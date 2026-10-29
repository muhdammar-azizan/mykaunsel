<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Support\AnalyticsRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place that computes every number on the Platform Admin Analytics page (and the figures the Dashboard's Platform Stats widget
 * will use). Everything is worked out from the live tables: there is no statistics table that can go stale.
 *
 * AGGREGATES ONLY. The result holds counts, percentages and labels such as "University"; never a name, an email, a KB number or the id
 * of a person or an organization. No chat, complaint or session-note text is read at all.
 *
 * DEFINITIONS (also in docs/ANALYTICS.md)
 * - An APPROVED organization is one that is active or suspended (it was approved; "suspended" is how many of them are, currently). The
 *   virtual platform organization is never counted. It counts as of a moment when its approval date (reviewed_at) is on or before it.
 *   An approved organization with NO approval date is counted at every moment (both periods, so the trend is not affected); the number
 *   of those is reported. No date is invented.
 * - An APPROVED counselor is one who is approved or suspended, counted the same way (organization counselors have no approval flow,
 *   so they have no date and fall under the "no date" rule).
 * - A SESSION happens at calendar_entries.entry_date + start_time, which are Malaysian wall-clock times (counselors.directory.slot_timezone).
 *   The instant is worked out in that IANA zone. Only bookings with a calendar entry have a date; the others are left out and counted.
 *   Only the explicit statuses completed and no_show are counted: a session is NEVER assumed completed because its time has passed.
 * - NO-SHOW RATE = no_show / (completed + no_show); cancelled sessions are not in it. No sessions: null (never divided by zero).
 * - TREND = percentage change against the previous period of equal length; null when the previous value is 0 or unknown.
 * - Everything the database does is portable: plain comparisons and counts. Days, weeks and months are worked out in PHP.
 */
class AnalyticsService
{
    /** @var list<string> */
    private const OUTCOME_STATUSES = [BookingStatus::Completed->value, BookingStatus::NoShow->value, BookingStatus::Cancelled->value];

    /** What a booking's organization means for the page. */
    public const TYPE_LABELS = [
        'university' => 'University',
        'corporate' => 'Corporate',
        'clinic' => 'Clinic',
        'independent' => 'Independent',
        'none' => 'No organization recorded',
    ];

    // =============================================================================================================
    //  The whole report
    // =============================================================================================================

    /**
     * @return array<string, mixed>
     */
    public function report(AnalyticsRange $range): array
    {
        $previous = $range->previous();

        $orgs = $this->approvedOrganizations();
        $counselors = $this->approvedCounselors();
        $sessions = $this->sessionRows($previous->from, $range->to);

        $current = $this->sessionsIn($sessions, $range);
        $before = $this->sessionsIn($sessions, $previous);

        return [
            'range' => [
                'from' => $range->from->toIso8601String(),
                'to' => $range->to->toIso8601String(),
                'timezone' => $range->timezone,
                'days' => $range->days(),
                'bucket' => $range->bucket(),
                'previous_from' => $previous->from->toIso8601String(),
                'previous_to' => $previous->to->toIso8601String(),
            ],
            'cards' => [
                'organizations' => $this->organizationsCard($orgs, $range, $previous),
                'counselors' => $this->counselorsCard($counselors, $range, $previous),
                'sessions_completed' => $this->sessionsCard($current, $before),
                'no_show_rate' => $this->noShowCard($current, $before),
            ],
            'growth' => $this->growth($orgs, $counselors, $range),
            'sessions_by_type' => $this->sessionsByType($current),
            'specializations' => [
                'sessions' => $this->specializationsBySessions($current),
                'offered' => $this->specializationsOffered(),
            ],
            'outcomes' => [
                'organizations' => $this->organizationOutcomes($range),
                'counselors' => $this->counselorOutcomes($range),
            ],
            'notes' => [
                'small_number_threshold' => $this->threshold(),
                // Completed or no-show bookings that have no calendar entry, so no date: they cannot be placed in any range.
                'sessions_without_date' => DB::table('bookings')->whereNull('calendar_entry_id')->whereIn('status', [BookingStatus::Completed->value, BookingStatus::NoShow->value])->count(),
                // Has any session ever been marked completed or no-show (with a date)? false = "No session data yet".
                'any_session_outcomes' => $this->anySessionOutcomes(),
            ],
        ];
    }

