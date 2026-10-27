<?php

namespace App\Support;

/**
 * Ringgit as whole sen (integers), never floats. "RM 1,200.00" for display; a typed price is read digit by digit.
 */
class Money
{
    public static function rm(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.'RM '.number_format(intdiv($cents, 100)).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * What an admin typed ("1200", "1,200", "1200.5", "1,200.50") as sen, or null when it is not a plain amount with at
     * most two decimals. No currency sign, no minus, no exponent.
     */
    public static function parseRm(?string $input): ?int
    {
        $input = trim((string) $input);

        if (! preg_match('/^(\d{1,3}(,\d{3})+|\d+)(\.(\d{1,2}))?$/', $input, $m)) {
            return null;
        }

        $whole = str_replace(',', '', $m[1]);

        if (strlen($whole) > 12) {
            return null;
        }

        return (int) $whole * 100 + (int) str_pad($m[4] ?? '0', 2, '0');
    }

    /** "1,200.00" without the currency (for a form field). */
    public static function plain(int $cents): string
    {
        return number_format(intdiv($cents, 100), 0, '.', '').'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
