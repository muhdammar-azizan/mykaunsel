<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;

/**
 * Numbers for the Platform Admin Dashboard to show later. The Dashboard is not connected to these yet.
 * (The count of active promo codes comes with the Promo Codes tab.)
 */
class PricingStats
{
    public function activePlans(): int
    {
        return SubscriptionPlan::query()->where('active', true)->count();
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
