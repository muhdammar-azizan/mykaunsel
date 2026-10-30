<?php

namespace App\Services;

use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * THE ONE place that decides which times can be booked (docs/AVAILABILITY.md). The mobile app's availability screen, the counselor's profile
 * and search, and later the confirm step, slot creation and the approval of a requested time all ask it, so they can never disagree.
 *
 * THE RULES
 * - Times are Malaysian wall-clock time (Asia/Kuala_Lumpur). A day starts at Malaysian midnight.
 * - A slot cannot be booked sooner than booking.min_lead_hours from now, nor later than booking.horizon_days from today.
 * - A slot is BOOKED when its is_available flag is false OR it has a booking that is not cancelled (two layers on purpose). Booking a slot
 *   sets the flag; cancelling must set it back (the contract is in docs/AVAILABILITY.md).
 * - A free slot is NOT OFFERED when it clashes with anything else on the same counselor's calendar: a personal block, or a booked slot of any
 *   context. "Clash" means the times overlap once the counselor's buffer (buffer_minutes) is added to BOTH ends of the other entry. The
 *   context (which organization the slot belongs to) is ignored on purpose: a counselor is one person.
 * - A clashing slot is simply left out. It is never shown as "booked", which would reveal the counselor's private calendar.
 *
 * Everything is worked out from ONE query of the counselor's entries for the range and compared in PHP: never a query per slot.
 */
class AvailabilityService
{
    public const AVAILABLE = 'available';

    public const BOOKED = 'booked';

