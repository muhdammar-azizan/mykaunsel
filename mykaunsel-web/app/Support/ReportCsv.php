<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * An organization report as CSV rows (docs/REPORTS.md). It is built from the SAME arrays the pages show, whose figures ReportFigures has already
 * made safe ("fewer than 5", "hidden"), so an export can never say more than the page. It adds no figure and reads no table.
 *
 * Every cell is also made safe for a spreadsheet: one that starts with =, +, - or @ (a counselor named "=1+1", say) would be run as a formula.
 */
final class ReportCsv
{
    /**
     * @param  array<string, mixed>  $report
     * @param  array{label: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $period
     * @return list<list<string>>
     */
    public static function rows(string $organization, string $page, array $report, array $period): array
    {
        $rows = [
            ['Organization', $organization],
            ['Report', Str::headline($page)],
            ['Period', $period['label'].' ('.$period['from']->format('j M Y').' to '.$period['to']->format('j M Y').', Malaysia time)'],
            ['A number from 1 to '.((int) config('org-reports.min_group') - 1).' is shown as "fewer than '.(int) config('org-reports.min_group').'"; "hidden" protects a neighbouring number.'],
            [],
        ];

        foreach (self::walk('', $report) as $row) {
            $rows[] = $row;
        }

        return array_map(fn (array $row) => array_map(fn ($cell) => self::safe((string) $cell), $row), $rows);
    }

    /** @return list<list<string>> */
    private static function walk(string $prefix, mixed $value): array
    {
        // A set of figures (ReportFigures::set): a total and a cell each.
        if (is_array($value) && isset($value['cells'], $value['total'])) {
            $rows = [[$prefix, 'Total', $value['total']]];

            foreach ($value['cells'] as $label => $cell) {
                $rows[] = [$prefix, (string) $label, $cell['label']];
            }

            return $rows;
        }

        // A list of rows (the counselors): a header, then each row.
        if (is_array($value) && $value !== [] && array_is_list($value) && is_array($value[0])) {
            $rows = [array_merge([$prefix], array_map(fn ($key) => Str::headline((string) $key), array_keys($value[0])))];

            foreach ($value as $item) {
                $rows[] = array_merge([''], array_map(fn ($cell) => is_bool($cell) ? ($cell ? 'yes' : 'no') : (string) ($cell ?? ''), array_values($item)));
            }

            return $rows;
        }

        if (is_array($value)) {
            $rows = [];

            foreach ($value as $key => $inner) {
                $label = trim($prefix.' '.Str::headline((string) $key));
                array_push($rows, ...self::walk($label, $inner));
            }

            return $rows;
        }

        return [[$prefix, (string) ($value ?? 'not shown')]];
    }

    /** A cell that a spreadsheet would run as a formula gets a quote in front of it. */
    public static function safe(string $cell): string
    {
        return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$cell : $cell;
    }

    /** @param  list<list<string>>  $rows */
    public static function render(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");                       // so Excel reads the letters right

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);

        return (string) stream_get_contents($handle);
    }
}
