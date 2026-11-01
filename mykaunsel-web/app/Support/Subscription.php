<?php

namespace App\Support;

use App\Enums\OrgType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The paid period of an organization decides what can be booked (docs/PAYMENTS_PLAN.md, "Subscription end date", decided 2 November 2026).
 *
 * - A session (or a time, or a time request) is allowed only if its DAY is on or before the last day of the organization's plan. The last day counts
 *   whole: a session at 23:00 on it is allowed.
 * - An organization that has no plan has no paid period, so nothing is allowed for it. The platform organization (independent counselors) never has a
 *   plan and is never blocked.
 * - No grace period. A session already booked inside the paid period still goes ahead; this only decides what can be offered or newly booked.
 *
 * Dates are Malaysian calendar days: `subscription_ends_at` keeps the last day as written in Malaysian time, and a time's `entry_date` is a Malaysian day.
 * ONE rule, in two forms: `allows()` for one organization and `scope()` for a query, so what is offered and what can be booked can not disagree.
 */
final class Subscription
{
    /** May something on this day (Y-m-d) happen for this organization? A time with no organization is not held to a plan. */
    public static function allows(?Organization $org, string $day): bool
    {
        if ($org === null || $org->org_type === OrgType::Platform) {
            return true;
        }

        return $org->subscription_ends_at !== null && $day <= $org->subscription_ends_at->toDateString();
    }

    /**
     * Keeps only the rows whose organization allows their day (the same rule as allows(), in SQL).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function scope(Builder $query, string $dayColumn = 'calendar_entries.entry_date', string $orgColumn = 'calendar_entries.context_org_id'): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull($orgColumn)
            ->orWhereExists(fn (QueryBuilder $org) => $org->selectRaw('1')->from('organizations')
                ->whereColumn('organizations.id', $orgColumn)
                ->where(fn (QueryBuilder $o) => $o
                    ->where('organizations.org_type', OrgType::Platform->value)
                    ->orWhere(fn (QueryBuilder $paid) => $paid
                        ->whereNotNull('organizations.subscription_ends_at')
                        ->whereRaw('DATE(organizations.subscription_ends_at) >= DATE('.$dayColumn.')')))));
    }
}
