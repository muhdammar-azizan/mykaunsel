<?php

namespace Tests\Feature\Platform;

use App\Exceptions\InvalidAnalyticsRange;
use App\Support\AnalyticsRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The date range of the Analytics page: whole days in the admin's timezone, the chart bucket, the previous period.
 */
class AnalyticsRangeTest extends TestCase
{
    private const KL = 'Asia/Kuala_Lumpur';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', self::KL));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function custom(string $from, string $to, string $tz = self::KL): AnalyticsRange
    {
        return AnalyticsRange::custom($from, $to, $tz);
    }

    private function invalid(?string $from, ?string $to, string $needle, string $tz = self::KL): void
    {
        try {
            AnalyticsRange::custom($from, $to, $tz);
            $this->fail('the range was accepted: '.json_encode([$from, $to]));
        } catch (InvalidAnalyticsRange $e) {
            $this->assertStringContainsString($needle, $e->getMessage());
        }
    }

    // ---- presets and whole days -------------------------------------------------------------------------------

    public function test_a_preset_is_whole_days_ending_today_in_the_admins_timezone(): void
    {
        $r = AnalyticsRange::preset(30, self::KL);

        $this->assertSame('2026-09-29 00:00:00', $r->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-28 23:59:59', $r->to->format('Y-m-d H:i:s'));
        $this->assertSame([30, 'day', self::KL], [$r->days(), $r->bucket(), $r->timezone]);
        $this->assertSame('2026-09-28 16:00:00', AnalyticsRange::utc($r->from), 'midnight in Kuala Lumpur is 16:00 the day before in UTC');
        $this->assertSame('2026-10-28 15:59:59', AnalyticsRange::utc($r->to));
    }

    public function test_the_same_moment_is_a_different_day_in_a_different_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 23:00:00', 'UTC'));   // already the 29th in Kuala Lumpur

        $this->assertSame('2026-10-29', AnalyticsRange::preset(7, self::KL)->to->format('Y-m-d'));
        $this->assertSame('2026-10-28', AnalyticsRange::preset(7, 'UTC')->to->format('Y-m-d'));

        // ...so "the 29th" is the future for UTC, but today for Kuala Lumpur.
        $this->assertSame(1, $this->custom('2026-10-29', '2026-10-29')->days());
        $this->invalid('2026-10-29', '2026-10-29', 'in the future', 'UTC');
    }

    public function test_the_presets_pick_the_expected_buckets(): void
    {
        $this->assertSame('day', AnalyticsRange::preset(7, self::KL)->bucket());
        $this->assertSame('day', AnalyticsRange::preset(30, self::KL)->bucket());
        $this->assertSame('week', AnalyticsRange::preset(90, self::KL)->bucket());
    }

    // ---- validation -------------------------------------------------------------------------------------------

    public function test_a_reversed_range_is_refused_and_one_day_is_fine(): void
    {
        $this->invalid('2026-10-10', '2026-10-09', 'on or before');
        $this->assertSame(1, $this->custom('2026-10-10', '2026-10-10')->days());
    }

    public function test_an_end_date_in_the_future_is_refused_and_today_is_fine(): void
    {
        $this->invalid('2026-10-01', '2026-10-29', 'cannot be in the future');
        $this->assertSame('2026-10-28', $this->custom('2026-10-01', '2026-10-28')->to->format('Y-m-d'));
    }

    public function test_the_longest_range_is_366_days(): void
    {
        $this->assertSame(366, $this->custom('2025-10-28', '2026-10-28')->days());
        $this->invalid('2025-10-27', '2026-10-28', 'at most 366 days');
    }

