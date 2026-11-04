<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A counselor's blocked times: leave, holidays, a private appointment (docs/AVAILABILITY.md, "Blocked times").
 *
 * - A block is a calendar entry of type `personal_block`. AvailabilityService already treats it as busy everywhere, so a time open for booking
 *   that falls inside it can no longer be booked (it stays in the schedule and becomes bookable again when the block is removed).
 * - It can not be put over a session that is already booked (a confirmed booking): cancel that session first. The counselor is told which one.
 * - Whole days (00:00 for 24 hours) or a stretch of the day, for one day or a range of days (at most MAX_DAYS in one go).
 * - The title is the counselor's own note: nobody else sees it, and a client only ever sees the time as not available.
 */
class CounselorBlocks
{
    public const MAX_DAYS = 60;

    public const MAX_BLOCKS = 400;

    public function __construct(private readonly AvailabilityService $availability) {}

    /** Blocks from today on, soonest first. @return list<array<string, mixed>> */
    public function upcoming(User $counselor): array
    {
        $today = $this->availability->now()->format('Y-m-d');

        return CalendarEntry::query()
            ->where('counselor_user_id', $counselor->getKey())->where('entry_type', CalendarEntryType::PersonalBlock->value)
            ->whereDate('entry_date', '>=', $today)
            ->orderBy('entry_date')->orderBy('start_time')->limit(self::MAX_BLOCKS)->get()
            ->map(function (CalendarEntry $entry) {
                $start = $this->availability->startOf($entry);
                $allDay = (int) $entry->duration_minutes >= 1440;

                return [
                    'id' => $entry->public_id,
                    'day_label' => $start->format('D, j M Y'),
                    'time' => $allDay ? 'All day' : $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
                    'title' => (string) $entry->title,
                ];
            })->all();
    }

    /**
     * @param  array{from: string, to?: string|null, all_day?: bool, start?: string|null, end?: string|null, title?: string|null}  $input
     * @return int how many days were blocked
     *
     * @throws BookingRefused block_invalid | planner_in_past | block_has_session | block_limit
     */
    public function add(User $counselor, array $input): int
    {
        $tz = $this->availability->timezone();
        $now = $this->availability->now();
        $first = CarbonImmutable::createFromFormat('Y-m-d', $input['from'], $tz)->startOfDay();
        $last = CarbonImmutable::createFromFormat('Y-m-d', $input['to'] ?? $input['from'], $tz)->startOfDay();
        $allDay = (bool) ($input['all_day'] ?? false);

        if ($last->lt($first) || $first->diffInDays($last) >= self::MAX_DAYS) {
            throw new BookingRefused('block_invalid');
        }

        if ($first->lt($now->startOfDay())) {
            throw new BookingRefused('planner_in_past');
        }

        [$startTime, $minutes] = [null, 1440];

        if (! $allDay) {
            $start = $input['start'] ?? null;
            $end = $input['end'] ?? null;

            if ($start === null || $end === null || $end <= $start || (int) substr($start, 3, 2) % 5 !== 0 || (int) substr($end, 3, 2) % 5 !== 0) {
                throw new BookingRefused('block_invalid');
            }

            $startTime = $start.':00';
            $minutes = (int) ((strtotime("1970-01-01 {$end}:00") - strtotime("1970-01-01 {$start}:00")) / 60);
        }

        $days = [];

        for ($day = $first; $day->lte($last); $day = $day->addDay()) {
            $days[] = $day;
        }

        return DB::transaction(function () use ($counselor, $days, $startTime, $minutes, $input, $tz, $allDay) {
            // The lock booking and opening a time take: no session can be booked into the range while it is checked.
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            if (CalendarEntry::query()->where('counselor_user_id', $counselor->getKey())->where('entry_type', CalendarEntryType::PersonalBlock->value)
                ->whereDate('entry_date', '>=', $this->availability->now()->format('Y-m-d'))->count() + count($days) > self::MAX_BLOCKS) {
                throw new BookingRefused('block_limit');
            }

            foreach ($days as $day) {
                $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $day->format('Y-m-d').' '.($startTime ?? '00:00:00'), $tz);

                if ($this->sessionIn($counselor, $start, $minutes)) {
                    throw new BookingRefused('block_has_session');
                }
            }

            foreach ($days as $day) {
                CalendarEntry::create([
                    'counselor_user_id' => $counselor->getKey(),
                    'entry_type' => CalendarEntryType::PersonalBlock,
                    'title' => ($title = trim((string) ($input['title'] ?? ''))) === '' ? null : mb_substr($title, 0, 100),
                    'entry_date' => $day->format('Y-m-d'),
                    'start_time' => $startTime ?? '00:00:00',
                    'duration_minutes' => $minutes,
                    'is_available' => true,
                ]);
            }

            // One entry for the whole range, in the same transaction. The counselor's own title is never recorded.
            Audit::log('calendar.block_added', $profile, [
                'counselor_id' => $profile?->getKey(), 'from' => $days[0]->format('Y-m-d'), 'to' => end($days)->format('Y-m-d'), 'days' => count($days),
                'all_day' => $allDay, 'start' => $allDay ? null : substr((string) $startTime, 0, 5), 'minutes' => $minutes,
            ], $counselor);

            return count($days);
        });
    }

    /** @throws BookingRefused block_not_found */
    public function remove(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $entry = CalendarEntry::query()
                ->where('counselor_user_id', $counselor->getKey())->where('entry_type', CalendarEntryType::PersonalBlock->value)
                ->where('public_id', strtolower($publicId))->first();

            if ($entry === null) {
                throw new BookingRefused('block_not_found');
            }

            $entry->delete();

            Audit::log('calendar.block_removed', $profile, [
                'counselor_id' => $profile?->getKey(), 'date' => $entry->entry_date->format('Y-m-d'),
                'all_day' => (int) $entry->duration_minutes >= 1440, 'minutes' => (int) $entry->duration_minutes,
            ], $counselor);
        });
    }

    /** Is a confirmed session of this counselor inside [start, start + minutes)? */
    private function sessionIn(User $counselor, CarbonImmutable $start, int $minutes): bool
    {
        $end = $start->addMinutes($minutes);

        $booked = CalendarEntry::query()
            ->where('counselor_user_id', $counselor->getKey())
            ->whereDate('entry_date', '>=', $start->subDay()->format('Y-m-d'))
            ->whereDate('entry_date', '<=', $end->format('Y-m-d'))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('bookings')->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')
                ->where('bookings.status', BookingStatus::Confirmed->value))
            ->get();

        foreach ($booked as $entry) {
            $from = $this->availability->startOf($entry);

            if ($from->lt($end) && $from->addMinutes((int) $entry->duration_minutes)->gt($start)) {
                return true;
            }
        }

        return false;
    }
}
