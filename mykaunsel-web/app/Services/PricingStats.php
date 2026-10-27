<?php

namespace App\Services;

use App\Enums\PromoCodeStatus;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;

/**
 * Numbers for the Platform Admin Dashboard to show later. The Dashboard is not connected to these yet.
 */
class PricingStats
{
    public function activePlans(): int
    {
        return SubscriptionPlan::query()->where('active', true)->count();
    }

    /** Promo codes that can be used right now. Uses PromoCode::status(), the one place that works the status out. */
    public function activePromoCodes(): int
    {
        return PromoCode::query()->get()->filter(fn (PromoCode $code) => $code->status() === PromoCodeStatus::Active)->count();
    }

    /** Organizations whose CURRENT plan ends from today up to $days days from now. */
    public function subscriptionsEndingWithin(int $days = 30): int
    {
        return SubscriptionAssignment::query()
            ->whereIn('id', Organization::query()->whereNotNull('current_assignment_id')->select('current_assignment_id'))
            ->whereBetween('ends_on', [today()->toDateString(), today()->addDays($days)->toDateString()])
            ->count();
    }
}