    public function test_things_that_are_not_dates_or_zones_are_refused(): void
    {
        foreach ([[null, '2026-10-01'], ['2026-10-01', null], ['', ''], ['abc', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['2026-13-01', '2026-13-02'], ['01/10/2026', '2026-10-02'], ['2026-10-01 10:00', '2026-10-02'], ['2026-1-1', '2026-10-02']] as [$from, $to]) {
            $this->invalid($from, $to, 'valid start date and end date');
        }

        $this->invalid('2026-10-01', '2026-10-02', 'timezone is not known', 'Mars/Olympus');
        $this->invalid('2026-10-01', '2026-10-02', 'timezone is not known', '+08:00');   // an IANA name, never a fixed offset
    }

    // ---- the previous period ---------------------------------------------------------------------------------

    public function test_the_previous_period_has_the_same_length_and_ends_the_day_before(): void
    {
        $r = $this->custom('2026-10-01', '2026-10-28');
        $p = $r->previous();

        $this->assertSame(28, $r->days());
        $this->assertSame(28, $p->days());
        $this->assertSame(['2026-09-03', '2026-09-30'], [$p->from->format('Y-m-d'), $p->to->format('Y-m-d')]);
        $this->assertSame('23:59:59', $p->to->format('H:i:s'));
        $this->assertSame(self::KL, $p->timezone);
    }

    public function test_the_previous_period_may_reach_before_any_data_without_being_refused(): void
    {
        $r = $this->custom('2025-10-28', '2026-10-28');   // 366 days: the previous one is another 366 days
        $this->assertSame(366, $r->previous()->days());
    }

    // ---- buckets ----------------------------------------------------------------------------------------------

    public function test_the_bucket_changes_at_31_and_180_days(): void
    {
        $bucket = fn (string $from) => $this->custom($from, '2026-10-28')->bucket();

        $this->assertSame('day', $bucket('2026-09-28'));     // 31 days
        $this->assertSame('week', $bucket('2026-09-27'));    // 32 days
        $this->assertSame('week', $bucket('2026-05-02'));    // 180 days
        $this->assertSame('month', $bucket('2026-05-01'));   // 181 days
    }

    public function test_daily_buckets_run_from_midnight_to_the_last_second_in_the_admins_zone(): void
    {
        $buckets = $this->custom('2026-10-01', '2026-10-03')->buckets();

        $this->assertCount(3, $buckets);
        $this->assertSame(['2026-09-30 16:00:00', '2026-10-01 15:59:59'], [AnalyticsRange::utc($buckets[0]['start']), AnalyticsRange::utc($buckets[0]['end'])]);
        $this->assertSame(['2026-10-01 16:00:00', '2026-10-02 15:59:59'], [AnalyticsRange::utc($buckets[1]['start']), AnalyticsRange::utc($buckets[1]['end'])]);
        $this->assertSame('1 Oct', $buckets[0]['label']);

        // The same three days for an admin in UTC are eight hours later.
        $utc = $this->custom('2026-10-01', '2026-10-03', 'UTC')->buckets();
        $this->assertSame(['2026-10-01 00:00:00', '2026-10-01 23:59:59'], [AnalyticsRange::utc($utc[0]['start']), AnalyticsRange::utc($utc[0]['end'])]);
    }

    public function test_weekly_buckets_run_monday_to_sunday_and_the_first_and_last_are_cut_to_the_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00', self::KL));

        $b = $this->custom('2026-09-01', '2026-10-31')->buckets();   // 1 Sep is a Tuesday, 31 Oct a Saturday

        $this->assertCount(9, $b);
        $this->assertSame(['2026-09-01', '2026-09-06'], [$b[0]['start']->format('Y-m-d'), $b[0]['end']->format('Y-m-d')], 'cut at the start: Tuesday to Sunday');
        $this->assertSame(['2026-09-07', '2026-09-13'], [$b[1]['start']->format('Y-m-d'), $b[1]['end']->format('Y-m-d')]);
        $this->assertSame('Monday', $b[1]['start']->format('l'));
        $this->assertSame('Sunday', $b[1]['end']->format('l'));
        $this->assertSame(['2026-10-26', '2026-10-31'], [$b[8]['start']->format('Y-m-d'), $b[8]['end']->format('Y-m-d')], 'cut at the end: Monday to Saturday');
        $this->assertSame('Week of 7 Sep', $b[1]['label']);
    }

