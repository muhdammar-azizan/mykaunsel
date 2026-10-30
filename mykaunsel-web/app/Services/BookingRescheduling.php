<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moving a booking to another time of the SAME counselor (docs/AVAILABILITY.md, "Rescheduling").
 *
 * - The new time is claimed and the old time given back in ONE transaction (B9), under the same locks as booking and cancelling (the person,
 *   the counselor's profile, then the booking). The booking keeps its public id and its counselor; only its time (and so its mode, place and
 *   organization) changes, and `reschedule_count` goes up by one.
 * - The same time limit as cancelling (`BookingCancellation::rule`): inside the cancellation window it is closed. At most
 *   `booking.max_reschedules` moves.
 * - Safe to repeat: asking for the time the booking already has answers the booking again and changes NOTHING (no second count, no second e-mail).
 * - A time the person picked that was edited meanwhile is `slot_changed` (the expected_* fields), a time that is gone is `slot_taken`.
 */
class BookingRescheduling
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
        private readonly BookingCancellation $cancellation,
        private readonly CounselorDirectory $directory,
    ) {}

    /**
     * @param  array{slot: string, expected_starts_at: string, expected_duration_minutes: int, expected_mode: string}  $input
     * @return array{booking: Booking, moved_now: bool, from: CalendarEntry, to: CalendarEntry}
     *
     * @throws BookingRefused
     */
    public function reschedule(User $user, string $publicId, array $input): array
    {
        $found = Booking::query()->where('user_id', $user->getKey())->where('public_id', strtolower($publicId))->first();

        if ($found === null || $found->calendar_entry_id === null) {
            throw new BookingRefused('booking_not_found');
        }

        $first = $this->directory->resolveSlot($user, $input['slot']) ?? throw new BookingRefused('counselor_unavailable');

        if ((int) $first['slot']->counselor_user_id !== (int) $found->counselor_user_id) {
            throw new BookingRefused('different_counselor');
        }

        return DB::transaction(function () use ($user, $found, $input) {
            // LOCK ORDER: the person, the counselor's profile, then the booking (the same as booking and cancelling).
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();
            CounselorProfile::query()->where('user_id', $found->counselor_user_id)->lockForUpdate()->first();
            $booking = Booking::query()->whereKey($found->getKey())->lockForUpdate()->firstOrFail();

            $from = CalendarEntry::query()->findOrFail($booking->calendar_entry_id);

            $resolved = $this->directory->resolveSlot($user, $input['slot']) ?? throw new BookingRefused('counselor_unavailable');
            $to = $resolved['slot'];

            // A repeat: the booking is already at that time. Say so again; change nothing.
            if ($booking->status === BookingStatus::Confirmed && $to->id === $from->id) {
                return ['booking' => $booking, 'moved_now' => false, 'from' => $from, 'to' => $to];
            }

            if ($booking->status !== BookingStatus::Confirmed || ! $this->availability->startOf($from)->gt($this->availability->now())) {
                throw new BookingRefused('not_reschedulable');
            }

            $rule = $this->cancellation->rule($booking, $from);

            if (! $rule['allowed']) {
                throw new BookingRefused('reschedule_closed', ['cancellation_hours' => $rule['hours']]);
            }

            $max = (int) config('booking.max_reschedules');

            if ((int) $booking->reschedule_count >= $max) {
                throw new BookingRefused('reschedule_limit_reached', ['max_reschedules' => $max]);
            }

            if ((int) $to->counselor_user_id !== (int) $booking->counselor_user_id) {
                throw new BookingRefused('different_counselor');
            }

            if (! $this->availability->slotBookable($to)) {
                throw new BookingRefused('slot_taken');
            }

            if ($this->bookings->differs($to, $input)) {
                throw new BookingRefused('slot_changed', ['slot' => $this->bookings->details($user, $resolved)]);
            }

            if ($this->bookings->overlapRefusal($user, $to, $from->id) !== null) {
                throw new BookingRefused('time_conflict');
            }

            // THE CLAIM of the new time: one conditional UPDATE (the same as booking), and the number of rows it changed is the answer.
            $claimed = CalendarEntry::query()
                ->whereKey($to->id)
                ->where('is_available', true)
                ->whereNotExists(fn ($b) => $b->selectRaw('1')->from('bookings')
                    ->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')
                    ->where('bookings.status', '!=', BookingStatus::Cancelled->value))
                ->update(['is_available' => false]);

            if ($claimed !== 1) {
                throw new BookingRefused('slot_taken');
            }

            $booking->forceFill([
                'calendar_entry_id' => $to->id,
                'org_id' => $to->context_org_id,
                'session_mode' => $to->session_mode ?? SessionMode::Online,
                'location_id' => $to->location_id,
                'reschedule_count' => (int) $booking->reschedule_count + 1,
            ])->save();

            // ...and the old time given back, in the same transaction.
            CalendarEntry::query()->whereKey($from->id)->update(['is_available' => true]);

            return ['booking' => $booking->fresh(), 'moved_now' => true, 'from' => $from, 'to' => $to];
        });
    }
}
