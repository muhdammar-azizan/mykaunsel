<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An organization admin's Settings page (portal).
 *
 * - Read only here: the name, kind and SSM number (MyKaunsel checked them when it approved the organization, so only MyKaunsel changes them), the
 *   e-mail domains and how members join (a domain decides who joins on their own, so MyKaunsel manages it), and the people who administer it.
 * - Changeable by the organization: how many hours before a session a member may still cancel or move it (`cancellation_deadline_hours`; it applies to
 *   every session of this organization's counselors, including ones already booked, because the rule is worked out when someone cancels).
 * - Who may be booked, and independent work by the counselors, are on the Counselors page.
 * - Audit: which setting and the old and new number.
 */
class OrgSettings
{
    /** The hours offered: a few sensible steps, not free text. */
    public const DEADLINE_CHOICES = [1, 2, 6, 12, 24, 48, 72];

    /** @return array<string, mixed> */
    public function for(Organization $org): array
    {
        $org->loadMissing('orgDomains');

        $admins = Membership::query()->where('org_id', $org->getKey())->where('role', MembershipRole::OrgAdmin->value)->where('status', MembershipStatus::Active->value)
            ->with('user:id,name,email')->orderBy('joined_at')->get();

        $hours = (int) ($org->cancellation_deadline_hours ?? config('booking.cancellation_default_hours'));

        return [
            'organization' => $org,
            'domains' => $org->orgDomains,
            'admins' => $admins,
            'deadline_hours' => $hours,
            'deadline_choices' => collect(self::DEADLINE_CHOICES)->push($hours)->unique()->sort()->values()->all(),
        ];
    }

    /** @return bool whether anything changed */
    public function update(Organization $org, User $admin, int $deadlineHours): bool
    {
        return DB::transaction(function () use ($org, $admin, $deadlineHours) {
            $locked = Organization::query()->lockForUpdate()->findOrFail($org->getKey());
            $old = (int) ($locked->cancellation_deadline_hours ?? config('booking.cancellation_default_hours'));

            if ($old === $deadlineHours) {
                return false;
            }

            $locked->forceFill(['cancellation_deadline_hours' => $deadlineHours])->save();
            Audit::log('organization.settings_updated', $locked, ['changes' => ['cancellation_deadline_hours' => ['old' => $old, 'new' => $deadlineHours]]], $admin);

            return true;
        });
    }
}
