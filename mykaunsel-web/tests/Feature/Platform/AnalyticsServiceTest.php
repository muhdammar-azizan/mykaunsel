<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\AnalyticsRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every figure of the Analytics page, from small known data. "Now" is 28 October 2026, 12:00 in Kuala Lumpur.
 * Times given for approvals and applications are UTC (as the database keeps them); times of sessions are Malaysian wall-clock times.
 */
class AnalyticsServiceTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const KL = 'Asia/Kuala_Lumpur';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', self::KL));
        config(['analytics.small_number_threshold' => 5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(string $from = '2026-10-01', string $to = '2026-10-28', string $tz = self::KL): array
    {
        return app(AnalyticsService::class)->report(AnalyticsRange::custom($from, $to, $tz));
    }

    private function daysOf(int $n): int
    {
        return $n * 86400;
    }

    // =============================================================================================================
    //  Cards: organizations and counselors
    // =============================================================================================================

    public function test_the_organizations_card_counts_approved_ones_without_the_platform_and_counts_the_undated_in_both_periods(): void
    {
        $this->platform()->forceFill(['reviewed_at' => '2026-01-01 00:00:00'])->saveQuietly();      // the virtual organization: never counted
        $this->org('university', 'active', '2026-08-10 02:00:00');                                  // before the previous period
        $this->org('university', 'active', '2026-09-15 02:00:00');                                  // in the previous period
        $this->org('university', 'suspended', '2026-10-05 02:00:00');                               // approved, now suspended
        $this->org('corporate', 'active', '2026-10-20 02:00:00');
        $this->org('clinic', 'active', null);                                                       // approved, no approval date recorded
        $this->org('university', 'active', '2026-10-28 15:59:59');                                  // 23:59:59 on the last day in Kuala Lumpur: in
        $this->org('university', 'active', '2026-10-28 16:00:00');                                  // 00:00:00 the next day: out
        $this->org('university', 'active', '2026-09-30 15:59:59');                                  // last second of the previous period: in both
        $this->org('university', 'active', '2026-09-30 16:00:00');                                  // first second of this range: only in this one
        $this->org('university', 'pending', null, '2026-10-02 00:00:00');
        $this->org('corporate', 'rejected', '2026-10-02 00:00:00', '2026-10-01 00:00:00');

        $card = $this->report()['cards']['organizations'];

        $this->assertSame(8, $card['value']);
        $this->assertSame(4, $card['previous']);
        $this->assertSame(['percent' => 100.0, 'direction' => 'up'], $card['trend']);
        $this->assertSame(1, $card['suspended'], 'suspended right now, among the counted');
        $this->assertSame(1, $card['undated'], '"1 without a recorded approval date"');
    }

    public function test_the_counselors_card_splits_independent_and_organization_and_counts_suspended(): void
    {
        $this->counselor('independent', 'approved', '2026-09-10 02:00:00');
        $this->counselor('independent', 'approved', '2026-10-12 02:00:00');
        $this->counselor('independent', 'suspended', '2026-07-01 02:00:00');
        $this->counselor('independent', 'pending', null, '2026-10-02 00:00:00');
        $this->counselor('independent', 'rejected', '2026-10-03 00:00:00', '2026-10-01 00:00:00');
        $this->counselor('organization', 'approved');   // organization counselors have no approval flow, so no date
        $this->counselor('organization', 'approved');

        $card = $this->report()['cards']['counselors'];

        $this->assertSame(5, $card['value']);
        $this->assertSame(4, $card['previous'], 'the one approved on 12 October was not there yet; the two without a date were');
        $this->assertSame(['percent' => 25.0, 'direction' => 'up'], $card['trend']);
        $this->assertSame([3, 2, 1, 2], [$card['independent'], $card['organization'], $card['suspended'], $card['undated']]);
    }

    public function test_a_trend_is_a_dash_when_the_previous_value_is_zero_and_never_divides_by_zero(): void
    {
        $this->org('university', 'active', '2026-10-10 02:00:00');
        $c = $this->counselor('organization', 'approved', '2026-10-10 02:00:00');
        $o = $this->org('university', 'active', '2026-10-10 02:00:00');
        $this->sessions(3, $c, $o);                         // 3 now, none before

        $cards = $this->report()['cards'];

        $this->assertSame(['percent' => null, 'direction' => null], $cards['organizations']['trend']);
        $this->assertSame(0, $cards['organizations']['previous']);
        $this->assertSame(['percent' => null, 'direction' => null], $cards['sessions_completed']['trend']);
        $this->assertSame(3, $cards['sessions_completed']['value']);
        $this->assertSame(['percent' => null, 'direction' => null], $cards['no_show_rate']['trend']);

        // Nothing now but something before is a real fall: -100%.
        $this->booking($c, $o, 'completed', '2026-09-10 10:00');
        $this->booking($c, $o, 'completed', '2026-09-11 10:00');
        DB::table('bookings')->update(['status' => 'confirmed']);
        $this->booking($c, $o, 'completed', '2026-09-12 10:00');

        $fall = $this->report()['cards']['sessions_completed'];
        $this->assertSame([0, 1], [$fall['value'], $fall['previous']]);
        $this->assertSame(['percent' => -100.0, 'direction' => 'down'], $fall['trend']);
    }

    // =============================================================================================================
    //  Cards: sessions and no-shows
    // =============================================================================================================

    private function sessionFixture(): void
    {
        $o = $this->org('university', 'active', '2026-01-01 00:00:00');
        $c = $this->counselor('organization', 'approved');

        foreach (['2026-10-01 00:00', '2026-10-28 23:59', '2026-10-15 10:00', '2026-10-15 11:00'] as $at) {
            $this->booking($c, $o, 'completed', $at);                      // 4 in this range
        }
        foreach (['2026-09-30 23:59', '2026-09-03 00:00', '2026-09-10 10:00'] as $at) {
            $this->booking($c, $o, 'completed', $at);                      // 3 in the previous one
        }
        $this->booking($c, $o, 'completed', '2026-09-02 23:59');          // the minute before the previous period
        $this->booking($c, $o, 'completed', '2026-10-29 00:00');          // the minute after this range

        $this->sessions(2, $c, $o, 'confirmed');                           // booked, not done: never counted
        $this->booking($c, $o, 'cancelled', '2026-10-12 10:00');
        $this->booking($c, $o, 'no_show', '2026-10-13 10:00');
        $this->booking($c, $o, 'completed', null);                        // no calendar entry: no date
        $this->booking($c, $o, 'completed', null);
    }

    public function test_sessions_are_counted_by_session_date_and_only_when_explicitly_completed(): void
    {
        $this->sessionFixture();

        $report = $this->report();
        $card = $report['cards']['sessions_completed'];

        $this->assertSame([4, 3], [$card['value'], $card['previous']]);
        $this->assertSame(['percent' => 33.3, 'direction' => 'up'], $card['trend']);
        $this->assertSame(2, $report['notes']['sessions_without_date'], 'the two with no calendar entry are left out and reported');
        $this->assertTrue($report['notes']['any_session_outcomes']);
    }

    public function test_the_same_sessions_fall_in_different_days_for_an_admin_in_utc(): void
    {
        $this->sessionFixture();

        // 00:00 on 1 October in Kuala Lumpur is 16:00 on 30 September in UTC, and 00:00 on 29 October in KL is 16:00 on 28 October in UTC.
        $card = $this->report('2026-10-01', '2026-10-28', 'UTC')['cards']['sessions_completed'];

        $this->assertSame(4, $card['value'], '23:59 on the 28th, two on the 15th, and the one at 00:00 on the 29th (still the 28th in UTC)');
        $this->assertSame(3, $card['previous'], 'the one at 00:00 on 1 October is still 30 September in UTC');
    }

    public function test_the_no_show_rate_leaves_cancelled_out_and_shows_the_cancelled_count_for_the_tooltip(): void
    {
        $this->sessionFixture();

        $card = $this->report()['cards']['no_show_rate'];

        $this->assertSame(20.0, $card['value'], '1 no-show out of 4 completed + 1 no-show');
        $this->assertSame([4, 1, 1], [$card['completed'], $card['no_show'], $card['cancelled_excluded']]);
        $this->assertTrue($card['has_data']);
        $this->assertSame(0.0, $card['previous'], '0 no-shows out of 3');
        $this->assertSame(['percent' => null, 'direction' => null], $card['trend'], 'a previous rate of zero gives "—", not a division by zero');
    }

    public function test_the_no_show_rate_is_unknown_not_zero_when_there_is_nothing_to_divide(): void
    {
        $c = $this->counselor('organization', 'approved');
        $o = $this->org('university', 'active', '2026-01-01 00:00:00');

        $empty = $this->report()['cards']['no_show_rate'];
        $this->assertNull($empty['value']);
        $this->assertFalse($empty['has_data']);

        $this->booking($c, $o, 'cancelled', '2026-10-12 10:00');          // only cancelled: still nothing to divide
        $this->booking($c, $o, 'confirmed', '2026-10-13 10:00');
        $onlyCancelled = $this->report()['cards']['no_show_rate'];
        $this->assertNull($onlyCancelled['value']);
        $this->assertSame(1, $onlyCancelled['cancelled_excluded']);

        $this->booking($c, $o, 'no_show', '2026-10-14 10:00');
        $this->assertSame(100.0, $this->report()['cards']['no_show_rate']['value']);

        $this->booking($c, $o, 'completed', '2026-10-15 10:00');
        $this->booking($c, $o, 'completed', '2026-10-16 10:00');
        $this->booking($c, $o, 'completed', '2026-10-17 10:00');
        $this->assertSame(25.0, $this->report()['cards']['no_show_rate']['value'], '1 of 4');
    }

    public function test_a_session_is_never_assumed_completed_because_its_time_has_passed(): void
    {
        $c = $this->counselor('organization', 'approved');
        $o = $this->org('university', 'active', '2026-01-01 00:00:00');
        $this->sessions(5, $c, $o, 'confirmed');      // all in the past, nobody marked them

        $report = $this->report();

        $this->assertSame(0, $report['cards']['sessions_completed']['value']);
        $this->assertFalse($report['notes']['any_session_outcomes'], 'this is what makes the page say "No session data yet"');
        $this->assertFalse($report['sessions_by_type']['has_data']);
    }

    // =============================================================================================================
    //  Growth chart
    // =============================================================================================================

    private function growthFixture(): void
    {
        $this->org('university', 'active', '2026-09-20 02:00:00');       // before the range: the starting level
        $this->org('university', 'active', '2026-10-03 02:00:00');       // 3 Oct 10:00 in KL
        $this->org('corporate', 'active', '2026-10-05 17:00:00');        // 6 Oct 01:00 in KL, but 5 Oct 17:00 in UTC
        $this->org('clinic', 'active', null);                            // approved, no date
        $this->counselor('independent', 'approved', '2026-10-02 02:00:00');
        $this->counselor('organization', 'approved');                    // no date
    }

    public function test_the_growth_chart_is_cumulative_at_the_end_of_each_day_of_the_admins_timezone(): void
    {
        $this->growthFixture();

        $growth = $this->report('2026-10-01', '2026-10-07')['growth'];

        $this->assertSame(['1 Oct', '2 Oct', '3 Oct', '4 Oct', '5 Oct', '6 Oct', '7 Oct'], array_column($growth['points'], 'label'));
        $this->assertSame([2, 2, 3, 3, 3, 4, 4], array_column($growth['points'], 'organizations'), 'the 6 October approval counts from the 6th in Kuala Lumpur');
        $this->assertSame([1, 2, 2, 2, 2, 2, 2], array_column($growth['points'], 'counselors'));
        $this->assertSame(['organizations' => 1, 'counselors' => 1], $growth['undated']);
        $this->assertFalse($growth['empty']);

        $utc = $this->report('2026-10-01', '2026-10-07', 'UTC')['growth'];
        $this->assertSame([2, 2, 3, 3, 4, 4, 4], array_column($utc['points'], 'organizations'), 'the same approval is on the 5th for an admin in UTC');
    }

    public function test_the_growth_chart_is_weekly_and_monthly_for_longer_ranges_and_ends_at_the_card_value(): void
    {
        $this->growthFixture();

        $weekly = $this->report('2026-09-01', '2026-10-28');
        $this->assertSame('week', $weekly['range']['bucket']);
        $this->assertSame(9, count($weekly['growth']['points']));
        $this->assertSame($weekly['cards']['organizations']['value'], end($weekly['growth']['points'])['organizations']);
        $this->assertSame($weekly['cards']['counselors']['value'], end($weekly['growth']['points'])['counselors']);

        $monthly = $this->report('2026-01-15', '2026-10-28');
        $this->assertSame('month', $monthly['range']['bucket']);
        $this->assertSame(10, count($monthly['growth']['points']));
        $this->assertSame('Oct 2026', end($monthly['growth']['points'])['label']);
        $this->assertSame(4, end($monthly['growth']['points'])['organizations']);
    }

    public function test_the_growth_chart_is_empty_only_when_both_lines_are_zero_all_the_way(): void
    {
        $this->assertTrue($this->report()['growth']['empty']);

        $this->org('university', 'pending', null);         // not approved: still nothing to draw
        $this->assertTrue($this->report()['growth']['empty']);

        $this->org('university', 'active', '2026-05-01 02:00:00');   // flat, but not zero
        $this->assertFalse($this->report()['growth']['empty']);
    }

    // =============================================================================================================
    //  Sessions by organization type
    // =============================================================================================================

    public function test_sessions_by_type_count_the_four_kinds_and_hide_one_to_four(): void
    {
        $c = $this->counselor('organization', 'approved');
        $this->sessions(6, $c, $this->org('university', 'active'));
        $this->sessions(5, $c, $this->org('corporate', 'active'));
        $this->sessions(2, $c, $this->org('clinic', 'active'));
        $this->sessions(7, $c, $this->platform());               // independent practice is the virtual platform organization
        $this->sessions(3, $c, $this->org('university', 'active'), 'cancelled');   // not completed: not counted

        $chart = $this->report()['sessions_by_type'];
        $byKey = collect($chart['items'])->keyBy('key');

        $this->assertSame(['university', 'corporate', 'clinic', 'independent'], array_column($chart['items'], 'key'), 'the fifth bar is only there when needed');
        $this->assertSame([6, '6', false], [$byKey['university']['count'], $byKey['university']['display'], $byKey['university']['suppressed']]);
        $this->assertSame([5, '5'], [$byKey['corporate']['count'], $byKey['corporate']['display']], 'exactly the threshold is shown');
        $this->assertSame([null, '<5', true], [$byKey['clinic']['count'], $byKey['clinic']['display'], $byKey['clinic']['suppressed']], 'a hidden count carries no number at all');
        $this->assertSame([7, 'Independent'], [$byKey['independent']['count'], $byKey['independent']['label']]);
    }

    public function test_zero_is_shown_as_zero_and_the_bar_for_no_organization_appears_only_when_there_is_one(): void
    {
        $c = $this->counselor('organization', 'approved');
        $this->sessions(6, $c, $this->org('university', 'active'));

        $byKey = collect($this->report()['sessions_by_type']['items'])->keyBy('key');
        $this->assertSame([0, '0', false], [$byKey['clinic']['count'], $byKey['clinic']['display'], $byKey['clinic']['suppressed']]);
        $this->assertFalse($byKey->has('none'));

        $this->booking($c, null, 'completed', '2026-10-10 10:00');         // a booking with no organization at all
        $none = collect($this->report()['sessions_by_type']['items'])->keyBy('key')['none'];
        $this->assertSame(['No organization recorded', null, '<5'], [$none['label'], $none['count'], $none['display']], 'the same "<5" rule');
    }

    // =============================================================================================================
    //  Specializations
    // =============================================================================================================

    private function specializationFixture(): void
    {
        $oa = $this->counselor('organization', 'approved', null, null, 1, ['Stres & Kebimbangan', 'Kerjaya']);
        $ob = $this->counselor('organization', 'approved', null, null, 1, ['stres  &  kebimbangan ', 'kerjaya']);   // spacing and capitals differ
        $ia = $this->counselor('independent', 'approved', '2026-05-01 00:00:00', '2026-04-30 00:00:00', 1, ['Stres & Kebimbangan', 'Trauma']);
        $ib = $this->counselor('independent', 'approved', '2026-05-01 00:00:00', '2026-04-30 00:00:00', 1, ['Trauma', 'trauma']);   // the same one written twice
        $ic = $this->counselor('independent', 'suspended', '2026-05-01 00:00:00', '2026-04-30 00:00:00', 1, []);
        $this->counselor('independent', 'pending', null, '2026-10-01 00:00:00', 1, ['Only Pending']);

        $this->sessions(6, $oa, $this->org('university', 'active'));
        $this->sessions(5, $ob, $this->org('corporate', 'active'));
        $this->sessions(2, $ob, $this->org('clinic', 'active'));
        $this->sessions(2, $ia, $this->platform());
        $this->sessions(2, $ib, $this->platform());
        $this->sessions(3, $ic, $this->platform());
    }

    public function test_a_session_counts_once_for_each_distinct_specialization_of_its_counselor(): void
    {
        $this->specializationFixture();

        $view = $this->report()['specializations']['sessions'];
        $rows = collect($view['items']);

        $this->assertTrue($view['has_data']);
        // Stres: 6 (first) + 5 + 2 (second) + 2 (third) = 15. Kerjaya: 6 + 7 = 13. Trauma: 2 + 2 = 4, although the fourth wrote it twice.
        $this->assertSame([15, 13], [$rows[0]['count'], $rows[1]['count']]);
        $this->assertSame(['Stres & Kebimbangan', 'Kerjaya'], [$rows[0]['label'], $rows[1]['label']], 'sorted by sessions; spelling variants of one thing are merged');
        $this->assertSame(3, $rows->count());
    }

    public function test_small_groups_go_into_other_and_a_small_other_is_hidden_too(): void
    {
        $this->specializationFixture();

        $other = collect($this->report()['specializations']['sessions']['items'])->last();

        $this->assertSame(['Other (fewer than 5)', true], [$other['label'], $other['other']]);
        $this->assertSame([null, '<5', true], [$other['count'], $other['display'], $other['suppressed']], 'Trauma has 4 sessions and is the only small group: Other would give its count away');

        // With a second small group the total is 4 + 3 = 7: big enough to show.
        $d = $this->counselor('independent', 'approved', '2026-05-01 00:00:00', '2026-04-30 00:00:00', 1, ['Penagihan']);
        $this->sessions(3, $d, $this->platform());

        $other = collect($this->report()['specializations']['sessions']['items'])->last();
        $this->assertSame([7, '7', false], [$other['count'], $other['display'], $other['suppressed']]);
        $this->assertNotContains('Trauma', array_column($this->report()['specializations']['sessions']['items'], 'label'), 'the names of small groups are never listed');
        $this->assertNotContains('Penagihan', array_column($this->report()['specializations']['sessions']['items'], 'label'));
    }

    public function test_a_group_of_exactly_the_threshold_is_listed_and_one_fewer_goes_into_other(): void
    {
        $five = $this->counselor('organization', 'approved', null, null, 1, ['Exactly Five']);
        $four = $this->counselor('organization', 'approved', null, null, 1, ['Just Four']);
        $this->sessions(5, $five, $this->org('university', 'active'));
        $this->sessions(4, $four, $this->org('university', 'active'));

        $items = $this->report()['specializations']['sessions']['items'];

        $this->assertSame(['Exactly Five', 'Other (fewer than 5)'], array_column($items, 'label'));
        $this->assertSame([5, null], [$items[0]['count'], $items[1]['count']], '5 is shown; the 4 behind "Other" is itself hidden');
    }

    public function test_the_offered_view_counts_approved_counselors_not_sessions(): void
    {
        $this->specializationFixture();
        config(['analytics.small_number_threshold' => 1]);     // nothing hidden, so the plain counts can be compared

        $offered = $this->report()['specializations']['offered'];
        $rows = collect($offered['items'])->keyBy('label');

        $this->assertTrue($offered['has_data']);
        $this->assertSame(3, $rows['Stres & Kebimbangan']['count'], 'three approved counselors list it (not 15 sessions)');
        $this->assertSame(2, $rows['Kerjaya']['count']);
        $this->assertSame(2, $rows['Trauma']['count'], 'the counselor who wrote it twice counts once');
        $this->assertFalse($rows->has('Only Pending'), 'a pending counselor offers nothing yet');
        $this->assertSame(['Stres & Kebimbangan', 'Kerjaya', 'Trauma'], array_column($offered['items'], 'label'), 'ties are broken by the alphabet');
    }

    public function test_the_offered_view_works_before_any_session_exists(): void
    {
        $this->counselor('organization', 'approved', null, null, 1, ['Kerjaya']);
        config(['analytics.small_number_threshold' => 1]);

        $report = $this->report();

        $this->assertFalse($report['specializations']['sessions']['has_data']);
        $this->assertSame([], $report['specializations']['sessions']['items']);
        $this->assertSame(1, $report['specializations']['offered']['items'][0]['count']);
    }

    public function test_the_label_of_a_merged_group_does_not_depend_on_the_order_rows_were_written(): void
    {
        config(['analytics.small_number_threshold' => 1]);
        $this->counselor('organization', 'approved', null, null, 1, ['zeta']);
        $this->counselor('organization', 'approved', null, null, 1, ['Zeta']);
        $this->counselor('organization', 'approved', null, null, 1, ['ZETA']);   // three spellings, one each: the alphabet decides

        $first = collect($this->report()['specializations']['offered']['items'])->pluck('label')->all();
        $this->assertSame(['ZETA'], $first, 'uppercase letters sort before lowercase ones');

        // Write the same rows again in the opposite order (different ids): the answer must not change.
        $rows = DB::table('counselor_specializations')->orderByDesc('id')->get(['counselor_profile_id', 'specialization'])->all();
        DB::table('counselor_specializations')->delete();
        foreach ($rows as $row) {
            DB::table('counselor_specializations')->insert(['counselor_profile_id' => $row->counselor_profile_id, 'specialization' => $row->specialization, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame($first, collect($this->report()['specializations']['offered']['items'])->pluck('label')->all());

        // The most common spelling wins over the alphabet.
        $this->counselor('organization', 'approved', null, null, 1, ['zeta']);
        $this->assertSame(['zeta'], collect($this->report()['specializations']['offered']['items'])->pluck('label')->all(), '"zeta" was written twice, the others once');
    }

    // =============================================================================================================
    //  Application outcomes
    // =============================================================================================================

    public function test_organization_outcomes_add_up_and_the_median_of_an_even_number_of_decisions_is_the_middle_pair(): void
    {
        $this->platform()->forceFill(['submitted_at' => '2026-10-02 00:00:00', 'reviewed_at' => '2026-10-03 00:00:00'])->saveQuietly();   // never counted

        $this->org('university', 'active', '2026-10-04 02:00:00', '2026-10-02 02:00:00');       // 2 days
        $this->org('corporate', 'suspended', '2026-10-09 02:00:00', '2026-10-03 02:00:00');     // 6 days: approved, since suspended
        $this->org('clinic', 'rejected', '2026-10-06 02:00:00', '2026-10-05 02:00:00');         // 1 day
        $this->org('university', 'pending', null, '2026-10-20 02:00:00');
        $this->org('university', 'pending', '2026-10-10 02:00:00', '2026-10-22 02:00:00', 2);   // applied again; the old decision date is stale
        $this->org('university', 'active', '2026-10-03 02:00:00', '2026-09-20 02:00:00');       // submitted before the range: not in the cohort, but decided in it: 13 days

        $o = $this->report()['outcomes']['organizations'];

        $this->assertSame([5, 2, 1, 2], [$o['submitted'], $o['approved'], $o['rejected'], $o['pending']]);
        $this->assertSame($o['submitted'], $o['approved'] + $o['rejected'] + $o['pending'], 'approved, rejected and pending add up to submitted');
        $this->assertSame([40.0, 20.0, 40.0], [$o['approved_percent'], $o['rejected_percent'], $o['pending_percent']]);
        $this->assertSame(4, $o['decisions'], 'the stale decision before the new submission is not one');
        $this->assertSame($this->daysOf(4), $o['median_decision_seconds'], 'between 2 and 6 days: 4 days');
        $this->assertNull($o['resubmissions'], '"—, not tracked yet": nothing records an organization applying again');
    }

    public function test_the_median_of_an_odd_number_of_decisions_is_the_middle_one(): void
    {
        $this->org('university', 'active', '2026-10-04 02:00:00', '2026-10-02 02:00:00');    // 2 days
        $this->org('corporate', 'active', '2026-10-09 02:00:00', '2026-10-03 02:00:00');     // 6 days
        $this->org('clinic', 'rejected', '2026-10-06 02:00:00', '2026-10-05 02:00:00');      // 1 day

        $this->assertSame($this->daysOf(2), $this->report()['outcomes']['organizations']['median_decision_seconds']);
    }

    public function test_counselor_outcomes_are_for_independent_counselors_only_and_count_resubmissions(): void
    {
        $this->counselor('independent', 'approved', '2026-10-03 02:00:00', '2026-10-02 02:00:00');                // 1 day
        $this->counselor('independent', 'rejected', '2026-10-07 02:00:00', '2026-10-04 02:00:00');               // 3 days
        $this->counselor('independent', 'pending', '2026-09-30 02:00:00', '2026-10-08 02:00:00', 2);             // resubmitted: stale decision
        $this->counselor('independent', 'suspended', '2026-10-10 02:00:00', '2026-10-09 02:00:00');             // 1 day, approved earlier
        $this->counselor('independent', 'pending', null, null);                                                // documents not sent: no submission date
        $this->counselor('organization', 'approved', '2026-10-06 02:00:00', '2026-10-05 02:00:00');             // an organization counselor: not in this cohort

        $c = $this->report()['outcomes']['counselors'];

        $this->assertSame([4, 2, 1, 1], [$c['submitted'], $c['approved'], $c['rejected'], $c['pending']]);
        $this->assertSame([50.0, 25.0, 25.0], [$c['approved_percent'], $c['rejected_percent'], $c['pending_percent']]);
        $this->assertSame(3, $c['decisions']);
        $this->assertSame($this->daysOf(1), $c['median_decision_seconds'], '1, 1 and 3 days: the middle one');
        $this->assertSame(1, $c['resubmissions']);
    }

    public function test_without_applications_the_percentages_and_the_median_are_unknown_not_zero(): void
    {
        $o = $this->report()['outcomes']['organizations'];

        $this->assertSame([0, null, null, null, 0, null], [$o['submitted'], $o['approved_percent'], $o['rejected_percent'], $o['pending_percent'], $o['decisions'], $o['median_decision_seconds']]);
        $this->assertSame(0, $this->report()['outcomes']['counselors']['resubmissions']);
    }

    public function test_the_application_cohort_follows_the_range_in_the_admins_timezone(): void
    {
        $this->org('university', 'pending', null, '2026-09-30 15:59:59');   // 23:59:59 on 30 September in Kuala Lumpur: before the range
        $this->org('university', 'pending', null, '2026-09-30 16:00:00');   // 00:00:00 on 1 October: in

        $this->assertSame(1, $this->report()['outcomes']['organizations']['submitted']);
        $this->assertSame(2, $this->report('2026-09-30', '2026-10-28', self::KL)['outcomes']['organizations']['submitted']);
        $this->assertSame(2, $this->report('2026-09-30', '2026-09-30', 'UTC')['outcomes']['organizations']['submitted'], 'both are 30 September for an admin in UTC');
    }

    // =============================================================================================================
    //  Privacy, empty data, hooks
    // =============================================================================================================

    public function test_the_report_holds_no_personal_data(): void
    {
        $this->specializationFixture();
        $this->growthFixture();
        $this->org('clinic', 'rejected', '2026-10-06 02:00:00', '2026-10-05 02:00:00');

        $report = $this->report();
        $json = json_encode($report);

        foreach (['Fixture', '@example.test', 'FIX-KB', 'FIX-PA', 'example.test'] as $personal) {
            $this->assertStringNotContainsString($personal, $json, $personal);
        }

        // Not one key that could carry an identity, at any depth of the result.
        $forbidden = ['id', 'user_id', 'org_id', 'counselor', 'counselor_user_id', 'email', 'name', 'kb_number', 'pa_number', 'booking_id', 'public_id'];
        $walk = function (array $data) use (&$walk, $forbidden) {
            foreach ($data as $key => $value) {
                $this->assertNotContains($key, $forbidden);
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($report);
    }

    public function test_a_hidden_count_is_not_anywhere_in_the_report(): void
    {
        $c = $this->counselor('organization', 'approved', null, null, 1, ['Rare Thing']);
        $this->sessions(3, $c, $this->org('clinic', 'active'));      // 3 clinic sessions, 3 sessions of a rare specialization

        $report = $this->report();

        $clinic = collect($report['sessions_by_type']['items'])->firstWhere('key', 'clinic');
        $this->assertSame([null, '<5'], [$clinic['count'], $clinic['display']]);
        $this->assertSame([null, '<5'], [collect($report['specializations']['sessions']['items'])->last()['count'], collect($report['specializations']['sessions']['items'])->last()['display']]);
        $this->assertStringNotContainsString('Rare Thing', json_encode($report['specializations']['sessions']));
    }

    public function test_an_empty_database_gives_a_complete_report_of_zeros(): void
    {
        $report = $this->report();

        $this->assertSame([0, 0, 0], [$report['cards']['organizations']['value'], $report['cards']['counselors']['value'], $report['cards']['sessions_completed']['value']]);
        $this->assertNull($report['cards']['no_show_rate']['value']);
        $this->assertSame(['percent' => null, 'direction' => null], $report['cards']['organizations']['trend']);
        $this->assertTrue($report['growth']['empty']);
        $this->assertSame(28, count($report['growth']['points']));
        $this->assertSame([0, 0, 0, 0], array_column($report['sessions_by_type']['items'], 'count'));
        $this->assertSame([], $report['specializations']['sessions']['items']);
        $this->assertSame([], $report['specializations']['offered']['items']);
        $this->assertSame([0, 0], [$report['outcomes']['organizations']['submitted'], $report['outcomes']['counselors']['submitted']]);
        $this->assertSame([0, false], [$report['notes']['sessions_without_date'], $report['notes']['any_session_outcomes']]);
        $this->assertSame('day', $report['range']['bucket']);
    }

    public function test_the_dashboard_hooks_use_the_same_definitions(): void
    {
        $org = $this->org('university', 'active', '2026-10-01 02:00:00');
        $this->org('university', 'suspended', '2026-10-01 02:00:00');
        $this->org('university', 'pending');
        $this->platform();
        $oc = $this->counselor('organization', 'approved');
        $this->counselor('independent', 'approved', '2026-10-01 02:00:00');
        $this->counselor('independent', 'pending', null, '2026-10-01 00:00:00');

        $service = app(AnalyticsService::class);
        $this->assertSame(2, $service->totalOrganizations(), 'active + suspended, the platform organization left out');
        $this->assertSame(2, $service->totalCounselors());
        $this->assertSame($service->report(AnalyticsRange::preset(30, self::KL))['cards']['organizations']['value'], $service->totalOrganizations());
        $this->assertSame(0, $service->totalOrganizations(Carbon::parse('2026-09-01', self::KL)), 'as of an earlier day, none of the dated ones were approved yet');

        foreach ([[MembershipRole::Student, MembershipStatus::Active], [MembershipRole::Staff, MembershipStatus::Active], [MembershipRole::Employee, MembershipStatus::Active], [MembershipRole::Student, MembershipStatus::Suspended], [MembershipRole::Counselor, MembershipStatus::Active]] as [$role, $status]) {
            Membership::create(['user_id' => User::factory()->create()->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);
        }
        $this->assertSame(3, $service->activeMembers());

        $this->booking($oc, $org, 'completed', '2026-10-01 00:00');      // first minute of the month in Kuala Lumpur
        $this->booking($oc, $org, 'completed', '2026-10-27 10:00');
        $this->booking($oc, $org, 'completed', '2026-09-30 23:59');
        $this->booking($oc, $org, 'no_show', '2026-10-10 10:00');
        $this->booking($oc, $org, 'confirmed', '2026-10-11 10:00');

        $this->assertSame(2, $service->completedSessionsThisMonth(self::KL));
        $this->assertSame(1, $service->completedSessionsThisMonth('UTC'), 'in UTC the first one is still 30 September');
    }
}
