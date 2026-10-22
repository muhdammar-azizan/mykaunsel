<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One definition of "practising certificate (PA) is still valid".
 *
 * "Valid until 22 Oct" means valid for the WHOLE of 22 Oct — up to its last second in the application's timezone —
 * and expired from the first second of 23 Oct. Everything that asks (the counselor profile, the LKM check, listing,
 * the Platform Admin pages) must use this, so they can never disagree about the last day.
 */
class PaValidity
{
    public static function isValid(?CarbonInterface $validUntil, ?CarbonInterface $at = null): bool
    {
        if ($validUntil === null) {
            return false;
        }

        return self::lastMoment($validUntil)->greaterThanOrEqualTo($at ?? now());
    }

    public static function isExpired(?CarbonInterface $validUntil, ?CarbonInterface $at = null): bool
    {
        return ! self::isValid($validUntil, $at);
    }

    /** Whole calendar days left, counting today (valid until today = 0). Negative once expired. Null when unknown. */
    public static function daysLeft(?CarbonInterface $validUntil, ?CarbonInterface $at = null): ?int
    {
        if ($validUntil === null) {
            return null;
        }

        $today = Carbon::parse(($at ?? now())->format('Y-m-d'), config('app.timezone'))->startOfDay();
        $last = Carbon::parse($validUntil->format('Y-m-d'), config('app.timezone'))->startOfDay();

        return (int) $today->diffInDays($last, false);
    }

    /** True when still valid but running out within $days days (the "expiring soon" badge). */
    public static function expiresWithin(?CarbonInterface $validUntil, int $days, ?CarbonInterface $at = null): bool
    {
        return self::isValid($validUntil, $at) && self::daysLeft($validUntil, $at) <= $days;
    }

    /** The last moment the PA counts as valid: 23:59:59 on the stated date, in the application timezone. */
    public static function lastMoment(CarbonInterface $validUntil): Carbon
    {
        return Carbon::parse($validUntil->format('Y-m-d'), config('app.timezone'))->endOfDay();
    }
}
