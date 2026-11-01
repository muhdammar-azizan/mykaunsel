<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Support\ReportFigures;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * An organization's reports (docs/PORTALS_PLAN.md, "Reports"): counts only, for the organization's own people and sessions, for a chosen period.
 *
 * - Never a name, never anything from the chat or from a session note. A figure about 1 to 4 people or sessions is hidden (ReportFigures).
 * - "Sessions" are the bookings made for this organization's times (`bookings.org_id`), by the day the session takes place (Malaysian calendar).
 * - Read in one pass over the period's rows and added up here, so the same rules work on SQLite and MariaDB.
 */
class OrgReports
{
    public function __construct(private readonly AvailabilityService $availability) {}

    // =============================================================================================================
    //  The period
    // =============================================================================================================

    /** @return array{key: string, label: string, from: CarbonImmutable, to: CarbonImmutable, by: string, choices: array<string, string>} */
    public function period(?string $key): array
    {
        $ranges = config('org-reports.ranges');
        $key = array_key_exists((string) $key, $ranges) ? (string) $key : (string) config('org-reports.default_range');
        [$label, $back] = $ranges[$key];

        $now = $this->availability->now();
        $from = $now->startOfMonth()->subMonths($back);
        $to = $now->endOfMonth();

        return [
            'key' => $key,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'by' => $back >= 6 ? 'month' : 'week',
            'choices' => array_map(fn ($range) => $range[0], $ranges),
        ];
    }

    /**
     * The period of the same length just before this one (this month -> last month, the last 3 months -> the 3 months before them), for comparing.
     *
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    public function previous(array $period): array
    {
        $months = (int) config('org-reports.ranges')[$period['key']][1] + 1;
        $from = $period['from']->subMonths($months);

        return ['label' => 'The '.($months === 1 ? 'month' : $months.' months').' before'] + ['from' => $from, 'to' => $period['from']->subDay()->endOfDay()] + $period;
    }

    // =============================================================================================================
    //  Overview (the glance page)
    // =============================================================================================================

    /** @return array<string, mixed> */
    public function overview(Organization $org, array $period): array
    {
        // Cancelled sessions are not use of the service.
        $held = fn (array $rows) => array_filter($rows, fn (array $row) => $row['status'] !== BookingStatus::Cancelled->value);

        $now = $held($this->sessions($org, $period['from'], $period['to']));
        $span = (int) $period['from']->diffInDays($period['to']) + 1;
        $before = $held($this->sessions($org, $period['from']->subDays($span), $period['from']->subDay()));

        $nowCount = count($now);
        $beforeCount = count($before);
        $min = (int) config('org-reports.min_group');

        $clientRoles = array_map(fn ($r) => $r->value, MembershipService::CLIENT_ROLES);
        $members = fn (MembershipStatus $status) => DB::table('memberships')->where('org_id', $org->getKey())->whereIn('role', $clientRoles)->where('status', $status->value);

        return [
            'sessions' => ReportFigures::one($nowCount),
            'sessions_before' => ReportFigures::one($beforeCount),
            'change' => $nowCount >= $min && $beforeCount >= $min ? $nowCount - $beforeCount : null,
            'members_active' => $members(MembershipStatus::Active)->count(),
            'members_waiting' => $members(MembershipStatus::PendingApproval)->count(),
            'members_left' => ReportFigures::one(DB::table('memberships')->where('org_id', $org->getKey())->whereIn('role', $clientRoles)
                ->whereNotNull('offboarded_at')->where('offboarded_at', '>=', $period['from']->utc())->where('offboarded_at', '<=', $period['to']->utc())->count()),
            'counselors' => DB::table('memberships')->where('org_id', $org->getKey())->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->count(),
        ];
    }

    // =============================================================================================================
    //  Use
    // =============================================================================================================

    /** @return array<string, mixed> */
    public function use(Organization $org, array $period): array
    {
        $rows = $this->sessions($org, $period['from'], $period['to']);

        $byStatus = ['Completed' => 0, 'No-show' => 0, 'Cancelled' => 0, 'Still ahead, or not marked yet' => 0];
        $byMode = ['Online' => 0, 'In person' => 0];
        $weekdays = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];
        $hours = [];
        $lead = array_fill_keys(array_keys(config('org-reports.lead_buckets')), 0);
        $series = $this->emptySeries($period);

