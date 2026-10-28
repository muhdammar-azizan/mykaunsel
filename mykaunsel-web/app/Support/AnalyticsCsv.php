<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The Analytics CSV, in LONG format: one figure per line with the columns section, label, period, value. The first lines (section
 * "Report") say which range, timezone and moment the figures are for.
 *
 * It is built from the SAME report array the page shows, so the two can never disagree, and the privacy rules carry over:
 * - a hidden small count ("<5") is written as "<5", never as a number (the report holds no number for it);
 * - there is no name, email, KB number or id of anyone, only counts, percentages and labels;
 * - text cells that a spreadsheet could read as a formula (= + - @) are made safe (neutralise()).
 */
final class AnalyticsCsv
{
    /** Shown when a figure does not exist (nothing to compare with, no sessions yet, not tracked). */
    public const NONE = 'n/a';

    /**
     * @param  array<string, mixed>  $report  AnalyticsService::report()
     * @param  string  $generatedAt  already in the admin's timezone, e.g. "2026-10-20 14:05:00"
     * @return list<array{0: string, 1: string, 2: string, 3: string|int|float}>
     */
    public static function rows(array $report, string $generatedAt): array
    {
        $range = $report['range'];
        $notes = $report['notes'];
        $from = self::date($range['from']);
        $to = self::date($range['to']);
        $period = "{$from} to {$to}";
        $prior = self::date($range['previous_from']).' to '.self::date($range['previous_to']);
        $rows = [];

        $add = function (string $section, string $label, string $when, string|int|float|null $value) use (&$rows) {
            $rows[] = [$section, $label, $when, $value ?? self::NONE];
        };

        // The first lines: what these figures are for.
        $add('Report', 'Date range', '', $period);
        $add('Report', 'Timezone', '', $range['timezone']);
        $add('Report', 'Generated at', '', $generatedAt);
        $add('Report', 'Chart period', '', $range['bucket'].' ('.$range['days'].' days)');
        $add('Report', 'Small numbers', '', 'A count from 1 to '.($notes['small_number_threshold'] - 1).' is written as <'.$notes['small_number_threshold'].' and never as a number');
        $add('Report', 'Data', '', 'Calculated from live platform data. Demo data may be included in non-production environments.');

        // Cards. The "previous" lines are the period of equal length just before.
        $cards = $report['cards'];
        $org = $cards['organizations'];
        $add('Approved organizations', 'Total', "as of {$to}", $org['value']);
        $add('Approved organizations', 'Total (previous period)', "as of ".self::date($range['previous_to']), $org['previous']);
        $add('Approved organizations', 'Change vs previous period (%)', $prior, $org['trend']['percent']);
        $add('Approved organizations', 'Suspended (currently)', 'now', $org['suspended']);
        $add('Approved organizations', 'Without a recorded approval date', 'now', $org['undated']);

        $cou = $cards['counselors'];
        $add('Approved counselors', 'Total', "as of {$to}", $cou['value']);
        $add('Approved counselors', 'Total (previous period)', 'as of '.self::date($range['previous_to']), $cou['previous']);
        $add('Approved counselors', 'Change vs previous period (%)', $prior, $cou['trend']['percent']);
        $add('Approved counselors', 'Independent', "as of {$to}", $cou['independent']);
        $add('Approved counselors', 'Organization', "as of {$to}", $cou['organization']);
        $add('Approved counselors', 'Suspended (currently)', 'now', $cou['suspended']);
        $add('Approved counselors', 'Without a recorded approval date', 'now', $cou['undated']);

        $ses = $cards['sessions_completed'];
        $nsr = $cards['no_show_rate'];

        if (! $notes['any_session_outcomes']) {
            // Same wording as the page: nothing can be completed until the attendance step exists.
            $add('Sessions completed', 'No session data yet', $period, null);
            $add('No-show rate', 'No session data yet', $period, null);
        } else {
            $add('Sessions completed', 'Total', $period, $ses['value']);
            $add('Sessions completed', 'Total (previous period)', $prior, $ses['previous']);
            $add('Sessions completed', 'Change vs previous period (%)', $prior, $ses['trend']['percent']);
            $add('Sessions completed', 'Sessions without a session date (left out)', 'all time', $notes['sessions_without_date']);

            $add('No-show rate', 'Rate (%)', $period, $nsr['value']);
            $add('No-show rate', 'Rate (%) (previous period)', $prior, $nsr['previous']);
            $add('No-show rate', 'Change vs previous period (%)', $prior, $nsr['trend']['percent']);
            $add('No-show rate', 'Completed', $period, $nsr['completed']);
            $add('No-show rate', 'No-show', $period, $nsr['no_show']);
            $add('No-show rate', 'Cancelled (not in the rate)', $period, $nsr['cancelled_excluded']);
        }

        // Growth: one line per series per chart period. The period is the first day of the chart period.
        foreach ($report['growth']['points'] as $point) {
            $add('Platform growth', 'Approved organizations (running total)', self::date($point['start']), $point['organizations']);
            $add('Platform growth', 'Approved counselors (running total)', self::date($point['start']), $point['counselors']);
        }

        // Sessions by organization type: the shown text ("<5" or a number), never a hidden count.
        if (! $notes['any_session_outcomes']) {
            $add('Sessions by organization type', 'No session data yet', $period, null);
        } else {
            foreach ($report['sessions_by_type']['items'] as $item) {
                $add('Sessions by organization type', $item['label'], $period, self::shown($item));
            }
        }

        // Specializations: groups under the threshold are already one "Other" line, so no rare specialization is named.
        if (! $notes['any_session_outcomes']) {
            $add('Specializations (sessions)', 'No session data yet', $period, null);
        } else {
            foreach ($report['specializations']['sessions']['items'] as $item) {
                $add('Specializations (sessions)', $item['label'], $period, self::shown($item));
            }
        }

        foreach ($report['specializations']['offered']['items'] as $item) {
            $add('Specializations (counselors offering)', $item['label'], 'now', self::shown($item));
        }

        // Application outcomes: applications submitted in the range, where they stand NOW.
        foreach (['organizations' => 'Application outcomes (organizations)', 'counselors' => 'Application outcomes (independent counselors)'] as $key => $section) {
            $o = $report['outcomes'][$key];
            $add($section, 'Submitted', $period, $o['submitted']);
            $add($section, 'Approved (now)', $period, $o['approved']);
            $add($section, 'Rejected (now)', $period, $o['rejected']);
            $add($section, 'Still pending (now)', $period, $o['pending']);
            $add($section, 'Approved (%)', $period, $o['approved_percent']);
            $add($section, 'Rejected (%)', $period, $o['rejected_percent']);
            $add($section, 'Pending (%)', $period, $o['pending_percent']);
            $add($section, 'Decisions made in the period', $period, $o['decisions']);
            $add($section, 'Median decision time (hours)', $period, $o['median_decision_seconds'] === null ? null : round($o['median_decision_seconds'] / 3600, 1));
            $add($section, 'Resubmissions', $period, $o['resubmissions'] ?? 'not tracked yet');
        }

        return $rows;
    }

    /** The names of the sections in a list of rows, in order, without the "Report" lines. Used for the audit entry. */
    public static function sections(array $rows): array
    {
        return array_values(array_unique(array_filter(array_column($rows, 0), fn ($section) => $section !== 'Report')));
    }

    /**
     * CSV-injection guard: a text cell that a spreadsheet could read as a formula (starts with = + - @, or a tab / carriage return,
     * even after spaces) gets a leading apostrophe so it is shown as text. Numbers are left alone.
     */
    public static function neutralise(mixed $cell): mixed
    {
        return AuditLogCsv::neutralise($cell);
    }

    /** @param  array{count: ?int, display: string, suppressed: bool}  $item */
    private static function shown(array $item): string|int
    {
        // A hidden count has no number at all in the report; only the "<5" text can be written.
        return $item['suppressed'] ? $item['display'] : (int) $item['count'];
    }

    /** "2026-10-20" from an ISO moment, keeping the admin's own day (the offset in the text decides the day). */
    private static function date(string $iso): string
    {
        return CarbonImmutable::parse($iso)->format('Y-m-d');
    }
}
