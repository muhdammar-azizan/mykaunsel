<?php

namespace App\Support;

/**
 * How long an application or complaint has been waiting, and how urgent that is. The ONE place for the thresholds, so the pages
 * (organizations, counselors) and the Dashboard can never disagree: 5 whole days or more is amber, 8 or more is red.
 */
final class WaitingTime
{
    public const AMBER_DAYS = 5;

    public const RED_DAYS = 8;

    /** 'ok', 'amber' or 'red' */
    public static function tone(int $days): string
    {
        return $days >= self::RED_DAYS ? 'red' : ($days >= self::AMBER_DAYS ? 'amber' : 'ok');
    }
}