    // =============================================================================================================
    //  For the Dashboard's Platform Stats widget (it is not connected yet; it must call these, not count by itself)
    // =============================================================================================================

    /** Approved organizations (active + suspended, the platform organization excluded) as of a moment (default: now). */
    public function totalOrganizations(?CarbonInterface $asOf = null): int
    {
        return $this->countAsOf($this->approvedOrganizations(), $asOf ?? now());
    }

    /** Approved counselors (approved + suspended, independent and organization) as of a moment (default: now). */
    public function totalCounselors(?CarbonInterface $asOf = null): int
    {
        return $this->countAsOf($this->approvedCounselors(), $asOf ?? now());
    }

    /**
     * Active members: DISTINCT PEOPLE (a user who belongs to two organizations counts once) with an active student, staff or employee
     * membership in a real organization (the virtual platform organization is left out).
     */
    public function activeMembers(): int
    {
        return Membership::query()
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', [MembershipRole::Student->value, MembershipRole::Staff->value, MembershipRole::Employee->value])
            ->whereIn('org_id', Organization::query()->where('org_type', '!=', OrgType::Platform->value)->select('id'))
            ->distinct()
            ->count('user_id');
    }

    /** Has any session ever been marked completed or no-show (with a session date)? false = the page says "No session data yet". */
    public function anySessionOutcomes(): bool
    {
        return DB::table('bookings')->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->whereIn('bookings.status', [BookingStatus::Completed->value, BookingStatus::NoShow->value])->exists();
    }

    /** Completed sessions this calendar month IN THE GIVEN TIMEZONE, counted by SESSION date (not by when the booking was made). */
    public function completedSessionsThisMonth(string $timezone, ?CarbonInterface $now = null): int
    {
        $today = CarbonImmutable::instance($now ?? now())->setTimezone($timezone);
        $range = new AnalyticsRange($today->startOfMonth(), $today->endOfMonth(), $timezone);

        return $this->sessionsIn($this->sessionRows($range->from, $range->to), $range)->where('status', BookingStatus::Completed->value)->count();
    }

    // =============================================================================================================
    //  Cards
    // =============================================================================================================

    private function organizationsCard(array $orgs, AnalyticsRange $range, AnalyticsRange $previous): array
    {
        $value = $this->countAsOf($orgs, $range->to);
        $prior = $this->countAsOf($orgs, $previous->to);

        return [
            'value' => $value,
            'previous' => $prior,
            'trend' => $this->trend($value, $prior),
            // Suspended is how many of the counted organizations are suspended RIGHT NOW (history of suspensions is not kept).
            'suspended' => count(array_filter($this->countedAsOf($orgs, $range->to), fn (array $r) => $r['suspended'])),
            'undated' => count(array_filter($orgs, fn (array $r) => $r['at'] === null)),
        ];
    }

    private function counselorsCard(array $counselors, AnalyticsRange $range, AnalyticsRange $previous): array
    {
        $counted = $this->countedAsOf($counselors, $range->to);
        $value = count($counted);
        $prior = $this->countAsOf($counselors, $previous->to);

        return [
            'value' => $value,
            'previous' => $prior,
            'trend' => $this->trend($value, $prior),
            'independent' => count(array_filter($counted, fn (array $r) => $r['independent'])),
            'organization' => count(array_filter($counted, fn (array $r) => ! $r['independent'])),
            'suspended' => count(array_filter($counted, fn (array $r) => $r['suspended'])),
            'undated' => count(array_filter($counselors, fn (array $r) => $r['at'] === null)),
        ];
    }

    private function sessionsCard($current, $before): array
    {
        $value = $current->where('status', BookingStatus::Completed->value)->count();
        $prior = $before->where('status', BookingStatus::Completed->value)->count();

        return ['value' => $value, 'previous' => $prior, 'trend' => $this->trend($value, $prior)];
    }

