<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a booking (docs/AVAILABILITY.md, "Cancelling").
 *
 * THE CONTRACT (B9): the status change and giving the time back (`calendar_entries.is_available` on) happen in ONE transaction. Otherwise the
 * time would stay "booked" forever.
 *
 * - Only the person's own booking. Someone else's and an unknown one are the same `booking_not_found`.
 * - Allowed only before the deadline: the session's start minus the slot's organization's cancellation hours (or the configured default).
 *   Inside the window: `cancellation_closed`. Completed, no-show or already over: `not_cancellable`.
 * - Safe to repeat (a lost answer, a double tap): cancelling a booking that is already cancelled answers the same booking again and touches
 *   NOTHING. It must not, because the time may have been booked by someone else in the meantime.
 * - Same lock order as booking (the person, then the counselor's profile, then the booking), so the two never wait for each other in a circle.
 */
class BookingCancellation
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
    ) {}

    /**
     * The rule, in one place: used by the details ("can I cancel?") and by cancelling itself, so they cannot disagree.
     *
     * @return array{hours: int, deadline: CarbonImmutable, allowed: bool}
     */
    public function rule(Booking $booking, CalendarEntry $entry): array
    {
        $hours = $this->bookings->cancellationHours($entry);
        $start = $this->availability->startOf($entry);
        $deadline = $start->subHours($hours);
        $now = $this->availability->now();

        return [
            'hours' => $hours,
            'deadline' => $deadline,
            'allowed' => $booking->status === BookingStatus::Confirmed && $start->gt($now) && $deadline->gt($now),
        ];
    }

    /**
     * @return array{booking: Booking, cancelled_now: bool}  cancelled_now is false for a repeat
     *
     * @throws BookingRefused booking_not_found | cancellation_closed | not_cancellable
     */
    public function cancel(User $user, string $publicId): array
    {
        $found = Booking::query()->where('user_id', $user->getKey())->where('public_id', strtolower($publicId))->first();

        if ($found === null || $found->calendar_entry_id === null) {
            throw new BookingRefused('booking_not_found');
        }

        return DB::transaction(function () use ($user, $found) {
            // LOCK ORDER: the person, the counselor's profile, then the booking (the same as booking a time).
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            CounselorProfile::query()->where('user_id', $found->counselor_user_id)->lockForUpdate()->first();
            $booking = Booking::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            // A repeat: already cancelled. Say so again; change nothing (the time may be someone else's now).
            if ($booking->status === BookingStatus::Cancelled) {
                return ['booking' => $booking, 'cancelled_now' => false];
            }

            $entry = CalendarEntry::query()->findOrFail($booking->calendar_entry_id);
            $rule = $this->rule($booking, $entry);

            if ($booking->status !== BookingStatus::Confirmed || ! $this->availability->startOf($entry)->gt($this->availability->now())) {
                throw new BookingRefused('not_cancellable');
            }

            if (! $rule['allowed']) {
                throw new BookingRefused('cancellation_closed', ['cancellation_hours' => $rule['hours']]);
            }

            // The status, only if it is still confirmed (the lock makes this certain; the condition keeps it honest)...
            $changed = Booking::query()->whereKey($booking->getKey())->where('status', BookingStatus::Confirmed->value)->update(['status' => BookingStatus::Cancelled->value]);

            if ($changed !== 1) {
                throw new BookingRefused('not_cancellable');
            }

            // ...and the time given back, in the same transaction. Only for THIS booking's own slot.
            CalendarEntry::query()->whereKey($entry->getKey())->update(['is_available' => true]);

            return ['booking' => $booking->fresh(), 'cancelled_now' => true];
        });
    }
}
