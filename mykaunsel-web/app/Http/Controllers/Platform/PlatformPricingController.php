<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\PricingChangeBlocked;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\PlatformSettings;
use App\Services\SubscriptionPlanService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Platform Admin → Pricing & Promotions. There is NO payment: plans, prices and promo codes are records, and the page says so.
 * Step 1: the Plans tab and the commission setting.
 */
class PlatformPricingController extends Controller
{
    private const GROUPS = ['university' => 'University', 'corporate' => 'Corporate'];

    public function index(Request $request, SubscriptionPlanService $plans, PlatformSettings $settings): View
    {
        $all = SubscriptionPlan::query()->orderBy('display_order')->orderBy('min_members')->orderBy('id')->get();
        $usage = $plans->usage();

        return view('platform.pricing.index', [
            'groups' => self::GROUPS,
            'plans' => $all->groupBy(fn (SubscriptionPlan $plan) => $plan->org_type->value),
            'usage' => $usage,
            'coverage' => collect(self::GROUPS)->map(fn ($label, $type) => $plans->gapWarnings([$type]))->all(),
            'commission' => $settings->commissionPercent(),
            'tabs' => [['key' => 'plans', 'label' => 'Plans', 'count' => $all->where('active', true)->count(), 'url' => route('platform.pricing')]],
            'tab' => 'plans',
        ]);
    }

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

    /** @param  list<string>  $warnings shown in yellow under the message (gaps in coverage); nothing is attached when there are none */
    private function saved(string $status, array $warnings): RedirectResponse
    {
        $redirect = redirect()->route('platform.pricing')->with('status', $status);

        return $warnings === [] ? $redirect : $redirect->with('warnings', $warnings);
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
}