    public function timezone(): string
    {
        return (string) config('counselors.directory.slot_timezone');
    }

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /** The first moment a slot may start to be bookable (now + the minimum lead time). */
    public function earliestStart(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? $this->now())->addHours((int) config('booking.min_lead_hours'));
    }

    /** The last day (Malaysian midnight) a slot may be on to be bookable. */
    public function lastDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? $this->now())->startOfDay()->addDays((int) config('booking.horizon_days'));
    }

    // =============================================================================================================
    //  The clash rule (used by everything that creates, shows or books a time)
    // =============================================================================================================

    /**
     * Would this stretch of time fit on the counselor's calendar? (Used when a slot is created, when a booking is confirmed and when a
     * requested time is approved.)
     *
     * @param  int|null  $exceptEntryId  an entry to ignore (for example the slot being booked or moved)
     */
    public function isFree(int $counselorUserId, CarbonInterface $start, int $durationMinutes, ?int $exceptEntryId = null): bool
    {
        $start = CarbonImmutable::instance($start)->setTimezone($this->timezone());
        $end = $start->addMinutes($durationMinutes);
        $busy = $this->busy([$counselorUserId], $start->startOfDay()->subDay(), $end->startOfDay()->addDay())[$counselorUserId] ?? [];

        return ! $this->clashes($start->getTimestamp(), $end->getTimestamp(), $busy, $exceptEntryId);
    }

    /**
     * The slots that do NOT clash with anything else on their counselor's calendar. One query for all of them.
     *
     * @param  Collection<int, CalendarEntry>  $slots  free slots (calendar entries with a date, a start time and a duration)
     * @return Collection<int, CalendarEntry>
     */
    public function withoutConflicts(Collection $slots): Collection
    {
        if ($slots->isEmpty()) {
            return $slots->values();
        }

        $first = $slots->min(fn (CalendarEntry $slot) => $this->startOf($slot)->getTimestamp());
        $last = $slots->max(fn (CalendarEntry $slot) => $this->startOf($slot)->getTimestamp());

        $busy = $this->busy(
            $slots->pluck('counselor_user_id')->unique()->all(),
            CarbonImmutable::createFromTimestamp($first, $this->timezone())->startOfDay()->subDay(),
            CarbonImmutable::createFromTimestamp($last, $this->timezone())->startOfDay()->addDay(),
        );

        return $slots->reject(function (CalendarEntry $slot) use ($busy) {
            $start = $this->startOf($slot);

            return $this->clashes($start->getTimestamp(), $start->addMinutes((int) $slot->duration_minutes)->getTimestamp(), $busy[$slot->counselor_user_id] ?? [], $slot->id);
        })->values();
    }

    /** True when the slot is taken: the flag is off OR a booking that is not cancelled points at it. */
    public function isBooked(CalendarEntry $slot): bool
    {
        return ! $slot->is_available || (bool) ($slot->has_active_booking ?? $slot->bookings()->where('status', '!=', 'cancelled')->exists());
    }

    // =============================================================================================================
    //  The answer of the availability screen
    // =============================================================================================================

    /**
     * @param  Collection<int, CalendarEntry>  $window  every slot of one counselor this person may see inside the booking window
     *                                                  (loaded with `has_active_booking` and `location`)
     * @param  string|null  $from  Y-m-d; today or later (earlier is moved to today)
     * @param  string|null  $mode  online|physical
     * @return array{booking_window: array<string, mixed>, modes_offered: list<string>, days: list<array<string, mixed>>, next_from: ?string}
     */
    public function present(Collection $window, ?string $from, int $days, ?string $mode): array
    {
        $now = $this->now();
        $today = $now->startOfDay();
        $last = $this->lastDate($now);

        $booked = $window->filter(fn (CalendarEntry $slot) => $this->isBooked($slot));
        $free = $this->withoutConflicts($window->reject(fn (CalendarEntry $slot) => $this->isBooked($slot)));
        $visible = $booked->concat($free)->sortBy([
            fn ($a, $b) => $a->entry_date->format('Y-m-d') <=> $b->entry_date->format('Y-m-d'),
            fn ($a, $b) => $a->start_time <=> $b->start_time,
            fn ($a, $b) => $a->id <=> $b->id,
        ])->values();

        $start = $from !== null ? CarbonImmutable::createFromFormat('!Y-m-d', $from, $this->timezone()) : $today;
        $start = $start->lt($today) ? $today : $start;
        $end = $start->addDays(max(1, $days) - 1);
        $end = $end->gt($last) ? $last : $end;

        $dayList = [];

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $slots = $visible
                ->filter(fn (CalendarEntry $slot) => $slot->entry_date->format('Y-m-d') === $day->format('Y-m-d'))
                ->filter(fn (CalendarEntry $slot) => $mode === null || $this->modeOf($slot) === $mode)
                ->map(fn (CalendarEntry $slot) => $this->slotPayload($slot))
                ->values();

            $dayList[] = [
                'date' => $day->format('Y-m-d'),
                'counts' => [
                    SessionMode::Online->value => $slots->where('state', self::AVAILABLE)->where('mode', SessionMode::Online->value)->count(),
                    SessionMode::Physical->value => $slots->where('state', self::AVAILABLE)->where('mode', SessionMode::Physical->value)->count(),
                ],
                'slots' => $slots->all(),
            ];
        }

        return [
            'booking_window' => [
                'earliest_start' => $this->earliestStart($now)->toIso8601String(),
                'last_date' => $last->format('Y-m-d'),
                'min_lead_hours' => (int) config('booking.min_lead_hours'),
            ],
            'modes_offered' => $visible->map(fn (CalendarEntry $slot) => $this->modeOf($slot))->unique()->sort()->values()->all(),
            'days' => $start->gt($last) ? [] : $dayList,
            'next_from' => ! $start->gt($last) && $end->lt($last) ? $end->addDay()->format('Y-m-d') : null,
        ];
    }

    // =============================================================================================================
    //  Internals
    // =============================================================================================================

    /** A slot with no mode is an online slot. */
    private function modeOf(CalendarEntry $slot): string
    {
        return ($slot->session_mode ?? SessionMode::Online)->value;
    }

    /**
     * One slot as the app sees it: time, length, mode, whether it can still be taken, and for an in-person slot the place's name and city
     * only. Never the street, a meeting link, a client or a booking.
     *
     * @return array<string, mixed>
     */
    private function slotPayload(CalendarEntry $slot): array
    {
        $mode = $this->modeOf($slot);

        return [
            'id' => $slot->public_id,
            'starts_at' => $this->startOf($slot)->toIso8601String(),
            'duration_minutes' => (int) $slot->duration_minutes,
            'mode' => $mode,
            'state' => $this->isBooked($slot) ? self::BOOKED : self::AVAILABLE,
            'location' => $mode === SessionMode::Physical->value && $slot->location !== null
                ? ['name' => (string) $slot->location->name, 'city' => (string) $slot->location->city]
                : null,
        ];
    }

    private function startOf(CalendarEntry $entry): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i:s', $entry->entry_date->format('Y-m-d').' '.$entry->start_time, $this->timezone());
    }

    /**
     * What is on these counselors' calendars between two days, as time stretches that already include the counselor's buffer on BOTH ends:
     * every personal block and every booked slot, whatever its context. ONE query for the entries and one for the buffers.
     *
     * @param  list<int>  $counselorUserIds
     * @return array<int, list<array{0: int, 1: int, 2: int}>> counselor user id => [start timestamp, end timestamp, entry id]
     */
    private function busy(array $counselorUserIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $buffers = CounselorProfile::query()->whereIn('user_id', $counselorUserIds)->pluck('buffer_minutes', 'user_id');

        $entries = CalendarEntry::query()
            ->whereIn('counselor_user_id', $counselorUserIds)
            // whereDate: the date column is stored with a time part on SQLite, so a plain comparison would miss the last day.
            ->whereDate('entry_date', '>=', $from->format('Y-m-d'))
            ->whereDate('entry_date', '<=', $to->format('Y-m-d'))
            ->where(fn ($query) => $query
                ->where('entry_type', CalendarEntryType::PersonalBlock->value)
                ->orWhere(fn ($slot) => $slot->where('entry_type', CalendarEntryType::AvailableSlot->value)
                    ->where(fn ($booked) => $booked
                        ->where('is_available', false)
                        ->orWhereExists(fn ($booking) => $booking->select(DB::raw(1))->from('bookings')
                            ->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')
                            ->where('bookings.status', '!=', 'cancelled')))))
            ->get(['id', 'counselor_user_id', 'entry_date', 'start_time', 'duration_minutes']);

        $busy = [];

        foreach ($entries as $entry) {
            $buffer = (int) ($buffers[$entry->counselor_user_id] ?? 0) * 60;
            $start = $this->startOf($entry);

            $busy[$entry->counselor_user_id][] = [
                $start->getTimestamp() - $buffer,
                $start->addMinutes((int) $entry->duration_minutes)->getTimestamp() + $buffer,
                $entry->id,
            ];
        }

        return $busy;
    }

    /**
     * Does [start, end) overlap any busy stretch? Touching exactly at the edge does not count as overlapping.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $busy
     */
    private function clashes(int $start, int $end, array $busy, ?int $exceptEntryId): bool
    {
        foreach ($busy as [$busyStart, $busyEnd, $entryId]) {
            if ($entryId !== $exceptEntryId && $start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }

        return false;
    }
}
