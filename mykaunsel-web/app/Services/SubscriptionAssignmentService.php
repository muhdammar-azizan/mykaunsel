<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\PromoCodeStatus;
use App\Exceptions\PricingChangeBlocked;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\OrganizationSize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Giving an organization a plan. THERE IS NO PAYMENT: an assignment is a record of which plan an organization is on, at what
 * price, until when.
 *
 * - Each assignment stores the plan price, the discount and the final price AS THEY WERE (snapshots): editing the plan or the
 *   code afterwards never changes them. The organization points to its current one; older ones stay as history.
 * - It lasts one year from the day it is made. If the member count is above the plan's maximum, that is only a warning.
 * - Redeeming a promo code happens in the SAME transaction as the assignment and its audit entries: the organization, the plan
 *   and the code rows are locked (always in that order), the code is checked, and used_count goes up in ONE conditional
 *   UPDATE ("... WHERE used_count < usage_limit"), so two admins using the last remaining use cannot both succeed. If anything
 *   fails, nothing is saved.
 * - Money is whole sen. A percentage is rounded to the nearest sen (half up); the final price is never below RM 0.00 and a
 *   fixed discount never goes above the price.
 */
class SubscriptionAssignmentService
{
    // ---- what can be offered ---------------------------------------------------------------------------------

