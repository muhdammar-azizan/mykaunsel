<?php

namespace App\Support;

/**
 * The one rule for showing a number in an organization's reports (docs/PORTALS_PLAN.md, "Reports"): a figure about a group of 1 to (min - 1) people
 * or sessions is never shown, so nobody can be recognised from it. Used by every report, never worked out a second way.
 *
 * - A single figure: 0 stays 0; 1..min-1 becomes "fewer than N".
 * - A set of figures that add up to a total (sessions by weekday, by status...): each small cell is hidden. If only ONE cell is hidden and the total is
 *   shown, the reader could subtract to find it, so the smallest other cell that is not zero is hidden too ("hidden").
 */
final class ReportFigures
{
    public static function one(int $count, ?int $min = null): string
    {
        $min ??= (int) config('org-reports.min_group');

        return $count > 0 && $count < $min ? 'fewer than '.$min : number_format($count);
    }

    /**
     * @param  array<string, int>  $counts  label => count
     * @return array{total: string, cells: array<string, array{value: int|null, label: string, hidden: bool}>}
     */
    public static function set(array $counts, ?int $min = null): array
    {
        $min ??= (int) config('org-reports.min_group');
        $total = array_sum($counts);

        $hidden = [];

        foreach ($counts as $label => $count) {
            if ($count > 0 && $count < $min) {
                $hidden[$label] = 'primary';
            }
        }

        // One hidden cell next to a visible total gives itself away: hide the smallest other cell that has anything in it.
        if (count($hidden) === 1 && $total >= $min) {
            $others = array_filter($counts, fn ($count, $label) => $count > 0 && ! isset($hidden[$label]), ARRAY_FILTER_USE_BOTH);

            if ($others !== []) {
                asort($others);
                $hidden[array_key_first($others)] = 'secondary';
            }
        }

        $cells = [];

        foreach ($counts as $label => $count) {
            $kind = $hidden[$label] ?? null;
            $cells[$label] = [
                'value' => $kind === null ? $count : null,
                'label' => match ($kind) {
                    'primary' => 'fewer than '.$min,
                    'secondary' => 'hidden',
                    default => number_format($count),
                },
                'hidden' => $kind !== null,
            ];
        }

        return ['total' => self::one($total, $min), 'cells' => $cells];
    }

    /** The height of a bar, 0 to 100, for a cell (a hidden cell has no bar). */
    public static function bar(array $cell, int $max): int
    {
        return $cell['value'] === null || $max <= 0 ? 0 : (int) round($cell['value'] / $max * 100);
    }
}
