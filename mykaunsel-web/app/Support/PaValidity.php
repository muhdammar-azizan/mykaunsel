<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One definition of "practising certificate (PA) is still valid".
 *
 * "Valid until 22 Oct" means valid for the WHOLE of 22 Oct in MALAYSIAN time (Asia/Kuala_Lumpur, the same zone as the calendar): up to its last
 * second, and expired from the first second of 23 Oct Malaysian time. The server's own zone (UTC) is never used for a date: at 00:30 in Malaysia
 * it is still the day before in UTC. Everything that asks (the counselor profile, the LKM check, listing, the Platform Admin pages, the daily
 * check) must use this, so they can never disagree about the last day.
 */
class PaValidity
{
    /** The zone every PA date is read in: Malaysian time. One setting, shared with the calendar (config/counselors.php, directory.slot_timezone). */
    public static function timezone(): string
    {
        return (string) config('counselors.directory.slot_timezone', 'Asia/Kuala_Lumpur');
    }

    /** Today in Malaysian time, as a Y-m-d date. */
    public static function today(?CarbonInterface $at = null): string
    {
        return self::inMalaysia($at ?? Carbon::now())->format('Y-m-d');
    }

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

        $today = Carbon::parse(self::today($at), self::timezone())->startOfDay();
        $last = Carbon::parse($validUntil->format('Y-m-d'), self::timezone())->startOfDay();

        return (int) $today->diffInDays($last, false);
    }

    /** True when still valid but running out within $days days (the "expiring soon" badge). */
    public static function expiresWithin(?CarbonInterface $validUntil, int $days, ?CarbonInterface $at = null): bool
    {
        return self::isValid($validUntil, $at) && self::daysLeft($validUntil, $at) <= $days;
    }

    /** The last moment the PA counts as valid: 23:59:59 on the stated date, in Malaysian time. */
    public static function lastMoment(CarbonInterface $validUntil): Carbon
    {
        return Carbon::parse($validUntil->format('Y-m-d'), self::timezone())->endOfDay();
    }

    /** A moment, seen in Malaysian time (the original is not changed). */
    private static function inMalaysia(CarbonInterface $moment): CarbonImmutable
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone());
    }
}
