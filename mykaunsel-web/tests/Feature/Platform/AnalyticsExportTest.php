<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Services\AnalyticsReports;
use App\Support\AnalyticsCsv;
use App\Support\AnalyticsRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Analytics step 3: the CSV export (long format, same numbers as the page, small numbers hidden, formulas made safe, audited with
 * the range and the section names only, rate limited), and the kept result with its Refresh.
 */
class AnalyticsExportTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00:00');
        Cache::flush();

        $platform = $this->platform();
        $this->admin = User::factory()->create(['name' => 'Admin Analitik', 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->withSession($this->session);
    }

    /** @return list<list<string>> the rows of the downloaded CSV (without the header) */
    private function csv(array $query = [], ?User $user = null): array
    {
        $response = $this->as($user)->get(route('platform.analytics.export', $query))->assertOk();
        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'a byte order mark so a spreadsheet reads UTF-8');

        $handle = fopen('php://memory', 'w+');
        fwrite($handle, substr($body, 3));
        rewind($handle);
        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        $this->assertSame(['section', 'label', 'period', 'value'], array_shift($rows));

        return $rows;
    }

    /** @return list<string> the value column of every row with this section and label */
    private function values(array $rows, string $section, string $label): array
    {
        return array_values(array_map(fn ($r) => $r[3], array_filter($rows, fn ($r) => $r[0] === $section && $r[1] === $label)));
    }

    private function exports(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::where('action', 'analytics.exported')->get();
    }

    // ---- the export ----------------------------------------------------------------------------------------------------------

    public function test_only_a_platform_admin_can_export(): void
    {
        $this->get(route('platform.analytics.export'))->assertRedirect();
        $this->post(route('platform.analytics.refresh'))->assertRedirect();

        $student = $this->actingAs($this->admin)->withSession(['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student']);
        $student->get(route('platform.analytics.export'))->assertForbidden();
        $student->post(route('platform.analytics.refresh'))->assertForbidden();

        $this->assertCount(0, $this->exports());
    }

    public function test_the_download_is_a_csv_with_a_name_that_says_the_range(): void
    {
        $this->as()->get(route('platform.analytics.export', ['range' => 7]))->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=analytics_20261014-20261020.csv');
    }

    public function test_the_first_lines_say_the_range_the_timezone_and_when_it_was_generated(): void
    {
        $rows = $this->csv(['range' => 7]);

        $this->assertSame(['Report', 'Date range', '', '2026-10-14 to 2026-10-20'], $rows[0]);
        $this->assertSame(['Report', 'Timezone', '', 'Asia/Kuala_Lumpur'], $rows[1]);
        // 12:00 UTC is 20:00 in Kuala Lumpur: the moment is written in the admin's own timezone.
        $this->assertSame(['Report', 'Generated at', '', '2026-10-20 20:00:00'], $rows[2]);
    }

    public function test_the_timezone_is_the_admins_own(): void
    {
        $this->admin->forceFill(['timezone' => 'America/New_York'])->save();

        $rows = $this->csv(['range' => 7]);

        $this->assertSame('America/New_York', $rows[1][3]);
        $this->assertSame('2026-10-20 08:00:00', $rows[2][3]);
    }

    public function test_it_carries_the_same_figures_as_the_page(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $corporate = $this->org('corporate', 'active', '2026-10-05 08:00:00', '2026-10-01 08:00:00');
        $this->sessions(6, $counselor, $corporate, 'completed', 2, '2026-10');
        $this->sessions(2, $counselor, $corporate, 'no_show', 10, '2026-10');

        $rows = $this->csv(['range' => 30]);
        $report = app(AnalyticsReports::class)->get(AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur'))['report'];

        $this->assertSame([(string) $report['cards']['organizations']['value']], $this->values($rows, 'Approved organizations', 'Total'));
        $this->assertSame(['1'], $this->values($rows, 'Approved organizations', 'Total'));
        $this->assertSame(['1'], $this->values($rows, 'Approved counselors', 'Total'));
        $this->assertSame(['6'], $this->values($rows, 'Sessions completed', 'Total'));
        $this->assertSame(['25'], $this->values($rows, 'No-show rate', 'Rate (%)'));
        $this->assertSame(['6'], $this->values($rows, 'Sessions by organization type', 'Corporate'));
        $this->assertSame(['6'], $this->values($rows, 'Specializations (sessions)', 'Stress'));
        $this->assertSame(['1'], $this->values($rows, 'Application outcomes (organizations)', 'Submitted'));
        // Submitted 1 Oct, decided 5 Oct: four days.
        $this->assertSame(['96'], $this->values($rows, 'Application outcomes (organizations)', 'Median decision time (hours)'));
        $this->assertSame(['100'], $this->values($rows, 'Application outcomes (independent counselors)', 'Approved (%)'));
        $this->assertSame(['0'], $this->values($rows, 'Application outcomes (organizations)', 'Pending (%)'));
    }

    public function test_growth_has_one_line_per_series_per_chart_period_dated_by_its_first_day(): void
    {
        $rows = $this->csv(['range' => 7]);
        $growth = array_values(array_filter($rows, fn ($r) => $r[0] === 'Platform growth' && $r[1] === 'Approved organizations (running total)'));

        $this->assertCount(7, $growth);
        $this->assertSame('2026-10-14', $growth[0][2]);
        $this->assertSame('2026-10-20', $growth[6][2]);
    }

    public function test_a_small_count_is_written_as_less_than_five_and_never_as_a_number(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['Stress']);
        $university = $this->org('university', 'active', '2026-01-10 08:00:00');
        $corporate = $this->org('corporate', 'active', '2026-01-10 08:00:00');
        $this->sessions(3, $counselor, $university, 'completed', 2, '2026-10');
        $this->sessions(7, $counselor, $corporate, 'completed', 8, '2026-10');

        $rows = $this->csv(['range' => 30]);

        $this->assertSame(['<5'], $this->values($rows, 'Sessions by organization type', 'University'));
        $this->assertSame(['7'], $this->values($rows, 'Sessions by organization type', 'Corporate'));
        $this->assertSame(['0'], $this->values($rows, 'Sessions by organization type', 'Clinic'), 'zero identifies nobody and is written as 0');
    }

    public function test_rare_specializations_are_never_named(): void
    {
        $common = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['Anxiety']);
        $rare = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['Very Rare Specialty']);
        $org = $this->org('corporate', 'active', '2026-01-10 08:00:00');
        $this->sessions(6, $common, $org, 'completed', 2, '2026-10');
        $this->sessions(2, $rare, $org, 'completed', 10, '2026-10');

        $body = $this->as()->get(route('platform.analytics.export', ['range' => 30]))->streamedContent();
        $rows = $this->csv(['range' => 30]);

        $this->assertStringNotContainsString('Very Rare Specialty', $body, 'a specialization under the threshold is grouped, not named');
        $this->assertSame(['<5'], $this->values($rows, 'Specializations (sessions)', 'Other (fewer than 5)'));
        $this->assertSame(['6'], $this->values($rows, 'Specializations (sessions)', 'Anxiety'));
    }

    public function test_text_that_a_spreadsheet_could_read_as_a_formula_is_made_safe(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['=HYPERLINK("http://evil.example","x")', '+cmd', '-cmd', '@SUM(A1)']);
        $org = $this->org('corporate', 'active', '2026-01-10 08:00:00');
        $this->sessions(5, $counselor, $org, 'completed', 2, '2026-10');

        $labels = array_column(array_filter($this->csv(['range' => 30]), fn ($r) => $r[0] === 'Specializations (sessions)'), 1);

        foreach (['=HYPERLINK("http://evil.example","x")', '+cmd', '-cmd', '@SUM(A1)'] as $dangerous) {
            $this->assertContains("'".$dangerous, $labels);
            $this->assertNotContains($dangerous, $labels);
        }

        $this->assertSame("'=1+1", AnalyticsCsv::neutralise('=1+1'));
        $this->assertSame(-3.2, AnalyticsCsv::neutralise(-3.2), 'a real negative number is left alone');
        $this->assertSame('<5', AnalyticsCsv::neutralise('<5'));
    }

    public function test_the_file_never_holds_a_name_an_email_a_kb_number_or_an_id(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $org = $this->org('university', 'active', '2026-10-05 08:00:00', '2026-10-01 08:00:00');
        $this->sessions(6, $counselor, $org, 'completed', 2, '2026-10');

        $body = $this->as()->get(route('platform.analytics.export'))->streamedContent();

        foreach (['Fixture', 'example.test', 'FIX-KB', 'FIX-PA', 'Admin Analitik', 'MyKaunsel Platform', 'user_id', 'org_id'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_without_any_session_outcome_the_session_sections_say_so(): void
    {
        $rows = $this->csv();

        foreach (['Sessions completed', 'No-show rate', 'Sessions by organization type', 'Specializations (sessions)'] as $section) {
            $this->assertSame(['n/a'], $this->values($rows, $section, 'No session data yet'), $section);
        }

        $this->assertSame(['not tracked yet'], $this->values($rows, 'Application outcomes (organizations)', 'Resubmissions'));
    }

    public function test_an_invalid_range_is_not_exported_as_a_different_one(): void
    {
        $this->as()->get(route('platform.analytics.export', ['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertRedirect(route('platform.analytics', ['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']));

        $this->assertCount(0, $this->exports());
    }

    public function test_a_custom_range_is_exported(): void
    {
        $rows = $this->csv(['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10']);

        $this->assertSame('2026-10-01 to 2026-10-10', $rows[0][3]);
    }

    // ---- the audit entry -----------------------------------------------------------------------------------------------------

    public function test_an_export_is_recorded_with_the_range_and_the_section_names_only(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $org = $this->org('corporate', 'active', '2026-10-05 08:00:00');
        $this->sessions(6, $counselor, $org, 'completed', 2, '2026-10');

        $this->csv(['range' => 7]);

        $log = $this->exports()->sole();
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame(['format', 'from', 'to', 'timezone', 'sections'], array_keys($log->metadata));
        $this->assertSame(['csv', '2026-10-14', '2026-10-20', 'Asia/Kuala_Lumpur'], [$log->metadata['format'], $log->metadata['from'], $log->metadata['to'], $log->metadata['timezone']]);
        $this->assertContains('Approved organizations', $log->metadata['sections']);
        $this->assertContains('Platform growth', $log->metadata['sections']);
        $this->assertNotContains('Report', $log->metadata['sections']);
        $this->assertSame($log->metadata['sections'], array_values(array_unique($log->metadata['sections'])));
        $this->assertNull($log->target_id);

        // No figure, no specialization name, no organization type: only the section names and the range.
        $this->assertStringNotContainsString('Stress', json_encode($log->metadata));
        $this->assertStringNotContainsString('Corporate', json_encode($log->metadata));
    }

    public function test_viewing_the_page_or_refreshing_is_never_recorded(): void
    {
        $this->as()->get(route('platform.analytics'))->assertOk();
        $this->as()->post(route('platform.analytics.refresh'))->assertRedirect();

        $this->assertCount(0, $this->exports());
        $this->assertSame(0, AuditLog::where('action', 'like', 'analytics.%')->count());
    }

    public function test_the_export_is_rate_limited(): void
    {
        foreach (range(1, 6) as $i) {
            $this->as()->get(route('platform.analytics.export'))->assertOk();
        }

        $this->as()->get(route('platform.analytics.export'))->assertStatus(429);
        $this->assertCount(6, $this->exports());
    }

    public function test_the_audit_log_page_still_lists_the_entry_with_a_readable_label(): void
    {
        $this->csv();

        $this->as()->get(route('platform.audit-log'))->assertOk()->assertSee('Analytics exported');
    }

    // ---- the kept result and Refresh -----------------------------------------------------------------------------------------

    public function test_a_result_is_kept_so_a_second_look_does_not_recount(): void
    {
        $range = AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur');
        $reports = app(AnalyticsReports::class);

        $first = $reports->get($range);
        $this->org('university', 'active', '2026-10-05 08:00:00');
        $second = $reports->get($range);

        $this->assertSame(0, $first['report']['cards']['organizations']['value']);
        $this->assertSame(0, $second['report']['cards']['organizations']['value'], 'still the kept result');
        $this->assertEquals($first['generated_at'], $second['generated_at']);
    }

    public function test_refresh_forgets_it_and_the_next_look_counts_again(): void
    {
        $this->as()->get(route('platform.analytics', ['range' => 30]))->assertOk();
        $this->org('university', 'active', '2026-10-05 08:00:00');

        $this->assertSame(['0'], $this->values($this->csv(['range' => 30]), 'Approved organizations', 'Total'), 'the kept result is used');

        $this->as()->post(route('platform.analytics.refresh', ['range' => 30]))->assertRedirect(route('platform.analytics', ['range' => 30]));

        $this->assertSame(['1'], $this->values($this->csv(['range' => 30]), 'Approved organizations', 'Total'));
    }

    public function test_refresh_keeps_the_range_in_the_address_and_ignores_anything_else(): void
    {
        $this->as()->post(route('platform.analytics.refresh', ['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10', 'evil' => 'x']))
            ->assertRedirect(route('platform.analytics', ['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10']));
    }

    public function test_refresh_only_forgets_its_own_range(): void
    {
        $reports = app(AnalyticsReports::class);
        $thirty = AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur');
        $seven = AnalyticsRange::preset(7, 'Asia/Kuala_Lumpur');

        $reports->get($thirty);
        $reports->get($seven);
        $this->org('university', 'active', '2026-10-05 08:00:00');

        $this->as()->post(route('platform.analytics.refresh', ['range' => 7]));

        $this->assertSame(0, $reports->get($thirty)['report']['cards']['organizations']['value'], 'the 30 day result is still kept');
        $this->assertSame(1, $reports->get($seven)['report']['cards']['organizations']['value'], 'the 7 day result was worked out again');
    }

    public function test_two_timezones_never_share_a_result(): void
    {
        $reports = app(AnalyticsReports::class);
        $reports->get(AnalyticsRange::preset(7, 'Asia/Kuala_Lumpur'));

        $other = $reports->get(AnalyticsRange::preset(7, 'America/New_York'));

        $this->assertSame('America/New_York', $other['report']['range']['timezone']);
    }

    public function test_a_kept_result_ends_after_the_configured_minutes_and_zero_turns_it_off(): void
    {
        $range = AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur');
        $reports = app(AnalyticsReports::class);

        $reports->get($range);
        $this->org('university', 'active', '2026-10-05 08:00:00');

        Carbon::setTestNow(now()->addMinutes(4));
        $this->assertSame(0, $reports->get($range)['report']['cards']['organizations']['value']);

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame(1, $reports->get($range)['report']['cards']['organizations']['value'], 'five minutes have passed');

        config(['analytics.cache_minutes' => 0]);
        $this->org('university', 'active', '2026-10-06 08:00:00');
        $this->assertSame(2, $reports->get($range)['report']['cards']['organizations']['value'], 'nothing is kept when the setting is 0');
    }

    public function test_the_kept_result_holds_aggregates_only(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $org = $this->org('university', 'active', '2026-10-05 08:00:00', '2026-10-01 08:00:00');
        $this->sessions(3, $counselor, $org, 'completed', 2, '2026-10');

        $range = AnalyticsRange::preset(30, 'Asia/Kuala_Lumpur');
        app(AnalyticsReports::class)->get($range);

        $kept = json_encode(Cache::get($range->cacheKey()));

        foreach (['Fixture', 'example.test', 'FIX-KB'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, (string) $kept);
        }
    }

    public function test_the_page_shows_the_export_and_refresh_controls(): void
    {
        $this->as()->get(route('platform.analytics', ['range' => 7]))->assertOk()
            ->assertSee('Export CSV')
            ->assertSee(route('platform.analytics.export', ['range' => 7]), false)
            ->assertSee('action="'.route('platform.analytics.refresh', ['range' => 7]).'"', false);
    }
}
