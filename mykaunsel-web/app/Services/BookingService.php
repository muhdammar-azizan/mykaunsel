<?php

namespace App\Services;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Support\CounselorPublicPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Mode A (slot-based, auto-confirmed) booking, and answers the read-only question "could I?" with the SAME functions, so the
 * preview and the booking cannot disagree. See docs/AVAILABILITY.md ("The confirm step").
 *
 * Which rule decides what (nothing is written twice):
 *   - who may book this counselor and slot: CounselorDirectory::resolveSlot (one "no": counselor_unavailable)
 *   - whether the TIME can still be booked:  AvailabilityService::slotBookable (one "no": slot_taken)
 *   - the person's own limits:               refusalFor() below (time_conflict, upcoming_limit_reached)
 *
 * The time is NOT held while the person reads the confirm screen. It is claimed here, in one conditional UPDATE, under two locks.
 */
class BookingService
{
    public function __construct(
        private readonly CounselorDirectory $directory,
        private readonly AvailabilityService $availability,
    ) {}

    // =============================================================================================================
    //  Preview (read only)
    // =============================================================================================================

    /**
     * What the confirm screen shows, and whether the booking would be accepted right now. Claims nothing.
     *
     * @return array<string, mixed>
     *
     * @throws BookingRefused counselor_unavailable
     */
    public function preview(User $user, string $slotPublicId): array
    {
        $found = $this->directory->resolveSlot($user, $slotPublicId) ?? throw new BookingRefused('counselor_unavailable');

        $reason = $this->refusalFor($user, $found['slot']);

        // A time that is gone says nothing more: no details, and never why (a private block, the buffer, the lead time all look the same).
        if ($reason === 'slot_taken') {
            return ['valid' => false, 'reason' => $reason];
        }

        return ['valid' => $reason === null, 'reason' => $reason] + $this->details($user, $found);
    }

    // =============================================================================================================
    //  Create
    // =============================================================================================================

    /**
     * @param  array{slot: string, expected_starts_at: string, expected_duration_minutes: int, expected_mode: string}  $input
     * @return array{booking: Booking, slot: CalendarEntry, profile: CounselorProfile, orgIds: list<int>}
     *
     * @throws BookingRefused
     */
    public function create(User $user, array $input): array
    {
        // Only to learn WHICH counselor to lock. Everything is decided again after the locks.
        $first = $this->directory->resolveSlot($user, $input['slot']) ?? throw new BookingRefused('counselor_unavailable');

        return DB::transaction(function () use ($user, $input, $first) {
            // LOCK ORDER (always this order, so two bookings never wait for each other in a circle):
            //   1. the booking person  2. the counselor's profile (this serializes EVERY booking of that counselor, whatever the context)
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            CounselorProfile::query()->where('user_id', $first['slot']->counselor_user_id)->lockForUpdate()->first();

            $found = $this->directory->resolveSlot($user, $input['slot']) ?? throw new BookingRefused('counselor_unavailable');
            $slot = $found['slot'];

            $reason = $this->refusalFor($user, $slot, checkLimits: false);

            if ($reason !== null) {
                throw new BookingRefused($reason);
            }

            if ($this->differs($slot, $input)) {
                throw new BookingRefused('slot_changed', ['slot' => $this->details($user, $found)]);
            }

            $reason = $this->limitsRefusal($user, $slot);

            if ($reason !== null) {
                throw new BookingRefused($reason, $reason === 'upcoming_limit_reached' ? ['max_upcoming_sessions' => $this->cap()] : []);
            }

            // THE CLAIM: one conditional UPDATE, and the number of rows it changed is the answer. Never read-then-write.
            // It also insists that no booking that is not cancelled points at the slot.
            $claimed = CalendarEntry::query()
                ->whereKey($slot->id)
                ->where('is_available', true)
                ->whereNotExists(fn ($b) => $b->selectRaw('1')->from('bookings')
                    ->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')
                    ->where('bookings.status', '!=', BookingStatus::Cancelled->value))
                ->update(['is_available' => false]);

            if ($claimed !== 1) {
                throw new BookingRefused('slot_taken');
            }

            $booking = Booking::create([
                'org_id' => $slot->context_org_id,
                'user_id' => $user->getKey(),
                'counselor_user_id' => $slot->counselor_user_id,
                'calendar_entry_id' => $slot->id,
                'booking_mode' => BookingMode::SlotBased,
                'status' => BookingStatus::Confirmed,
                'session_mode' => $slot->session_mode ?? SessionMode::Online,
                'location_id' => $slot->location_id,
                'reschedule_count' => 0,
            ]);

            return ['booking' => $booking, 'slot' => $slot, 'profile' => $found['profile'], 'orgIds' => $found['orgIds']];
        });
    }

