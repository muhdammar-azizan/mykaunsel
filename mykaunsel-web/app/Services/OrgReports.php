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
