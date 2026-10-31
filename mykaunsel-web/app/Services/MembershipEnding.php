<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipNotEndable;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\MemberLeft;
use App\Notifications\MembershipEnded;
use App\Notifications\SessionCancelledMembershipEnded;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A client's membership of an organization ends: they leave by themselves, or the organization's admin removes them
 * (docs/APP_SCREENS.md, "Decisions of the owner", 4 and 5).
 *
 * - The place is freed at once (status offboarded), so the person can join another organization.
 * - Later sessions with the organization's counselors are cancelled and the time given back (as in an ordinary cancellation, B9), EXCEPT
 *   sessions starting within the next [GRACE_DAYS] days, which still go ahead. Requests nobody answered yet are closed.
 * - The person is told what happened to each session; each counselor is told about theirs. When the person LEAVES by themselves the
 *   organization's admins are told the NAME only. They are never told which sessions were affected.
 * - All of it in one transaction, with the same lock order as booking (the person, the counselors' profiles, then the bookings). The e-mails
 *   go out after, and a failing e-mail never undoes any of it.
 */
class MembershipEnding
{
    /** Sessions starting within this many days still go ahead, so nobody is cut off the day before a session. */
    public const GRACE_DAYS = 7;

    public function __construct(private readonly MembershipService $memberships, private readonly AvailabilityService $availability) {}

    /**
     * The person leaves the organization they belong to (or are waiting to join).
     *
     * @return array{organization: string, cancelled: int, kept: int}
     *
     * @throws MembershipNotEndable when they belong to no organization
     */
    public function leave(User $person): array
    {
        $held = $this->memberships->heldBy($person);

        if ($held === null) {
            throw new MembershipNotEndable;
        }

        return $this->end($held->organization, $held->getKey(), $person, 'person');
    }

    /**
     * An admin removes a member of THEIR organization.
     *
     * @return array{organization: string, cancelled: int, kept: int}
     *
     * @throws MembershipNotEndable when this person is not a member (any more)
     */
    public function remove(Organization $organization, int $membershipId, User $admin): array
    {
        return $this->end($organization, $membershipId, $admin, 'organization');
    }

    /** @param  'person'|'organization'  $by */
    private function end(Organization $organization, int $membershipId, User $actor, string $by): array
    {
        $cancelled = [];
        $kept = [];
        $closedRequests = 0;
        $wasPending = false;
        $person = null;

        DB::transaction(function () use ($organization, $membershipId, $actor, $by, &$cancelled, &$kept, &$closedRequests, &$wasPending, &$person) {
            $found = Membership::query()->where('org_id', $organization->getKey())->findOrFail($membershipId);

            // LOCK ORDER: the person, the counselors' profiles, then the bookings (the same as booking a time).
            $person = User::query()->whereKey($found->user_id)->lockForUpdate()->firstOrFail();
            $membership = Membership::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            $isClient = in_array($membership->role, MembershipService::CLIENT_ROLES, true);
            $ends = in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Suspended, MembershipStatus::NoticePeriod, MembershipStatus::PendingApproval], true);
            // A waiting request is answered with Approve or Decline, not removed.
            $wasPending = $membership->status === MembershipStatus::PendingApproval;

            if (! $isClient || ! $ends || ($wasPending && $by === 'organization')) {
                throw new MembershipNotEndable;
            }

            $membership->update([
                'status' => MembershipStatus::Offboarded,
                'offboarded_at' => now(),
                'offboard_reason' => $by === 'person' ? 'Left by the person' : 'Removed by the organization',
            ]);

            if (! $wasPending) {
                [$cancelled, $kept] = $this->sessions($person, $organization);
                $closedRequests = $this->closeRequests($person, $organization);
            }

            Audit::log($by === 'person' ? 'member.left' : 'member.removed', $by === 'person' ? $organization : $person, [
                'membership_id' => $membership->getKey(),
                'org_id' => $organization->getKey(),
                'cancelled_booking_ids' => array_map(fn (array $c) => $c['booking']->getKey(), $cancelled),
                'kept_booking_ids' => array_map(fn (array $k) => $k['booking']->getKey(), $kept),
                'closed_requests' => $closedRequests,
            ], $actor);
        });

        if (! $wasPending) {
            $this->tellEveryone($organization, $person, $by, $cancelled, $kept);
        }

        return ['organization' => $organization->name, 'cancelled' => count($cancelled), 'kept' => count($kept)];
    }

    /**
     * Cancels the later sessions with the organization's counselors, and gives their time back.
     *
     * @return array{0: list<array{booking: Booking, when: string}>, 1: list<array{booking: Booking, when: string}>}
     */
    private function sessions(User $person, Organization $organization): array
    {
        $now = $this->availability->now();
        $limit = $now->addDays(self::GRACE_DAYS);

        $bookings = Booking::query()
            ->where('user_id', $person->getKey())->where('org_id', $organization->getKey())
            ->where('status', BookingStatus::Confirmed->value)->whereNotNull('calendar_entry_id')
            ->with(['calendarEntry', 'counselor:id,name'])->orderBy('id')->get();

        // The counselors' profiles first, one by one in a fixed order, so this never waits for another booking in a circle.
        CounselorProfile::query()->whereIn('user_id', $bookings->pluck('counselor_user_id')->unique()->sort()->values())->orderBy('user_id')->lockForUpdate()->get();

        $cancelled = [];
        $kept = [];

        foreach ($bookings as $booking) {
            $booking = Booking::query()->whereKey($booking->getKey())->with(['calendarEntry', 'counselor:id,name'])->lockForUpdate()->firstOrFail();
            $entry = $booking->calendarEntry;

            if ($booking->status !== BookingStatus::Confirmed || $entry === null) {
                continue;
            }

            $start = $this->availability->startOf($entry);

            if ($start->lte($now)) {
                continue; // already under way or over: not a "later" session
            }

            $row = ['booking' => $booking, 'when' => $start->format('l, j F Y').', '.$start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A')];

            if ($start->lte($limit)) {
                $kept[] = $row;

                continue;
            }

            $booking->update(['status' => BookingStatus::Cancelled]);
            CalendarEntry::query()->whereKey($entry->getKey())->update(['is_available' => true]); // B9: in the same transaction
            $cancelled[] = $row;
        }

        return [$cancelled, $kept];
    }

    /** Requests for a time that nobody has answered yet can not be answered now. */
    private function closeRequests(User $person, Organization $organization): int
    {
        return BookingRequest::query()
            ->where('user_id', $person->getKey())->where('context_org_id', $organization->getKey())
            ->where('status', BookingRequestStatus::Pending->value)
            ->update([
                'status' => BookingRequestStatus::Rejected->value,
                'response_reason' => 'You are no longer a member of this organization.',
                'responded_at' => now(),
            ]);
    }

    /**
     * @param  list<array{booking: Booking, when: string}>  $cancelled
     * @param  list<array{booking: Booking, when: string}>  $kept
     */
    private function tellEveryone(Organization $organization, User $person, string $by, array $cancelled, array $kept): void
    {
        $line = fn (array $r) => $r['when'].' with '.$r['booking']->counselor->name;

        $this->tell($person, new MembershipEnded($organization->name, $by, array_map($line, $cancelled), array_map($line, $kept)));

        foreach ($cancelled as $row) {
            $this->tell($row['booking']->counselor, new SessionCancelledMembershipEnded($organization->name, $person->name, $row['when']));
        }

        // The organization hears the NAME of someone who left by themselves, and nothing about their sessions.
        if ($by === 'person') {
            foreach ($this->memberships->admins($organization) as $admin) {
                $this->tell($admin, new MemberLeft($organization->name, $person->name));
            }
        }
    }

    private function tell(?User $user, object $notification): void
    {
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