    // =============================================================================================================
    //  The rules (shared by preview and create)
    // =============================================================================================================

    /** @return 'slot_taken'|'time_conflict'|'upcoming_limit_reached'|null */
    private function refusalFor(User $user, CalendarEntry $slot, bool $checkLimits = true): ?string
    {
        if (! $this->availability->slotBookable($slot)) {
            return 'slot_taken';
        }

        return $checkLimits ? $this->limitsRefusal($user, $slot) : null;
    }

    /** The person's own limits: no overlapping session with ANY counselor, and no more than the cap of upcoming sessions. */
    private function limitsRefusal(User $user, CalendarEntry $slot): ?string
    {
        return $this->limitsRefusalAt($user, $this->availability->startOf($slot), (int) $slot->duration_minutes);
    }

    /**
     * The same two limits for a stretch of time that is not a slot (a requested time being approved).
     *
     * @return 'time_conflict'|'upcoming_limit_reached'|null
     */
    public function limitsRefusalAt(User $user, CarbonImmutable $start, int $minutes): ?string
    {
        $end = $start->addMinutes($minutes);
        $upcoming = $this->upcomingSessions($user);

        foreach ($upcoming as [$otherStart, $otherEnd]) {
            if ($start->getTimestamp() < $otherEnd && $end->getTimestamp() > $otherStart) {
                return 'time_conflict';
            }
        }

        return count($upcoming) >= $this->cap() ? 'upcoming_limit_reached' : null;
    }

    /**
     * The person's sessions that are confirmed and have not started yet, as [start timestamp, end timestamp]. Cancelled, completed and
     * no-show ones, and ones already under way or past, do not count.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function upcomingSessions(User $user, ?int $exceptEntryId = null): array
    {
        $now = $this->availability->now();

        $entries = CalendarEntry::query()
            ->whereIn('id', Booking::query()->where('user_id', $user->getKey())->where('status', BookingStatus::Confirmed->value)->whereNotNull('calendar_entry_id')->select('calendar_entry_id'))
            ->whereDate('entry_date', '>=', $now->format('Y-m-d'))
            ->when($exceptEntryId !== null, fn ($q) => $q->whereKeyNot($exceptEntryId))
            ->get();

        $sessions = [];

        foreach ($entries as $entry) {
            $start = $this->availability->startOf($entry);

            if ($start->gt($now)) {
                $sessions[] = [$start->getTimestamp(), $start->addMinutes((int) $entry->duration_minutes)->getTimestamp()];
            }
        }

        return $sessions;
    }

    /**
     * For moving a booking: would [slot] overlap another upcoming session of the person? The session being moved (its slot [exceptEntryId])
     * does not count against itself, and the cap does not apply (one session is replaced by another).
     *
     * @return 'time_conflict'|null
     */
    public function overlapRefusal(User $user, CalendarEntry $slot, int $exceptEntryId): ?string
    {
        $start = $this->availability->startOf($slot);
        $end = $start->addMinutes((int) $slot->duration_minutes);

        foreach ($this->upcomingSessions($user, $exceptEntryId) as [$otherStart, $otherEnd]) {
            if ($start->getTimestamp() < $otherEnd && $end->getTimestamp() > $otherStart) {
                return 'time_conflict';
            }
        }

        return null;
    }

