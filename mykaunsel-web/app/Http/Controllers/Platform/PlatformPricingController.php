<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PromoCodeStatus;
use App\Exceptions\PricingChangeBlocked;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Services\PlatformSettings;
use App\Services\PromoCodeService;
use App\Services\SubscriptionPlanService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Platform Admin → Pricing & Promotions. There is NO payment: plans, prices and promo codes are records, and the page says so.
 * Tabs: Plans (with the commission setting) and Promo Codes. Giving a plan to an organization comes from the Organizations page.
 */
class PlatformPricingController extends Controller
{
    private const GROUPS = ['university' => 'University', 'corporate' => 'Corporate'];

    private const PROMOS_PER_PAGE = 15;

    public function index(Request $request, SubscriptionPlanService $plans): View
    {
        $tab = $request->query('tab') === 'promos' ? 'promos' : 'plans';
        $timezone = $request->user()->display_timezone;

        // One status per code, from the one place that works it out (PromoCode::status()). The list, the filter and the counts use it.
        $codes = PromoCode::query()->withExists('assignments')->orderByDesc('id')->get();
        $statuses = $codes->mapWithKeys(fn (PromoCode $code) => [$code->id => $code->status()]);

        $tabs = [
            ['key' => 'plans', 'label' => 'Plans', 'count' => SubscriptionPlan::query()->where('active', true)->count(), 'url' => route('platform.pricing')],
            ['key' => 'promos', 'label' => 'Promo Codes', 'count' => $statuses->filter(fn ($s) => $s === PromoCodeStatus::Active)->count(), 'url' => route('platform.pricing', ['tab' => 'promos'])],
        ];

        if ($tab === 'promos') {
            $filter = PromoCodeStatus::tryFrom((string) $request->query('status'));
            $shown = $filter ? $codes->filter(fn (PromoCode $code) => $statuses[$code->id] === $filter)->values() : $codes;
            $page = max(1, (int) $request->query('page', 1));

            return view('platform.pricing.index', [
                'tab' => $tab,
                'tabs' => $tabs,
                'timezone' => $timezone,
                'promos' => new LengthAwarePaginator($shown->forPage($page, self::PROMOS_PER_PAGE)->values(), $shown->count(), self::PROMOS_PER_PAGE, $page, ['path' => route('platform.pricing'), 'query' => $request->only('tab', 'status')]),
                'statuses' => $statuses,
                'statusFilter' => $filter,
                'statusCounts' => collect(PromoCodeStatus::cases())->mapWithKeys(fn (PromoCodeStatus $s) => [$s->value => $statuses->filter(fn ($x) => $x === $s)->count()]),
                'totalCodes' => $codes->count(),
            ]);
        }

        $all = SubscriptionPlan::query()->orderBy('display_order')->orderBy('min_members')->orderBy('id')->get();

        return view('platform.pricing.index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'groups' => self::GROUPS,
            'plans' => $all->groupBy(fn (SubscriptionPlan $plan) => $plan->org_type->value),
            'usage' => $plans->usage(),
            'coverage' => collect(self::GROUPS)->map(fn ($label, $type) => $plans->gapWarnings([$type]))->all(),
            'commission' => app(PlatformSettings::class)->commissionPercent(),
        ]);
    }

    // ---- plans -----------------------------------------------------------------------------------------------

    public function storePlan(Request $request, SubscriptionPlanService $plans): RedirectResponse
    {
        $type = (string) $request->input('org_type');
        abort_unless(array_key_exists($type, self::GROUPS), 422);

        $bag = "plan-new-{$type}";
        $data = $this->planInput($request, $bag);

        try {
            $result = $plans->create($request->user(), $data);
        } catch (PricingChangeBlocked $e) {
            return back()->withInput()->withErrors(['form' => implode(' ', $e->reasons)], $bag);
        }

        return $this->saved("The plan \"{$result['plan']->name}\" was added.", $result['warnings']);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan, SubscriptionPlanService $plans): RedirectResponse
    {
        $bag = "plan-{$plan->id}";
        $data = $this->planInput($request, $bag);
        $version = $request->validateWithBag($bag, ['version' => ['required', 'integer', 'min:1']])['version'];

        try {
            $result = $plans->update($request->user(), $plan, (int) $version, $data);
        } catch (PricingChangeBlocked $e) {
            return back()->withInput()->withErrors(['form' => implode(' ', $e->reasons)], $bag);
        }

        return $this->saved($result['changed'] ? "The plan \"{$result['plan']->name}\" was saved." : 'Nothing was changed.', $result['warnings']);
    }

    public function updateCommission(Request $request, PlatformSettings $settings): RedirectResponse
    {
        $data = $request->validateWithBag('commission', [
            'commission_percent' => ['required', 'string', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
        ], [
            'commission_percent.required' => 'Type a percentage from 0 to 100.',
            'commission_percent.regex' => 'Type a number from 0 to 100 with at most two decimals, for example 10 or 7.5.',
        ]);

        try {
            $changed = $settings->setCommissionPercent($request->user(), $data['commission_percent']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['commission_percent' => $e->getMessage()], 'commission');
        }

        return redirect()->route('platform.pricing')->with('status', $changed ? 'The commission was saved (a record only: no payment is processed).' : 'Nothing was changed.');
    }

    // ---- promo codes -----------------------------------------------------------------------------------------

    public function storePromo(Request $request, PromoCodeService $promos): RedirectResponse
    {
        $data = $this->promoInput($request, 'promo-new');

        try {
            $promo = $promos->create($request->user(), $data);
        } catch (PricingChangeBlocked $e) {
            return back()->withInput()->withErrors(['form' => implode(' ', $e->reasons)], 'promo-new');
        }

        return $this->promoDone("The code {$promo->code} was added.");
    }

    public function updatePromo(Request $request, PromoCode $promo, PromoCodeService $promos): RedirectResponse
    {
        $bag = "promo-{$promo->id}";
        $data = $this->promoInput($request, $bag);
        $version = $request->validateWithBag($bag, ['version' => ['required', 'integer', 'min:1']])['version'];

        try {
            $result = $promos->update($request->user(), $promo, (int) $version, $data);
        } catch (PricingChangeBlocked $e) {
            return back()->withInput()->withErrors(['form' => implode(' ', $e->reasons)], $bag);
        }

        return $this->promoDone($result['changed'] ? "The code {$result['promo']->code} was saved." : 'Nothing was changed.');
    }

    public function disablePromo(Request $request, PromoCode $promo, PromoCodeService $promos): RedirectResponse
    {
        $changed = $promos->setDisabled($request->user(), $promo, true);

        return $this->promoDone($changed ? "The code {$promo->code} is disabled. It cannot be used until it is enabled again." : "The code {$promo->code} was already disabled.");
    }

    public function enablePromo(Request $request, PromoCode $promo, PromoCodeService $promos): RedirectResponse
    {
        $changed = $promos->setDisabled($request->user(), $promo, false);

        return $this->promoDone($changed ? "The code {$promo->code} is enabled." : "The code {$promo->code} was already enabled.");
    }

    public function destroyPromo(Request $request, PromoCode $promo, PromoCodeService $promos): RedirectResponse
    {
        try {
            $promos->delete($request->user(), $promo);
        } catch (PricingChangeBlocked $e) {
            return redirect()->route('platform.pricing', ['tab' => 'promos'])->with('error', implode(' ', $e->reasons));
        }

        return $this->promoDone("The code {$promo->code} was deleted.");
    }

    // ---- helpers ---------------------------------------------------------------------------------------------

    /** @param  list<string>  $warnings shown in yellow under the message (gaps in coverage); nothing is attached when there are none */
    private function saved(string $status, array $warnings): RedirectResponse
    {
        $redirect = redirect()->route('platform.pricing')->with('status', $status);

        return $warnings === [] ? $redirect : $redirect->with('warnings', $warnings);
    }

    private function promoDone(string $status): RedirectResponse
    {
        return redirect()->route('platform.pricing', ['tab' => 'promos'])->with('status', $status);
    }

    /**
     * The shape of a plan form. The rules that depend on other plans or on use (overlap, type change, version) are in
     * SubscriptionPlanService, which also repeats these shape checks.
     *
     * @return array{org_type: string, name: string, min_members: int, max_members: ?int, price_cents: int, active: bool}
     */
    private function planInput(Request $request, string $bag): array
    {
        $cap = (int) config('pricing.plan.max_members');

        $data = $request->validateWithBag($bag, [
            'org_type' => ['required', Rule::in(array_keys(self::GROUPS))],
            'name' => ['required', 'string', 'max:100'],
            'min_members' => ['required', 'integer', 'min:1', "max:{$cap}"],
            'max_members' => ['nullable', 'integer', 'gte:min_members', "max:{$cap}"],
            'price' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                $cents = Money::parseRm($value);

                if ($cents === null) {
                    $fail('Type the yearly price in RM with at most two decimals, for example 1200.00.');
                } elseif ($cents <= 0) {
                    $fail('The yearly price must be more than RM 0.00.');
                } elseif ($cents > (int) config('pricing.plan.max_price_cents')) {
                    $fail('That price is higher than the limit of RM '.number_format((int) config('pricing.plan.max_price_cents') / 100, 2).'.');
                }
            }],
        ], [
            'name.required' => 'Give the plan a name.',
            'min_members.required' => 'Write the smallest number of members.',
            'min_members.integer' => 'The minimum must be a whole number.',
            'max_members.integer' => 'The maximum must be a whole number, or empty for no upper limit.',
            'max_members.gte' => 'The maximum cannot be less than the minimum.',
            'price.required' => 'Write the yearly price in RM.',
        ]);

        return [
            'org_type' => $data['org_type'],
            'name' => trim($data['name']),
            'min_members' => (int) $data['min_members'],
            'max_members' => isset($data['max_members']) ? (int) $data['max_members'] : null,
            'price_cents' => Money::parseRm($data['price']),
            'active' => $request->boolean('active'),
        ];
    }

    /**
     * The shape of a promo code form. Dates are typed in the admin's own timezone and stored in UTC. The rules that depend on
     * other codes or on use (duplicate, locked fields, version) are in PromoCodeService, which also repeats these shape checks.
     *
     * @return array{code: string, type: string, value: int, valid_from: Carbon, valid_until: Carbon, usage_limit: ?int, applies_to: string}
     */
    private function promoInput(Request $request, string $bag): array
    {
        $timezone = $request->user()->display_timezone;
        $type = (string) $request->input('type');

        $data = $request->validateWithBag($bag, [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{4,32}$/'],
            'type' => ['required', Rule::in(array_keys(PromoCode::TYPES))],
            'value' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($type) {
                if ($type === 'percent') {
                    if (! preg_match('/^\d{1,3}$/', $value) || (int) $value < 1 || (int) $value > 100) {
                        $fail('A percentage must be a whole number from 1 to 100.');
                    }

                    return;
                }

                $cents = Money::parseRm($value);

                if ($cents === null) {
                    $fail('Type the discount in RM with at most two decimals, for example 100.00.');
                } elseif ($cents <= 0) {
                    $fail('The discount must be more than RM 0.00.');
                } elseif ($cents > (int) config('pricing.plan.max_price_cents')) {
                    $fail('That discount is higher than the limit of RM '.number_format((int) config('pricing.plan.max_price_cents') / 100, 2).'.');
                }
            }],
            'valid_from' => ['required', 'date_format:Y-m-d\TH:i'],
            'valid_until' => ['required', 'date_format:Y-m-d\TH:i', 'after:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'applies_to' => ['required', Rule::in(array_keys(PromoCode::APPLIES_TO))],
        ], [
            'code.required' => 'Write the code.',
            'code.regex' => 'The code must be 4 to 32 characters: letters, numbers and hyphens only.',
            'valid_from.required' => 'Choose when the code starts.',
            'valid_from.date_format' => 'Choose the start date and time.',
            'valid_until.required' => 'Choose when the code ends.',
            'valid_until.date_format' => 'Choose the end date and time.',
            'valid_until.after' => 'The code must end after it starts.',
            'usage_limit.integer' => 'The usage limit must be a whole number, or empty for unlimited.',
            'usage_limit.min' => 'The usage limit must be at least 1, or empty for unlimited.',
        ]);

        return [
            'code' => strtoupper($data['code']),
            'type' => $data['type'],
            'value' => $data['type'] === 'percent' ? (int) $data['value'] : Money::parseRm($data['value']),
            'valid_from' => Carbon::createFromFormat('Y-m-d\TH:i', $data['valid_from'], $timezone)->utc(),
            'valid_until' => Carbon::createFromFormat('Y-m-d\TH:i', $data['valid_until'], $timezone)->utc(),
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'applies_to' => $data['applies_to'],
        ];
    }
}