    /**
     * The plans an admin can choose for this organization, best match first.
     *
     * Only ACTIVE plans of the organization's type. When the applicant stated a size, a plan that covers all of it is "fits", one
     * that covers part of it is "partial", and (unless $hideOutsideSize) one that covers none of it is "outside". When no size
     * was stated, every plan is offered ("unknown").
     *
     * @return array{applicable: bool, plans: Collection<int, array{plan: SubscriptionPlan, fit: string}>, size: ?array{0: int, 1: ?int}, size_label: ?string, none_cover: bool}
     */
    public function offeredPlans(Organization $organization, bool $hideOutsideSize = true): array
    {
        if (! $this->canHavePlan($organization)) {
            return ['applicable' => false, 'plans' => collect(), 'size' => null, 'size_label' => null, 'none_cover' => false];
        }

        $size = OrganizationSize::parse($organization->estimated_size);
        $order = ['fits' => 0, 'partial' => 1, 'unknown' => 2, 'outside' => 3];

        $plans = SubscriptionPlan::query()
            ->where('org_type', $organization->org_type->value)
            ->where('active', true)
            ->orderBy('display_order')->orderBy('min_members')->orderBy('id')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'plan' => $plan,
                'fit' => $size === null ? 'unknown' : (OrganizationSize::fit($plan->min_members, $plan->max_members, $size) === 'none' ? 'outside' : OrganizationSize::fit($plan->min_members, $plan->max_members, $size)),
            ]);

        $offered = $plans
            ->reject(fn (array $row) => $hideOutsideSize && $row['fit'] === 'outside')
            ->sortBy(fn (array $row) => $order[$row['fit']])   // stable: the plans keep their own order inside each group
            ->values();

        return [
            'applicable' => true,
            'plans' => $offered,
            'size' => $size,
            'size_label' => $size === null ? null : SubscriptionPlan::range($size[0], $size[1]),
            'none_cover' => $size !== null && $offered->isEmpty(),
        ];
    }

    /** Clinics and independent counselors are paid for by their clients (a commission), so they have no plan. */
    public function canHavePlan(Organization $organization): bool
    {
        return in_array($organization->org_type, [OrgType::University, OrgType::Corporate], true);
    }

    // ---- the price -------------------------------------------------------------------------------------------

    /**
     * What an assignment would record, WITHOUT saving anything or using up a code (for the preview).
     *
     * @return array{plan: SubscriptionPlan, promo: ?PromoCode, plan_price_cents: int, discount_cents: int, final_price_cents: int, starts_on: Carbon, ends_on: Carbon, members: int, warnings: list<string>}
     *
     * @throws PricingChangeBlocked
     */
    public function quote(Organization $organization, ?int $planId, ?string $code): array
    {
        $plan = $planId === null ? null : SubscriptionPlan::query()->find($planId);

        return $this->price($organization, $plan, $this->normaliseCode($code), lock: false);
    }

    /** The discount in sen. Percentages round to the nearest sen (half up); a fixed discount never exceeds the price. */
    public static function discount(int $priceCents, string $type, int $value): int
    {
        $discount = $type === 'percent'
            ? intdiv($priceCents * $value * 2 + 100, 200)   // round(price × percent ÷ 100), half up, in whole numbers
            : $value;

        return max(0, min($discount, $priceCents));
    }

    // ---- assigning -------------------------------------------------------------------------------------------

    /**
     * Give the organization a plan (the first one, or a change), optionally with a promo code. Nothing happens when no plan is
     * given (approving without a plan stays allowed). Run inside the caller's transaction when there is one (approving an
     * organization), so a failure undoes the approval too.
     *
     * @throws PricingChangeBlocked
     */
    public function assign(User $actor, Organization $organization, ?int $planId, ?string $code): ?SubscriptionAssignment
    {
        $code = $this->normaliseCode($code);

        if ($planId === null) {
            if ($code !== null) {
                throw new PricingChangeBlocked(['Choose a plan to use a promo code.']);
            }

            return null;
        }

        return DB::transaction(function () use ($actor, $organization, $planId, $code) {
            // Always in this order: the organization, the plan, the code.
            $org = Organization::query()->lockForUpdate()->findOrFail($organization->getKey());
            $plan = SubscriptionPlan::query()->lockForUpdate()->find($planId);

            $quote = $this->price($org, $plan, $code, lock: true);
            $promo = $quote['promo'];

            if ($promo !== null) {
                $now = now();
                $used = PromoCode::query()
                    ->whereKey($promo->getKey())
                    ->where('disabled', false)
                    ->where('valid_from', '<=', $now)
                    ->where('valid_until', '>=', $now)
                    ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
                    ->update(['used_count' => DB::raw('used_count + 1'), 'updated_at' => $now]);

                if ($used !== 1) {
                    throw new PricingChangeBlocked(["The code {$promo->code} has just been used up or disabled, so it was not applied. Nothing was saved."]);
                }
            }

            $previousId = $org->current_assignment_id;

            $assignment = SubscriptionAssignment::create([
                'org_id' => $org->id,
                'plan_id' => $plan->id,
                'promo_code_id' => $promo?->id,
                'plan_price_cents' => $quote['plan_price_cents'],
                'discount_cents' => $quote['discount_cents'],
                'final_price_cents' => $quote['final_price_cents'],
                'starts_on' => $quote['starts_on']->toDateString(),
                'ends_on' => $quote['ends_on']->toDateString(),
                'assigned_by' => $actor->id,
            ]);

            $org->forceFill([
                'current_assignment_id' => $assignment->id,
                'subscription_ends_at' => $quote['ends_on']->copy()->endOfDay(),
            ])->save();

            Audit::log($previousId === null ? 'organization.plan_assigned' : 'organization.plan_changed', $org, array_filter([
                'plan_id' => $plan->id,
                'assignment_id' => $assignment->id,
                'previous_assignment_id' => $previousId,
                'promo_code_id' => $promo?->id,
                'plan_price_cents' => $assignment->plan_price_cents,
                'discount_cents' => $assignment->discount_cents,
                'final_price_cents' => $assignment->final_price_cents,
                'starts_on' => $assignment->starts_on->toDateString(),
                'ends_on' => $assignment->ends_on->toDateString(),
            ], fn ($v) => $v !== null), $actor);

            if ($promo !== null) {
                Audit::log('promo.redeemed', $promo, [
                    'promo_id' => $promo->id,
                    'organization_id' => $org->id,
                    'assignment_id' => $assignment->id,
                    'discount_cents' => $assignment->discount_cents,
                    'used_count' => (int) PromoCode::query()->whereKey($promo->id)->value('used_count'),
                ], $actor);
            }

            return $assignment->load('plan');
        });
    }

    // ---- reading it back -------------------------------------------------------------------------------------

    /**
     * The organization's current plan, price and end date, for the Organization Admin's Settings > Subscription tab (not built yet).
     * Null when it has none.
     *
     * @return array{plan_id: int, plan_name: string, list_price_cents: int, discount_cents: int, price_cents: int, promo_code: ?string, starts_on: Carbon, ends_on: Carbon, ended: bool}|null
     */
    public function currentSubscription(Organization $organization): ?array
    {
        $a = $organization->current_assignment_id === null ? null : SubscriptionAssignment::query()->with(['plan', 'promoCode'])->find($organization->current_assignment_id);

        return $a === null ? null : [
            'plan_id' => $a->plan_id,
            'plan_name' => $a->plan->name,
            'list_price_cents' => $a->plan_price_cents,
            'discount_cents' => $a->discount_cents,
            'price_cents' => $a->final_price_cents,
            'promo_code' => $a->promoCode?->code,
            'starts_on' => $a->starts_on,
            'ends_on' => $a->ends_on,
            'ended' => $a->ends_on->lt(today()),
        ];
    }

    /** Every assignment of the organization, newest first (the current one first). */
    public function history(Organization $organization): Collection
    {
        return SubscriptionAssignment::query()->with(['plan', 'promoCode', 'assignedBy:id,name'])->where('org_id', $organization->id)->orderByDesc('id')->get();
    }

    /** Active students, staff and employees (the same count the organization page shows as "Members"). */
    public function memberCount(Organization $organization): int
    {
        return Membership::query()
            ->where('org_id', $organization->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [MembershipRole::Student->value, MembershipRole::Staff->value, MembershipRole::Employee->value])
            ->count();
    }

    // ---- internals -------------------------------------------------------------------------------------------

    /**
     * Checks everything and works out the three prices. With $lock the code row is locked for the rest of the transaction.
     *
     * @throws PricingChangeBlocked
     */
    private function price(Organization $org, ?SubscriptionPlan $plan, ?string $code, bool $lock): array
    {
        if (! $this->canHavePlan($org)) {
            throw new PricingChangeBlocked(['Clinics and independent counselors have no plan: their clients pay and the platform keeps a commission.']);
        }

        if ($org->subscription_status !== OrgStatus::Active) {
            throw new PricingChangeBlocked(['Only an active organization can be given a plan.']);
        }

        if ($plan === null || ! $plan->active) {
            throw new PricingChangeBlocked(['That plan is not available any more. Choose another one.']);
        }

        if ($plan->org_type !== $org->org_type) {
            throw new PricingChangeBlocked(['That plan is for '.$plan->org_type->value.' organizations.']);
        }

        $promo = null;
        $discount = 0;

        if ($code !== null) {
            $query = PromoCode::query()->where('code', $code);
            $promo = ($lock ? $query->lockForUpdate() : $query)->first();

            if ($promo === null) {
                throw new PricingChangeBlocked(["There is no promo code \"{$code}\"."]);
            }

            match ($promo->status()) {
                PromoCodeStatus::Disabled => throw new PricingChangeBlocked(["The code {$promo->code} is disabled."]),
                PromoCodeStatus::UsedUp => throw new PricingChangeBlocked(["The code {$promo->code} has been used up."]),
                PromoCodeStatus::Expired => throw new PricingChangeBlocked(["The code {$promo->code} has expired."]),
                PromoCodeStatus::Scheduled => throw new PricingChangeBlocked(["The code {$promo->code} is not valid yet."]),
                PromoCodeStatus::Active => null,
            };

            if (! $promo->appliesToType($org->org_type)) {
                throw new PricingChangeBlocked(["The code {$promo->code} is only for ".$promo->applies_to.' organizations.']);
            }

            $discount = self::discount($plan->price_cents, $promo->type, $promo->value);
        }

        $zone = (string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur');
        $start = Carbon::now($zone)->startOfDay();
        $members = $this->memberCount($org);

        return [
            'plan' => $plan,
            'promo' => $promo,
            'plan_price_cents' => $plan->price_cents,
            'discount_cents' => $discount,
            'final_price_cents' => max(0, $plan->price_cents - $discount),
            'starts_on' => $start,
            'ends_on' => $start->copy()->addYearNoOverflow(),
            'members' => $members,
            'warnings' => $plan->max_members !== null && $members > $plan->max_members
                ? ['This organization has '.number_format($members).' members, which is more than the plan\'s maximum of '.number_format($plan->max_members).'. Nothing is blocked.']
                : [],
        ];
    }

    private function normaliseCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return $code === '' ? null : $code;
    }
}