    /** Does the stretch overlap an upcoming confirmed session of the person? (No cap: used when a request is made.) */
    public function overlapsUpcoming(User $user, CarbonImmutable $start, int $minutes): bool
    {
        $end = $start->addMinutes($minutes);

        foreach ($this->upcomingSessions($user) as [$otherStart, $otherEnd]) {
            if ($start->getTimestamp() < $otherEnd && $end->getTimestamp() > $otherStart) {
                return true;
            }
        }

        return false;
    }

    private function cap(): int
    {
        return max(1, (int) config('booking.max_upcoming_sessions'));
    }

    /** Does what the person saw differ from the slot as it is now? (The counselor edited it meanwhile.) */
    public function differs(CalendarEntry $slot, array $input): bool
    {
        return $this->availability->startOf($slot)->getTimestamp() !== CarbonImmutable::parse($input['expected_starts_at'])->getTimestamp()
            || (int) $slot->duration_minutes !== (int) $input['expected_duration_minutes']
            || ($slot->session_mode ?? SessionMode::Online)->value !== $input['expected_mode'];
    }

    // =============================================================================================================
    //  What the person is told (no price, no link, no street address, no organization name, no ids but public ones)
    // =============================================================================================================

    /**
     * @param  array{slot: CalendarEntry, profile: CounselorProfile, orgIds: list<int>}  $found
     * @return array<string, mixed>
     */
    public function details(User $user, array $found): array
    {
        $slot = $found['slot'];
        $start = $this->availability->startOf($slot);
        $end = $start->addMinutes((int) $slot->duration_minutes);
        $mode = ($slot->session_mode ?? SessionMode::Online);
        $hours = $this->cancellationHours($slot);
        $deadline = $start->subHours($hours);
        $count = count($this->upcomingSessions($user));

        $details = [
            'slot' => $slot->public_id,
            'counselor' => [
                'id' => $found['profile']->public_id,
                'display_name' => CounselorPublicPresenter::displayName($found['profile'], $found['orgIds']),
                'verified' => true,
            ],
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'duration_minutes' => (int) $slot->duration_minutes,
            'mode' => $mode->value,
            'location' => $this->location($slot, $mode),
            'cancellation_deadline_at' => $deadline->gt($this->availability->now()) ? $deadline->toIso8601String() : null,
            'cancellation_hours' => $hours,
            'max_reschedules' => (int) config('booking.max_reschedules'),
            'meeting_link_visible_minutes' => (int) config('booking.meeting_link_visible_minutes'),
        ];

        // Only said when it matters: the person is at the cap, or one below it.
        if ($count >= $this->cap() - 1) {
            $details['upcoming_sessions'] = $count;
            $details['max_upcoming_sessions'] = $this->cap();
        }

        return $details;
    }

    /** The answer to a successful booking. */
    public function created(array $made): array
    {
        $booking = $made['booking'];
        $slot = $made['slot'];
        $start = $this->availability->startOf($slot);
        $mode = $slot->session_mode ?? SessionMode::Online;

        return [
            'booking' => [
                'id' => $booking->public_id,
                'status' => $booking->status->value,
                'starts_at' => $start->toIso8601String(),
                'ends_at' => $start->addMinutes((int) $slot->duration_minutes)->toIso8601String(),
                'duration_minutes' => (int) $slot->duration_minutes,
                'mode' => $mode->value,
                'counselor' => ['display_name' => CounselorPublicPresenter::displayName($made['profile'], $made['orgIds'])],
                'location' => $this->location($slot, $mode),
            ],
        ];
    }

    /** Name and city only; the street address never leaves the server. */
    private function location(CalendarEntry $slot, SessionMode $mode): ?array
    {
        return $mode === SessionMode::Physical && $slot->location !== null
            ? ['name' => (string) $slot->location->name, 'city' => (string) $slot->location->city]
            : null;
    }

    /** The slot's organization decides; with none of its own, the configured default. */
    public function cancellationHours(CalendarEntry $slot): int
    {
        $hours = $slot->contextOrg?->cancellation_deadline_hours;

        return $hours === null ? (int) config('booking.cancellation_default_hours') : (int) $hours;
    }
}
