<?php

namespace App\Services;

use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
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
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $place = $this->planner->placement($counselor, (int) $input['context'], SessionMode::from($input['mode']), isset($input['location']) ? (int) $input['location'] : null);

            $existing = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->get(['weekday', 'start_time', 'end_time']);

            if ($existing->count() >= self::MAX_LINES) {
                throw new BookingRefused('weekly_limit');
            }

            foreach ($existing->where('weekday', (int) $input['weekday']) as $line) {
                if ($from < $this->minutes((string) $line->end_time) && $to > $this->minutes((string) $line->start_time)) {
                    throw new BookingRefused('weekly_overlap');
                }
            }

            return CounselorWeeklyHour::create([
                'counselor_user_id' => $counselor->getKey(),
                'context_org_id' => $place['context'],
                'weekday' => (int) $input['weekday'],
                'start_time' => sprintf('%02d:%02d:00', intdiv($from, 60), $from % 60),
                'end_time' => sprintf('%02d:%02d:00', intdiv($to, 60), $to % 60),
                'slot_minutes' => $duration,
                'session_mode' => SessionMode::from($input['mode']),
                'location_id' => $place['location'],
            ]);
        });
    }

    /** @throws BookingRefused planner_not_found */
    public function remove(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $deleted = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->where('public_id', strtolower($publicId))->delete();

            if ($deleted !== 1) {
                throw new BookingRefused('planner_not_found');
            }
        });
    }

    /**
     * Opens the times of every line for the next [weeks] weeks (today included). Whatever does not fit (already open, in the past, clashes
     * with a booking or a block, a place that is gone) is skipped and counted.
     *
     * @return array{created: int, skipped: int}
     */
    public function fill(User $counselor, int $weeks): array
    {
        $weeks = max(1, min(self::MAX_WEEKS, $weeks));
        $today = $this->availability->now()->startOfDay();
        $created = 0;
        $skipped = 0;

        $lines = CounselorWeeklyHour::query()->where('counselor_user_id', $counselor->getKey())->orderBy('weekday')->orderBy('start_time')->get();

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
                        ]);
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

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}