    private function noShowCard($current, $before): array
    {
        [$rate, $completed, $noShow] = $this->noShow($current);
        [$priorRate] = $this->noShow($before);

        return [
            'value' => $rate,
            'previous' => $priorRate,
            'trend' => $this->trend($rate, $priorRate),
            'completed' => $completed,
            'no_show' => $noShow,
            // Mentioned in the tooltip: cancelled sessions are not part of the rate.
            'cancelled_excluded' => $current->where('status', BookingStatus::Cancelled->value)->count(),
            'has_data' => $completed + $noShow > 0,
        ];
    }

    /** @return array{0: ?float, 1: int, 2: int} rate in percent (one decimal) or null, completed, no-show */
    private function noShow($rows): array
    {
        $completed = $rows->where('status', BookingStatus::Completed->value)->count();
        $noShow = $rows->where('status', BookingStatus::NoShow->value)->count();
        $total = $completed + $noShow;

        return [$total === 0 ? null : round($noShow / $total * 100, 1), $completed, $noShow];
    }

    /**
     * @return array{percent: ?float, direction: ?string} percent is null (shown as "—") when the previous value is 0 or unknown
     */
    private function trend(int|float|null $now, int|float|null $before): array
    {
        if ($now === null || $before === null || $before == 0) {
            return ['percent' => null, 'direction' => null];
        }

        $percent = round(($now - $before) / $before * 100, 1);

        return ['percent' => $percent, 'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat')];
    }

    // =============================================================================================================
    //  Growth chart (cumulative, by approval date)
    // =============================================================================================================

    private function growth(array $orgs, array $counselors, AnalyticsRange $range): array
    {
        $points = [];

        foreach ($range->buckets() as $bucket) {
            $points[] = [
                'label' => $bucket['label'],
                'start' => $bucket['start']->toIso8601String(),
                'end' => $bucket['end']->toIso8601String(),
                'organizations' => $this->countAsOf($orgs, $bucket['end']),
                'counselors' => $this->countAsOf($counselors, $bucket['end']),
            ];
        }

        return [
            'points' => $points,
            // Both lines at zero all the way: the page shows the empty state instead of a chart.
            'empty' => collect($points)->every(fn (array $p) => $p['organizations'] === 0 && $p['counselors'] === 0),
            'undated' => [
                'organizations' => count(array_filter($orgs, fn (array $r) => $r['at'] === null)),
                'counselors' => count(array_filter($counselors, fn (array $r) => $r['at'] === null)),
            ],
        ];
    }

    // =============================================================================================================
    //  Sessions by organization type
    // =============================================================================================================

    private function sessionsByType($current): array
    {
        $completed = $current->where('status', BookingStatus::Completed->value);
        $counts = [];

        foreach (array_keys(self::TYPE_LABELS) as $key) {
            $counts[$key] = $completed->where('type', $key)->count();
        }

        $items = [];

        foreach (self::TYPE_LABELS as $key => $label) {
            // "No organization recorded" is shown only when there is something to show.
            if ($key === 'none' && $counts[$key] === 0) {
                continue;
            }

            $items[] = ['key' => $key, 'label' => $label] + $this->shown($counts[$key]);
        }

        return ['items' => $items, 'has_data' => $completed->isNotEmpty()];
    }

    /**
     * How one count is shown: a number, or "<5" for 1 to 4 (0 is shown as 0: it identifies nobody). A suppressed count carries NO number
     * at all, so nothing downstream (page, table view, export) can print it by mistake.
     *
     * @return array{count: ?int, display: string, suppressed: bool}
     */
    private function shown(int $count): array
    {
        $threshold = $this->threshold();

        if ($count > 0 && $count < $threshold) {
            return ['count' => null, 'display' => '<'.$threshold, 'suppressed' => true];
        }

        return ['count' => $count, 'display' => number_format($count), 'suppressed' => false];
    }

    // =============================================================================================================
    //  Specializations
    // =============================================================================================================

    /** A completed session counts ONCE for each (distinct) specialization of the counselor who delivered it. */
    private function specializationsBySessions($current): array
    {
        $completed = $current->where('status', BookingStatus::Completed->value);
        $bySpecialization = $this->specializationsOf($completed->pluck('counselor')->unique()->filter()->values()->all());

        $counts = [];
        $variants = [];

        foreach ($bySpecialization as $rows) {
            foreach ($rows as $key => $variantList) {
                foreach ($variantList as $variant) {
                    $variants[$key][$variant] = ($variants[$key][$variant] ?? 0) + 1;
                }
            }
        }

        foreach ($completed as $session) {
            foreach (array_keys($bySpecialization[$session['counselor']] ?? []) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return ['items' => $this->specializationRows($counts, $variants), 'has_data' => $completed->isNotEmpty()];
    }

    /** How many APPROVED counselors list each specialization (counselors, not sessions). Available before any session exists. */
    private function specializationsOffered(): array
    {
        $ids = DB::table('counselor_profiles')->where('verification_status', VerificationStatus::Approved->value)->pluck('user_id')->all();
        $bySpecialization = $this->specializationsOf($ids);

        $counts = [];
        $variants = [];

        foreach ($bySpecialization as $rows) {
            foreach ($rows as $key => $variantList) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;

                foreach ($variantList as $variant) {
                    $variants[$key][$variant] = ($variants[$key][$variant] ?? 0) + 1;
                }
            }
        }

        return ['items' => $this->specializationRows($counts, $variants), 'has_data' => $counts !== []];
    }

    /**
     * counselor user id => [normalised specialization => [the spellings that counselor wrote]]. Case and spacing are ignored when
     * deciding whether two are the same ("Stres  &  Kebimbangan" and "stres & kebimbangan"); nothing else is merged (no synonyms).
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, list<string>>>
     */
    private function specializationsOf(array $userIds): array
    {
        $out = [];

        foreach (array_chunk(array_values(array_unique($userIds)), 500) as $chunk) {
            $rows = DB::table('counselor_specializations')
                ->join('counselor_profiles', 'counselor_profiles.id', '=', 'counselor_specializations.counselor_profile_id')
                ->whereIn('counselor_profiles.user_id', $chunk)
                ->get(['counselor_profiles.user_id', 'counselor_specializations.specialization']);

            foreach ($rows as $row) {
                $spelling = trim((string) preg_replace('/\s+/u', ' ', (string) $row->specialization));

                if ($spelling === '') {
                    continue;
                }

                $out[(int) $row->user_id][mb_strtolower($spelling)][] = $spelling;
            }
        }

        return $out;
    }

    /**
     * The rows of one specialization view: biggest first (ties by label), each with its display label, then "Other (fewer than N)" for
     * everything under the threshold. The label of a merged group is its most common spelling, ties broken by the alphabet, so it never
     * changes between page loads.
     *
     * @param  array<string, int>  $counts  normalised key => count
     * @param  array<string, array<string, int>>  $variants  normalised key => spelling => how often it was written
     * @return list<array{label: string, count: ?int, display: string, suppressed: bool, other: bool}>
     */
    private function specializationRows(array $counts, array $variants): array
    {
        $threshold = $this->threshold();
        $shown = [];
        $other = 0;
        $hidden = 0;

        foreach ($counts as $key => $count) {
            if ($count >= $threshold) {
                $shown[] = ['label' => $this->labelFor($variants[$key] ?? [$key => 1]), 'count' => $count];
            } else {
                $other += $count;
                $hidden++;
            }
        }

        usort($shown, fn (array $a, array $b) => [$b['count'], $a['label']] <=> [$a['count'], $b['label']] ?: strcmp($a['label'], $b['label']));

        $rows = array_map(fn (array $row) => ['label' => $row['label'], 'other' => false] + $this->shown($row['count']), $shown);

        if ($hidden > 0) {
            // The total of the small groups is hidden too when it is itself small, otherwise it would give away a single group's count.
            $rows[] = ['label' => "Other (fewer than {$threshold})", 'other' => true] + $this->shown($other);
        }

        return $rows;
    }

    /** @param  array<string, int>  $variants */
    private function labelFor(array $variants): string
    {
        $labels = array_keys($variants);
        usort($labels, fn (string $a, string $b) => [$variants[$b], $a] <=> [$variants[$a], $b] ?: strcmp($a, $b));

        return $labels[0];
    }

    // =============================================================================================================
    //  Application outcomes (a cohort: applications SUBMITTED in the range)
    // =============================================================================================================

    private function organizationOutcomes(AnalyticsRange $range): array
    {
        $cohort = Organization::query()
            ->where('org_type', '!=', OrgType::Platform->value)
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [AnalyticsRange::utc($range->from), AnalyticsRange::utc($range->to)])
            ->get(['subscription_status']);

        $count = fn (array $statuses) => $cohort->filter(fn ($o) => in_array($o->subscription_status->value, $statuses, true))->count();

        $decisions = Organization::query()
            ->where('org_type', '!=', OrgType::Platform->value)
            ->whereNotNull('submitted_at')
            ->whereBetween('reviewed_at', [AnalyticsRange::utc($range->from), AnalyticsRange::utc($range->to)])
            ->get(['submitted_at', 'reviewed_at']);

        return $this->outcomeBlock(
            $cohort->count(),
            $count([OrgStatus::Active->value, OrgStatus::Suspended->value]),
            $count([OrgStatus::Rejected->value]),
            $count([OrgStatus::Pending->value]),
            $decisions,
            // The sign-up flow for a rejected organization to apply again does not exist yet, so nothing counts this: "not tracked".
            null,
        );
    }

    private function counselorOutcomes(AnalyticsRange $range): array
    {
        $independent = fn () => CounselorProfile::query()->where('verification_type', VerificationType::PlatformVerified->value);

        $cohort = $independent()
            ->whereNotNull('submitted_at')
            ->whereBetween('submitted_at', [AnalyticsRange::utc($range->from), AnalyticsRange::utc($range->to)])
            ->get(['verification_status', 'submission_count']);

        $count = fn (array $statuses) => $cohort->filter(fn ($p) => in_array($p->verification_status->value, $statuses, true))->count();

        $decisions = $independent()
            ->whereNotNull('submitted_at')
            ->whereBetween('reviewed_at', [AnalyticsRange::utc($range->from), AnalyticsRange::utc($range->to)])
            ->get(['submitted_at', 'reviewed_at']);

        return $this->outcomeBlock(
            $cohort->count(),
            $count([VerificationStatus::Approved->value, VerificationStatus::Suspended->value]),
            $count([VerificationStatus::Rejected->value]),
            $count([VerificationStatus::Pending->value]),
            $decisions,
            $cohort->where('submission_count', '>=', 2)->count(),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection  $decisions  rows with submitted_at and reviewed_at, decided in the range
     * @return array<string, mixed>
     */
    private function outcomeBlock(int $submitted, int $approved, int $rejected, int $pending, $decisions, ?int $resubmissions): array
    {
        $pct = fn (int $n) => $submitted === 0 ? null : round($n / $submitted * 100, 1);

        // Only a decision made AFTER the (latest) submission counts: after a resubmission reviewed_at still holds the old decision.
        $seconds = $decisions
            ->filter(fn ($row) => $row->reviewed_at->gte($row->submitted_at))
            ->map(fn ($row) => $row->submitted_at->diffInSeconds($row->reviewed_at, true))
            ->map(fn ($s) => (int) round($s))
            ->sort()->values()->all();

        return [
            'submitted' => $submitted,
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'approved_percent' => $pct($approved),
            'rejected_percent' => $pct($rejected),
            'pending_percent' => $pct($pending),
            'decisions' => count($seconds),
            'median_decision_seconds' => $this->median($seconds),
            // null = "—, not tracked yet" (organizations); a number for counselors.
            'resubmissions' => $resubmissions,
        ];
    }

    /** @param  list<int>  $sorted */
    private function median(array $sorted): ?int
    {
        $n = count($sorted);

        if ($n === 0) {
            return null;
        }

        $middle = intdiv($n, 2);

        return $n % 2 === 1 ? $sorted[$middle] : (int) round(($sorted[$middle - 1] + $sorted[$middle]) / 2);
    }

    // =============================================================================================================
    //  Reading the tables
    // =============================================================================================================

    /** @return list<array{at: ?CarbonInterface, suspended: bool}> */
    private function approvedOrganizations(): array
    {
        return Organization::query()
            ->where('org_type', '!=', OrgType::Platform->value)
            ->whereIn('subscription_status', [OrgStatus::Active->value, OrgStatus::Suspended->value])
            ->get(['reviewed_at', 'subscription_status'])
            ->map(fn (Organization $o) => ['at' => $o->reviewed_at, 'suspended' => $o->subscription_status === OrgStatus::Suspended])
            ->all();
    }

    /** @return list<array{at: ?CarbonInterface, suspended: bool, independent: bool}> */
    private function approvedCounselors(): array
    {
        return CounselorProfile::query()
            ->whereIn('verification_status', [VerificationStatus::Approved->value, VerificationStatus::Suspended->value])
            ->get(['reviewed_at', 'verification_status', 'verification_type'])
            ->map(fn (CounselorProfile $p) => [
                'at' => $p->reviewed_at,
                'suspended' => $p->verification_status === VerificationStatus::Suspended,
                'independent' => $p->verification_type === VerificationType::PlatformVerified,
            ])
            ->all();
    }

    /** Those approved on or before $moment; one with no approval date is always in. @return list<array<string, mixed>> */
    private function countedAsOf(array $rows, CarbonInterface $moment): array
    {
        return array_values(array_filter($rows, fn (array $r) => $r['at'] === null || $r['at']->lte($moment)));
    }

    private function countAsOf(array $rows, CarbonInterface $moment): int
    {
        return count($this->countedAsOf($rows, $moment));
    }

    /**
     * Bookings that have a session date, between $from and $to (generously, then cut exactly in PHP). The date is entry_date + start_time
     * in the slot timezone, turned into an instant. Only the plain columns are compared, so it is the same on SQLite and MySQL.
     *
     * @return \Illuminate\Support\Collection<int, array{at: CarbonImmutable, status: string, type: string, counselor: int}>
     */
    private function sessionRows(CarbonInterface $from, CarbonInterface $to)
    {
        $zone = (string) config('counselors.directory.slot_timezone');
        $from = CarbonImmutable::instance($from)->setTimezone($zone);
        $to = CarbonImmutable::instance($to)->setTimezone($zone);

        $rows = DB::table('bookings')
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'bookings.org_id')
            ->whereIn('bookings.status', self::OUTCOME_STATUSES)
            // A day either side of the range: the slot date is Malaysian, the range may be in another zone. Cut exactly below.
            ->where('calendar_entries.entry_date', '>=', $from->subDay()->format('Y-m-d'))
            ->where('calendar_entries.entry_date', '<', $to->addDays(2)->format('Y-m-d'))
            ->get(['calendar_entries.entry_date', 'calendar_entries.start_time', 'bookings.status', 'bookings.counselor_user_id', 'organizations.org_type']);

        return $rows->map(fn ($row) => [
            'at' => CarbonImmutable::parse(substr((string) $row->entry_date, 0, 10).' '.$row->start_time, $zone),
            'status' => (string) $row->status,
            'type' => $this->typeOf($row->org_type),
            'counselor' => (int) $row->counselor_user_id,
        ]);
    }

    /** @param  \Illuminate\Support\Collection  $rows */
    private function sessionsIn($rows, AnalyticsRange $range)
    {
        return $rows->filter(fn (array $row) => $row['at']->gte($range->from) && $row['at']->lte($range->to))->values();
    }

    /** The virtual platform organization is "independent practice"; no organization at all is its own bucket. */
    private function typeOf(?string $orgType): string
    {
        return match ($orgType) {
            OrgType::University->value => 'university',
            OrgType::Corporate->value => 'corporate',
            OrgType::Clinic->value => 'clinic',
            OrgType::Platform->value => 'independent',
            default => 'none',
        };
    }

    private function threshold(): int
    {
        return max(1, (int) config('analytics.small_number_threshold'));
    }
}
