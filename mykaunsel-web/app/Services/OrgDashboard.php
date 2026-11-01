<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What an organization admin sees first (portal Dashboard): counts only, never a name from a session.
 *
 * - Members and counselors are the admin's own people (they already see them on the Members page), so those numbers are exact.
 * - Sessions are use of the service: a number of 1 to (min_group - 1) is shown as "fewer than N" (config/org-reports.php), so nobody can be
 *   recognised from it. Sessions are counted by the organization the time was opened for, by Malaysian calendar month, and cancelled ones do not count.
 * - Notices: the plan (none, ending soon, ended), invoices waiting for payment, and members who have waited for approval a while.
 */
class OrgDashboard
{
    public function __construct(private readonly AvailabilityService $availability, private readonly SubscriptionAssignmentService $plans) {}

    /** @return array<string, mixed> */
    public function for(Organization $org): array
    {
        $now = $this->availability->now();
        $thisMonth = $now->startOfMonth();
        $lastMonth = $thisMonth->subMonth();

        $clientRoles = array_map(fn ($r) => $r->value, MembershipService::CLIENT_ROLES);
        $people = fn (MembershipStatus $status, array $roles) => Membership::query()->where('org_id', $org->getKey())->whereIn('role', $roles)->where('status', $status->value);

        $waiting = $people(MembershipStatus::PendingApproval, $clientRoles);
        $oldestWaiting = (clone $waiting)->min('joined_at');

        return [
            'name' => $org->name,
            'members' => $people(MembershipStatus::Active, $clientRoles)->count(),
            'waiting' => (clone $waiting)->count(),
            'counselors' => $people(MembershipStatus::Active, [MembershipRole::Counselor->value])->count(),
            'sessions_now' => $this->sessions($org, $thisMonth, $thisMonth->endOfMonth()),
            'sessions_before' => $this->sessions($org, $lastMonth, $lastMonth->endOfMonth()),
            'month_now' => $thisMonth->format('F'),
            'month_before' => $lastMonth->format('F'),
            'min_group' => (int) config('org-reports.min_group'),
            'notices' => $this->notices($org, $oldestWaiting === null ? null : CarbonImmutable::parse($oldestWaiting), (clone $waiting)->count()),
        ];
    }

    /** @return array{count: int, shown: string} */
    private function sessions(Organization $org, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $count = Booking::query()
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.org_id', $org->getKey())->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->whereDate('calendar_entries.entry_date', '>=', $from->format('Y-m-d'))->whereDate('calendar_entries.entry_date', '<=', $to->format('Y-m-d'))
            ->count();

        $min = (int) config('org-reports.min_group');

        return ['count' => $count, 'shown' => $count > 0 && $count < $min ? 'fewer than '.$min : (string) $count];
    }

    /** @return list<array{tone: string, text: string, url: string|null, link: string|null}> */
    private function notices(Organization $org, ?CarbonImmutable $oldestWaiting, int $waiting): array
    {
        $notices = [];

        if ($waiting > 0 && $oldestWaiting !== null && $oldestWaiting->lte(now()->subDays((int) config('org-reports.waiting_notice_days')))) {
            $notices[] = ['tone' => 'amber', 'text' => $waiting.' '.($waiting === 1 ? 'person has' : 'people have').' been waiting for your approval for '.(int) $oldestWaiting->diffInDays(now()).' days or more.', 'url' => route('org.members'), 'link' => 'Review'];
        }

        if ($this->plans->canHavePlan($org)) {
            $state = $org->subscriptionState();

            if ($org->currentAssignment === null) {
                $notices[] = ['tone' => 'amber', 'text' => 'Your organization has no subscription plan yet. MyKaunsel will set one up with you.', 'url' => null, 'link' => null];
            } elseif ($state === 'ended') {
                $notices[] = ['tone' => 'red', 'text' => 'Your subscription ended on '.$org->subscription_ends_at->format('j M Y').'.', 'url' => null, 'link' => null];
            } elseif ($state === 'ending_soon') {
                $notices[] = ['tone' => 'amber', 'text' => 'Your subscription ends on '.$org->subscription_ends_at->format('j M Y').'.', 'url' => null, 'link' => null];
            }

            $overdue = Invoice::query()->where('org_id', $org->getKey())->overdue()->count();
            $unpaid = Invoice::query()->where('org_id', $org->getKey())->open()->count();

            if ($overdue > 0) {
                $notices[] = ['tone' => 'red', 'text' => $overdue.' '.($overdue === 1 ? 'invoice is' : 'invoices are').' unpaid after the due date.', 'url' => null, 'link' => null];
            } elseif ($unpaid > 0) {
                $notices[] = ['tone' => 'amber', 'text' => $unpaid.' '.($unpaid === 1 ? 'invoice is' : 'invoices are').' waiting for payment.', 'url' => null, 'link' => null];
            }
        }

        return $notices;
    }
}