        foreach ($rows as $row) {
            $byStatus[match ($row['status']) {
                BookingStatus::Completed->value => 'Completed',
                BookingStatus::NoShow->value => 'No-show',
                BookingStatus::Cancelled->value => 'Cancelled',
                default => 'Still ahead, or not marked yet',
            }]++;

            $series[$this->bucket($row['day'], $period['by'])]['count']++;

            // How people use it: only sessions that were not cancelled.
            if ($row['status'] === BookingStatus::Cancelled->value) {
                continue;
            }

            $byMode[$row['mode'] === 'physical' ? 'In person' : 'Online']++;
            $weekdays[$row['day']->englishDayOfWeek]++;
            $hour = $row['day']->setTimeFromTimeString($row['time'])->format('g A');
            $hours[$row['hour']] = [$hour, ($hours[$row['hour']][1] ?? 0) + 1];

            $ahead = max(0, (int) $row['booked_on']->diffInDays($row['day'], false));

            foreach (config('org-reports.lead_buckets') as $label => [$from, $to]) {
                if ($ahead >= $from && ($to === null || $ahead <= $to)) {
                    $lead[$label]++;
                    break;
                }
            }
        }

        ksort($hours);

        return [
            'total' => ReportFigures::one(count($rows)),
            'status' => ReportFigures::set($byStatus),
            'mode' => ReportFigures::set($byMode),
            'weekdays' => ReportFigures::set($weekdays),
            'hours' => ReportFigures::set(collect($hours)->mapWithKeys(fn ($h) => [$h[0] => $h[1]])->all()),
            'lead' => ReportFigures::set($lead),
            'series' => ReportFigures::set(collect($series)->mapWithKeys(fn ($s) => [$s['label'] => $s['count']])->all()),
            'requests' => $this->requests($org, $period),
        ];
    }

    /** Time requests: how many, how they ended, and how fast they were answered. @return array<string, mixed> */
    private function requests(Organization $org, array $period): array
    {
        $rows = DB::table('booking_requests')->where('context_org_id', $org->getKey())
            ->where('created_at', '>=', $period['from']->utc())->where('created_at', '<=', $period['to']->utc())
            ->get(['status', 'created_at', 'responded_at']);

        $ended = ['Approved' => 0, 'Declined' => 0, 'Not answered in time' => 0, 'Withdrawn by the member' => 0, 'Still waiting' => 0];
        $answered = 0;
        $fast = 0;

        foreach ($rows as $row) {
            $ended[match ($row->status) {
                BookingRequestStatus::Approved->value => 'Approved',
                BookingRequestStatus::Rejected->value => 'Declined',
                BookingRequestStatus::Expired->value => 'Not answered in time',
                BookingRequestStatus::Withdrawn->value => 'Withdrawn by the member',
                default => 'Still waiting',
            }]++;

            if ($row->responded_at !== null && in_array($row->status, [BookingRequestStatus::Approved->value, BookingRequestStatus::Rejected->value], true)) {
                $answered++;
                $fast += CarbonImmutable::parse($row->created_at, 'UTC')->diffInHours(CarbonImmutable::parse($row->responded_at, 'UTC')) < 24 ? 1 : 0;
            }
        }

        return [
            'total' => ReportFigures::one(count($rows)),
            'ended' => ReportFigures::set($ended),
            'answered' => ReportFigures::one($answered),
            'answered_fast' => $answered >= (int) config('org-reports.min_group') ? round($fast / $answered * 100).'%' : null,
        ];
    }

    // =============================================================================================================
    //  Members
    // =============================================================================================================

    /** @return array<string, mixed> */
    public function members(Organization $org, array $period): array
    {
        $clientRoles = array_map(fn ($r) => $r->value, MembershipService::CLIENT_ROLES);
        $base = fn () => DB::table('memberships')->where('org_id', $org->getKey())->whereIn('role', $clientRoles);

        $from = $period['from']->utc();
        $to = $period['to']->utc();

        $joined = $base()->where('joined_at', '>=', $from)->where('joined_at', '<=', $to)
            ->where('status', '!=', MembershipStatus::PendingApproval->value)->get(['joined_at', 'join_source']);
        $left = $base()->whereNotNull('offboarded_at')->where('offboarded_at', '>=', $from)->where('offboarded_at', '<=', $to)->get(['offboarded_at']);

        $joinedSeries = $this->emptySeries($period);
        $leftSeries = $this->emptySeries($period);
        $sources = ['By their e-mail domain' => 0, 'With a reference code' => 0, 'By an invitation' => 0, 'Another way' => 0];

        foreach ($joined as $row) {
            $joinedSeries[$this->bucket($this->day($row->joined_at), $period['by'])]['count']++;
            $sources[match ($row->join_source) {
                JoinSource::Domain->value => 'By their e-mail domain',
                JoinSource::InviteCode->value => 'With a reference code',
                JoinSource::Invitation->value => 'By an invitation',
                default => 'Another way',
            }]++;
        }

        foreach ($left as $row) {
            $leftSeries[$this->bucket($this->day($row->offboarded_at), $period['by'])]['count']++;
        }

        $active = (clone $base())->where('status', MembershipStatus::Active->value)->pluck('user_id');
        $booked = $active->isEmpty() ? 0 : DB::table('bookings')->where('org_id', $org->getKey())->where('status', '!=', BookingStatus::Cancelled->value)
            ->whereIn('user_id', $active)->distinct()->count('user_id');

        return [
            'joined' => ReportFigures::set(collect($joinedSeries)->mapWithKeys(fn ($s) => [$s['label'] => $s['count']])->all()),
            'left' => ReportFigures::set(collect($leftSeries)->mapWithKeys(fn ($s) => [$s['label'] => $s['count']])->all()),
            'sources' => ReportFigures::set($sources),
            'active' => ReportFigures::one($active->count()),
            'share_booked' => $active->count() >= (int) config('org-reports.min_group') ? round($booked / $active->count() * 100).'%' : null,
        ];
    }

    // =============================================================================================================

    // =============================================================================================================
    //  Counselors
    // =============================================================================================================

    /**
     * Capacity and use per counselor of this organization. A counselor's name is shown (the organization chose them); a client never is. The number of
     * sessions of one counselor is a small group, so it follows the same hiding rule, and the share booked is shown only when it is not hidden.
     *
     * @return array<string, mixed>
     */
    public function counselors(Organization $org, array $period): array
    {
        $people = DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.org_id', $org->getKey())->where('memberships.role', MembershipRole::Counselor->value)->where('memberships.status', MembershipStatus::Active->value)
            ->orderBy('users.name')->get(['users.id', 'users.name']);
        $ids = $people->pluck('id')->all();

        $offered = array_fill_keys($ids, 0);
        $booked = array_fill_keys($ids, 0);
        $sessions = array_fill_keys($ids, 0);

        if ($ids !== []) {
            $rows = DB::table('calendar_entries')
                ->where('context_org_id', $org->getKey())->whereIn('counselor_user_id', $ids)->where('entry_type', 'available_slot')
                ->whereDate('entry_date', '>=', $period['from']->format('Y-m-d'))->whereDate('entry_date', '<=', $period['to']->format('Y-m-d'))
                ->select(['calendar_entries.counselor_user_id', 'calendar_entries.duration_minutes'])
                ->selectRaw('(select count(*) from bookings where bookings.calendar_entry_id = calendar_entries.id and bookings.status != ?) as active_bookings', [BookingStatus::Cancelled->value])
                ->get();

            foreach ($rows as $row) {
                $offered[$row->counselor_user_id] += (int) $row->duration_minutes;

                if ((int) $row->active_bookings > 0) {
                    $booked[$row->counselor_user_id] += (int) $row->duration_minutes;
                    $sessions[$row->counselor_user_id] += (int) $row->active_bookings;
                }
            }
        }

        $first = $this->firstFree($org, $ids);
        $min = (int) config('org-reports.min_group');
        $perCounselor = ReportFigures::set(collect($people)->mapWithKeys(fn ($p) => [$p->name => $sessions[$p->id]])->all());

        $list = [];

        foreach ($people as $person) {
            $cell = $perCounselor['cells'][$person->name];

            $list[] = [
                'name' => $person->name,
                'offered_hours' => round($offered[$person->id] / 60, 1),
                'sessions' => $cell['label'],
                'booked_share' => ! $cell['hidden'] && $sessions[$person->id] >= $min && $offered[$person->id] > 0 ? round($booked[$person->id] / $offered[$person->id] * 100).'%' : null,
                'first_free' => $first[$person->id] ?? null,
            ];
        }

        $totalOffered = array_sum($offered);
        $totalBooked = array_sum($booked);

        return [
            'list' => $list,
            'offered_hours' => round($totalOffered / 60, 1),
            'booked_hours' => round($totalBooked / 60, 1),
            'booked_share' => $totalOffered > 0 && array_sum($sessions) >= $min ? round($totalBooked / $totalOffered * 100).'%' : null,
            'sessions_total' => $perCounselor['total'],
        ];
    }

    /** For each counselor, the next time still free for this organization: when, and how many days away. @param list<int> $ids @return array<int, string> */
    private function firstFree(Organization $org, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $now = $this->availability->now();
        $today = $now->startOfDay();

        $rows = DB::table('calendar_entries')->where('context_org_id', $org->getKey())->whereIn('counselor_user_id', $ids)
            ->where('entry_type', 'available_slot')->where('is_available', true)
            ->whereDate('entry_date', '>=', $now->format('Y-m-d'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('bookings')->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')->where('bookings.status', '!=', BookingStatus::Cancelled->value))
            ->orderBy('entry_date')->orderBy('start_time')->get(['counselor_user_id', 'entry_date', 'start_time']);

        $first = [];

        foreach ($rows as $row) {
            if (isset($first[$row->counselor_user_id])) {
                continue;
            }

            $start = CarbonImmutable::parse(substr((string) $row->entry_date, 0, 10).' '.$row->start_time, $this->availability->timezone());

            if ($start->lte($now)) {
                continue;
            }

            $days = (int) $today->diffInDays($start->startOfDay());
            $first[$row->counselor_user_id] = $start->format('D, j M').' ('.($days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days")).')';
        }

        return $first;
    }

    // =============================================================================================================
    //  Quality
    // =============================================================================================================

    /**
     * Ratings and cancelling for this organization's sessions in the period. NEVER a comment or a client. An average is shown only from
     * `reviews.min_for_average` reviews (overall, and for each counselor on their own count); the cancelling rate only from `min_group` sessions.
     *
     * @return array<string, mixed>
     */
    public function quality(Organization $org, array $period): array
    {
        $ratings = $this->ratings($org, $period);
        unset($ratings['distribution']);

        return $ratings + $this->cancelling($org, $period);
    }

    /**
     * The organization's Reviews page: the stars clients gave after sessions with its counselors, and nothing else. NEVER a comment, a client, or the
     * date of one review. An average is shown from `reviews.min_for_average` reviews (overall, and per counselor on their own count); how the stars are
     * spread follows the same hiding rule as every other set.
     *
     * @return array<string, mixed>
     */
    public function reviews(Organization $org, array $period): array
    {
        return $this->ratings($org, $period);
    }

    /** @return array<string, mixed> */
    private function ratings(Organization $org, array $period): array
    {
        $needed = (int) config('reviews.min_for_average');

        $reviews = DB::table('reviews')->join('bookings', 'bookings.id', '=', 'reviews.booking_id')->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->join('users', 'users.id', '=', 'reviews.counselor_user_id')
            ->where('bookings.org_id', $org->getKey())->where('reviews.status', '!=', 'removed')
            ->whereDate('calendar_entries.entry_date', '>=', $period['from']->format('Y-m-d'))->whereDate('calendar_entries.entry_date', '<=', $period['to']->format('Y-m-d'))
            ->get(['reviews.rating', 'reviews.counselor_user_id', 'users.name']);

        $perCounselor = [];

        foreach ($reviews as $review) {
            $perCounselor[$review->counselor_user_id]['name'] = $review->name;
            $perCounselor[$review->counselor_user_id]['ratings'][] = (int) $review->rating;
        }

        $counselors = collect($perCounselor)->map(fn ($c) => [
            'name' => $c['name'],
            'count' => count($c['ratings']),
            'average' => count($c['ratings']) >= $needed ? number_format(array_sum($c['ratings']) / count($c['ratings']), 1) : null,
        ])->sortBy('name')->values()->all();

        $stars = ['5 stars' => 0, '4 stars' => 0, '3 stars' => 0, '2 stars' => 0, '1 star' => 0];

        foreach ($reviews as $review) {
            $stars[(int) $review->rating === 1 ? '1 star' : (int) $review->rating.' stars']++;
        }

        return [
            'needed' => $needed,
            'reviews' => ReportFigures::one($reviews->count()),
            'average' => $reviews->count() >= $needed ? number_format($reviews->avg('rating'), 1) : null,
            'counselors' => $counselors,
            'distribution' => ReportFigures::set($stars),
        ];
    }

    /** @return array<string, mixed> */
    private function cancelling(Organization $org, array $period): array
    {
        $cancel = DB::table('bookings')->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.org_id', $org->getKey())
            ->whereDate('calendar_entries.entry_date', '>=', $period['from']->format('Y-m-d'))->whereDate('calendar_entries.entry_date', '<=', $period['to']->format('Y-m-d'))
            ->get(['bookings.status', 'bookings.cancelled_by']);

        $cancelled = $cancel->where('status', BookingStatus::Cancelled->value);
        $by = [
            'By the member' => $cancelled->whereIn('cancelled_by', [null, 'client'])->count(),
            'By the counselor' => $cancelled->where('cancelled_by', 'counselor')->count(),
            'By MyKaunsel or the organization' => $cancelled->where('cancelled_by', 'system')->count(),
        ];

        return [
            'sessions' => ReportFigures::one($cancel->count()),
            'cancel_rate' => $cancel->count() >= (int) config('org-reports.min_group') ? round($cancelled->count() / $cancel->count() * 100).'%' : null,
            'cancelled_by' => ReportFigures::set($by),
        ];
    }

    /**
     * The sessions of this organization on days in the period: status, mode, the day and time, and the day it was booked. No name of anyone.
     *
     * @return list<array{status: string, mode: string, day: CarbonImmutable, time: string, hour: string, booked_on: CarbonImmutable}>
     */
    private function sessions(Organization $org, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];

        $query = DB::table('bookings')->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.org_id', $org->getKey())
            ->whereDate('calendar_entries.entry_date', '>=', $from->format('Y-m-d'))->whereDate('calendar_entries.entry_date', '<=', $to->format('Y-m-d'))
            ->select(['bookings.status', 'bookings.session_mode', 'bookings.created_at', 'calendar_entries.entry_date', 'calendar_entries.start_time']);

        foreach ($query->cursor() as $row) {
            $day = $this->day($row->entry_date, false);
            $rows[] = [
                'status' => $row->status,
                'mode' => (string) $row->session_mode,
                'day' => $day,
                'time' => (string) $row->start_time,
                'hour' => substr((string) $row->start_time, 0, 2),
                'booked_on' => $this->day($row->created_at),
            ];
        }

        return $rows;
    }

    /** A stored moment (UTC) as a Malaysian calendar day, or a stored date as that same day. */
    private function day(string $value, bool $fromUtc = true): CarbonImmutable
    {
        $tz = $this->availability->timezone();

        return $fromUtc ? CarbonImmutable::parse($value, 'UTC')->setTimezone($tz)->startOfDay() : CarbonImmutable::parse(substr($value, 0, 10), $tz)->startOfDay();
    }

    /** Every week (Monday) or month of the period, so a quiet one shows as 0. @return array<string, array{label: string, count: int}> */
    private function emptySeries(array $period): array
    {
        $series = [];
        $step = $period['by'] === 'month' ? $period['from']->startOfMonth() : $period['from']->startOfWeek();

        while ($step->lte($period['to'])) {
            $series[$this->bucket($step, $period['by'])] = ['label' => $period['by'] === 'month' ? $step->format('M Y') : 'Week of '.$step->format('j M'), 'count' => 0];
            $step = $period['by'] === 'month' ? $step->addMonth() : $step->addWeek();
        }

        return $series;
    }

    private function bucket(CarbonImmutable $day, string $by): string
    {
        return $by === 'month' ? $day->startOfMonth()->format('Y-m-d') : $day->startOfWeek()->format('Y-m-d');
    }
}
