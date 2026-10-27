<?php

namespace App\Support;

/**
 * Reads the size an applicant picked when they signed up (organizations.estimated_size) as a range of members.
 *
 * The sign-up form stores things like "1–50 members", "1,000–5,000 employees" and "5,000+ members"; older and sample data has
 * "10 - 50" or "5,000 - 20,000". Anything else (empty, words) is "not stated" and gives null: nothing is guessed.
 */
class OrganizationSize
{
    /**
     * @return array{0: int, 1: ?int}|null [from, to]; `to` is null for "5,000+" (no upper limit)
     */
    public static function parse(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d[\d,]*)\s*[-–—]\s*(\d[\d,]*)(?!\d)/u', $text, $m)) {
            $from = (int) str_replace(',', '', $m[1]);
            $to = (int) str_replace(',', '', $m[2]);

            return $to >= $from ? [$from, $to] : null;
        }

        if (preg_match('/^(\d[\d,]*)\s*\+/u', $text, $m)) {
            return [(int) str_replace(',', '', $m[1]), null];
        }

        return null;
    }

    /**
     * How a plan [$min, $max] relates to the stated range: 'fits' (covers all of it), 'partial' (covers part of it) or 'none'.
     * A null `to` / `max` means no upper limit.
     *
     * @param  array{0: int, 1: ?int}  $size
     */
    public static function fit(int $min, ?int $max, array $size): string
    {
        [$from, $to] = $size;

        $covers = $min <= $from && ($max === null || ($to !== null && $max >= $to));

        if ($covers) {
            return 'fits';
        }

        $overlaps = $min <= ($to ?? PHP_INT_MAX) && $from <= ($max ?? PHP_INT_MAX);

        return $overlaps ? 'partial' : 'none';
    }
}