    public function test_monthly_buckets_are_calendar_months_cut_to_the_range(): void
    {
        $b = $this->custom('2026-01-15', '2026-10-28')->buckets();

        $this->assertCount(10, $b);
        $this->assertSame(['2026-01-15', '2026-01-31'], [$b[0]['start']->format('Y-m-d'), $b[0]['end']->format('Y-m-d')]);
        $this->assertSame(['2026-02-01', '2026-02-28'], [$b[1]['start']->format('Y-m-d'), $b[1]['end']->format('Y-m-d')]);
        $this->assertSame(['2026-10-01', '2026-10-28'], [$b[9]['start']->format('Y-m-d'), $b[9]['end']->format('Y-m-d')]);
        $this->assertSame('Jan 2026', $b[0]['label']);
    }

    public function test_buckets_leave_no_gap_and_never_overlap(): void
    {
        foreach ([['2026-10-01', '2026-10-28'], ['2026-09-01', '2026-10-28'], ['2026-01-15', '2026-10-28'], ['2025-10-28', '2026-10-28']] as [$from, $to]) {
            $range = $this->custom($from, $to);
            $buckets = $range->buckets();

            $this->assertTrue($buckets[0]['start']->equalTo($range->from), "{$from}");
            $this->assertTrue(end($buckets)['end']->equalTo($range->to), "{$to}");

            for ($i = 1; $i < count($buckets); $i++) {
                $this->assertSame(
                    $buckets[$i - 1]['end']->addDay()->startOfDay()->toIso8601String(),
                    $buckets[$i]['start']->toIso8601String(),
                    "bucket {$i} of {$from}..{$to} starts exactly where the one before ended"
                );
            }
        }
    }

    public function test_a_zone_with_daylight_saving_still_gives_whole_calendar_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00', 'America/New_York'));

        $r = AnalyticsRange::custom('2026-02-20', '2026-03-31', 'America/New_York');   // the clocks change on Sunday 8 March

        $this->assertSame([40, 'week'], [$r->days(), $r->bucket()]);

        $b = $r->buckets();
        $this->assertSame(['2026-03-02 00:00:00', '2026-03-08 23:59:59'], [$b[2]['start']->format('Y-m-d H:i:s'), $b[2]['end']->format('Y-m-d H:i:s')], 'the short day (23 hours) is inside this week');
        $this->assertSame('2026-03-09 00:00:00', $b[3]['start']->format('Y-m-d H:i:s'), 'the next week starts at 00:00 on the dot');
        $this->assertSame(['-05:00', '-04:00'], [$b[2]['start']->format('P'), $b[3]['start']->format('P')], 'the offset changed, the days did not');
    }

    // ---- the cache key ---------------------------------------------------------------------------------------

    public function test_the_cache_key_depends_on_the_days_and_on_the_timezone(): void
    {
        $a = $this->custom('2026-10-01', '2026-10-28')->cacheKey();

        $this->assertSame($a, $this->custom('2026-10-01', '2026-10-28')->cacheKey());
        $this->assertNotSame($a, $this->custom('2026-10-01', '2026-10-27')->cacheKey());
        $this->assertNotSame($a, $this->custom('2026-10-02', '2026-10-28')->cacheKey());
        $this->assertNotSame($a, $this->custom('2026-10-01', '2026-10-28', 'UTC')->cacheKey(), 'the same days in another timezone are another report');
    }

    public function test_utc_text_is_independent_of_the_zone_the_moment_was_made_in(): void
    {
        $this->assertSame('2026-10-01 00:00:00', AnalyticsRange::utc(CarbonImmutable::parse('2026-10-01 08:00:00', self::KL)));
        $this->assertSame('2026-10-01 00:00:00', AnalyticsRange::utc(CarbonImmutable::parse('2026-09-30 20:00:00', 'America/New_York')));
    }
}
