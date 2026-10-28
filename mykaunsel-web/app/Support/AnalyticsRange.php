<?php

namespace App\Support;

use App\Exceptions\InvalidAnalyticsRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Throwable;

/**
 * The date range of the Analytics page: whole days (00:00:00 to 23:59:59) in the admin's own timezone, which is an IANA name
 * ("Asia/Kuala_Lumpur"), never a fixed offset.
 *
 * - `from` is the first moment of the first day and `to` the last moment of the last day, both in that timezone.
 * - The chart bucket follows the length: daily up to 31 days, weekly up to 180, monthly beyond (config/analytics.php).
 * - previous() is the period of EQUAL length immediately before.
 * - Dates are turned into database text with utc(): the database keeps UTC.
 */
final class AnalyticsRange
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $timezone,
    ) {}

    /** The last $days whole days, ending with today (today counts, up to its last second). */
    public static function preset(int $days, string $timezone, ?CarbonInterface $now = null): self
    {
        self::assertTimezone($timezone);

        $today = self::today($timezone, $now);

        return new self($today->subDays(max(1, $days) - 1)->startOfDay(), $today->endOfDay(), $timezone);
    }

    /**
     * A range typed by the admin ("2027-01-31" style dates, in their timezone).
     *
     * @throws InvalidAnalyticsRange reversed, in the future, longer than the maximum, or not a date
     */
    public static function custom(?string $from, ?string $to, string $timezone, ?CarbonInterface $now = null): self
    {
        self::assertTimezone($timezone);

        $start = self::parseDate($from, $timezone);
        $end = self::parseDate($to, $timezone);

        if ($start === null || $end === null) {
            throw new InvalidAnalyticsRange('Choose a valid start date and end date.');
        }

        if ($start->gt($end)) {
            throw new InvalidAnalyticsRange('The start date must be on or before the end date.');
        }

        if ($end->gt(self::today($timezone, $now))) {
            throw new InvalidAnalyticsRange('The end date cannot be in the future.');
        }

        $range = new self($start->startOfDay(), $end->endOfDay(), $timezone);

        if ($range->days() > (int) config('analytics.max_days')) {
            throw new InvalidAnalyticsRange('The range can be at most '.(int) config('analytics.max_days').' days.');
        }

        return $range;
    }

    /** How many calendar days the range covers (both ends included). */
    public function days(): int
    {
        return (int) round($this->from->startOfDay()->diffInDays($this->to->startOfDay(), true)) + 1;
    }

    /** 'day', 'week' or 'month' */
    public function bucket(): string
    {
        $days = $this->days();

        return match (true) {
            $days <= (int) config('analytics.daily_max_days') => 'day',
            $days <= (int) config('analytics.weekly_max_days') => 'week',
            default => 'month',
        };
    }

    /** The period of equal length immediately before this one. Not checked against the maximum: it only exists to compare. */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->subDays($days)->startOfDay(), $this->from->subDay()->endOfDay(), $this->timezone);
    }

    /**
     * The chart buckets, in order, each a whole number of days in the admin's timezone, the first and last cut to the range. Weeks run
     * Monday to Sunday; months are calendar months.
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, label: string}>
     */
    public function buckets(): array
    {
        $bucket = $this->bucket();
        $buckets = [];
        $cursor = $this->from;

        while ($cursor->lte($this->to)) {
            $natural = match ($bucket) {
                'day' => $cursor->endOfDay(),
                'week' => $cursor->endOfWeek(CarbonInterface::SUNDAY),
                default => $cursor->endOfMonth(),
            };
            $end = $natural->gt($this->to) ? $this->to : $natural;

            $buckets[] = [
                'start' => $cursor,
                'end' => $end,
                'label' => match ($bucket) {
                    'day' => $cursor->format('j M'),
                    'week' => 'Week of '.$cursor->format('j M'),
                    default => $cursor->format('M Y'),
                },
            ];

            $cursor = $end->addDay()->startOfDay();
        }

        return $buckets;
    }

    /** Part of the cache key: a result is only ever reused for the same days AND the same timezone. */
    public function cacheKey(): string
    {
        return 'analytics:'.$this->timezone.':'.$this->from->format('Y-m-d').':'.$this->to->format('Y-m-d');
    }

    /** A moment as the database keeps it (UTC, the application timezone). */
    public static function utc(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private static function today(string $timezone, ?CarbonInterface $now): CarbonImmutable
    {
        return CarbonImmutable::instance($now ?? now())->setTimezone($timezone)->startOfDay();
    }

    private static function assertTimezone(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidAnalyticsRange('That timezone is not known.');
        }
    }

    private static function parseDate(?string $value, string $timezone): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        } catch (Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
