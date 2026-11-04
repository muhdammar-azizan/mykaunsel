<?php

namespace App\Services;

use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Facades\Audit;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A counselor's weekly hours (docs/AVAILABILITY.md, "Weekly hours"): "every Monday 09:00 to 12:00, one-hour times, online, for this
 * organization". A PATTERN only: nothing is bookable until the counselor asks to fill the next weeks, and filling just opens ordinary times
 * through CounselorPlanner::open, so every rule of the planner (past, buffer, overlap, place) applies to each one and one that does not fit is
 * simply skipped. Filling again opens nothing twice: what is already open now overlaps itself and is skipped.
 *
 * - Lines of one counselor may not overlap on the same weekday (any context): the pattern says one thing about a given hour.
 * - Removing a line leaves the times it already opened alone (remove those in the planner).
 * - Same lock as booking (the counselor's profile row) when a line is added or removed.
 */
class CounselorWeeklyHours
{
    public const MAX_LINES = 30;

    public const MAX_WEEKS = 8;

    /** The most times one "open the next weeks" request may try to open (counted before skip rules, checked before anything is written). */
    public const MAX_FILL = 200;

    public function __construct(private readonly CounselorPlanner $planner, private readonly AvailabilityService $availability) {}

    /** @return list<array<string, mixed>> */
    public function lines(User $counselor): array
    {
        return CounselorWeeklyHour::query()
            ->where('counselor_user_id', $counselor->getKey())->with(['contextOrg:id,name', 'location:id,name,city'])
            ->orderBy('weekday')->orderBy('start_time')->get()
            ->map(fn (CounselorWeeklyHour $line) => [
                'id' => $line->public_id,
                'weekday' => $line->weekday,
                'weekday_label' => CarbonImmutable::parse('2026-11-02')->addDays($line->weekday - 1)->format('l'),
                'window' => substr((string) $line->start_time, 0, 5).' – '.substr((string) $line->end_time, 0, 5),
                'minutes' => $line->slot_minutes,
                'mode' => $line->session_mode->value,
                'place' => $line->location === null ? null : $line->location->name.', '.$line->location->city,
                'context' => (string) $line->contextOrg?->name,
            ])->all();
    }

    /**
     * @param  array{context: int, weekday: int, from: string, to: string, duration: int, mode: string, location: int|null}  $input
     *
     * @throws BookingRefused
     */
    public function add(User $counselor, array $input): CounselorWeeklyHour
    {
        $from = $this->minutes($input['from']);
        $to = $this->minutes($input['to']);
        $duration = (int) $input['duration'];

        if ($from % 5 !== 0 || $to % 5 !== 0 || $to <= $from || $to - $from < $duration
            || ! in_array($duration, CounselorPlanner::durations(), true) || $input['weekday'] < 1 || $input['weekday'] > 7) {
            throw new BookingRefused('weekly_time_invalid');
        }

        return DB::transaction(function () use ($counselor, $input, $from, $to, $duration) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $place =$this->planner->placement($counselor, (int) $input['context'], SessionMode::from($input['mode']), isset($input['location']) ? (int) $input['location'] : null);

            $existing = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->get(['weekday', 'start_time', 'end_time']);

            if ($existing->count() >= self::MAX_LINES) {
                throw new BookingRefused('weekly_limit');
            }

            foreach ($existing->where('weekday', (int) $input['weekday']) as $line) {
                if ($from < $this->minutes((string) $line->end_time) && $to > $this->minutes((string) $line->start_time)) {
                    throw new BookingRefused('weekly_overlap');
                }
            }

            $line = CounselorWeeklyHour::create([
                'counselor_user_id' => $counselor->getKey(),
                'context_org_id' => $place['context'],
                'weekday' => (int) $input['weekday'],
                'start_time' => sprintf('%02d:%02d:00', intdiv($from, 60), $from % 60),
                'end_time' => sprintf('%02d:%02d:00', intdiv($to, 60), $to % 60),
                'slot_minutes' => $duration,
                'session_mode' => SessionMode::from($input['mode']),
                'location_id' => $place['location'],
            ]);

            Audit::log('calendar.hours_line_added', $profile, [
                'counselor_id' => $profile?->getKey(), 'context_org_id' => $place['context'], 'weekday' => (int) $input['weekday'],
                'from' => substr((string) $line->start_time, 0, 5), 'to' => substr((string) $line->end_time, 0, 5),
                'minutes' => $duration, 'mode' => $input['mode'],
            ], $counselor);

            return $line;
        });
    }

    /** @throws BookingRefused planner_not_found */
    public function remove(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $line = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->first();

            if ($line === null || $line->delete() !== true) {
                throw new BookingRefused('planner_not_found');
            }

            Audit::log('calendar.hours_line_removed', $profile, [
                'counselor_id' => $profile?->getKey(), 'context_org_id' => $line->context_org_id, 'weekday' => (int) $line->weekday,
                'from' => substr((string) $line->start_time, 0, 5), 'to' => substr((string) $line->end_time, 0, 5),
            ], $counselor);
        });
    }

    /**
     * Opens the times of every line for the next [weeks] weeks (today included). Whatever does not fit (already open, in the past, clashes
     * with a booking or a block, a place that is gone) is skipped and counted.
     *
     * If the request would try more than MAX_FILL times, the WHOLE request is refused before anything is written (never cut short silently).
     *
     * @return array{created: int, skipped: int}
     *
     * @throws BookingRefused weekly_fill_too_many
     */
    public function fill(User $counselor, int $weeks): array
    {
        $weeks = max(1, min(self::MAX_WEEKS, $weeks));
        $today = $this->availability->now()->startOfDay();
        $created = 0;
        $skipped = 0;

        $lines = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->orderBy('weekday')->orderBy('start_time')->get();

        if ($this->candidates($lines, $today, $weeks) > self::MAX_FILL) {
            throw new BookingRefused('weekly_fill_too_many');
        }

        // One transaction for the whole fill: the times and the single summary audit entry are kept together or not at all.
        DB::transaction(function () use ($counselor, $lines, $today, $weeks, &$created, &$skipped) {
            for ($day = 0; $day < $weeks * 7; $day++) {
                $date = $today->addDays($day);

                foreach ($lines->where('weekday', $date->isoWeekday()) as $line) {
                    $start = $this->minutes((string) $line->start_time);
                    $end = $this->minutes((string) $line->end_time);

                    for ($at = $start; $at + $line->slot_minutes <= $end; $at += $line->slot_minutes) {
                        try {
                            $this->planner->open($counselor, [
                                'context' => $line->context_org_id,
                                'date' => $date->format('Y-m-d'),
                                'start' => sprintf('%02d:%02d', intdiv($at, 60), $at % 60),
                                'duration' => $line->slot_minutes,
                                'mode' => $line->session_mode->value,
                                'location' => $line->location_id,
                            ], false);
                            $created++;
                        } catch (BookingRefused $refused) {
                            // A time already gone by today is not "skipped": it was never on offer.
                            if ($refused->reason !== 'planner_in_past') {
                                $skipped++;
                            }
                        }
                    }
                }
            }

            // One summary: how many, which dates, which contexts. No client names, no list of time ids.
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->first();

            Audit::log('calendar.weeks_opened', $profile, [
                'counselor_id' => $profile?->getKey(), 'weeks' => $weeks, 'created' => $created, 'skipped' => $skipped,
                'from' => $today->format('Y-m-d'), 'to' => $today->addDays($weeks * 7 - 1)->format('Y-m-d'),
                'context_org_ids' => $lines->pluck('context_org_id')->unique()->values()->all(),
            ], $counselor);
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** How many times a fill of [weeks] weeks would try to open, before any skip rule. */
    private function candidates(\Illuminate\Support\Collection $lines, CarbonImmutable $today, int $weeks): int
    {
        $total = 0;

        for ($day = 0; $day < $weeks * 7; $day++) {
            foreach ($lines->where('weekday', $today->addDays($day)->isoWeekday()) as $line) {
                $total += intdiv($this->minutes((string) $line->end_time) - $this->minutes((string) $line->start_time), $line->slot_minutes);
            }
        }

        return $total;
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
