<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The Analytics page (step 2): access, the range in the URL, the four cards and charts, the distinct empty states, small-number
 * suppression on the page, and that nothing identifying is ever printed. The numbers themselves are tested in AnalyticsServiceTest.
 */
class AnalyticsPageTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private User $admin;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 12:00:00');

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

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin)->withSession($this->session)->get(route('platform.analytics', $query));
    }

    public function test_only_a_platform_admin_can_open_it(): void
    {
        $this->get(route('platform.analytics'))->assertRedirect();

        $this->actingAs($this->admin)->withSession(['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student'])
            ->get(route('platform.analytics'))->assertForbidden();

        $this->page()->assertOk();
    }

    public function test_the_page_has_the_controls_cards_charts_and_the_note(): void
    {
        $this->page()->assertOk()
            ->assertSee('Analytics')
            ->assertSee('Last 7 days')->assertSee('Last 30 days')->assertSee('Last 90 days')
            ->assertSee('Refresh')->assertSee('Export')
            ->assertSee('Updated just now')
            ->assertSee('Approved organizations')->assertSee('Approved counselors')->assertSee('Sessions completed')->assertSee('No-show rate')
            ->assertSee('Platform growth')->assertSee('Completed sessions by organization type')->assertSee('Specializations')->assertSee('Application outcomes')
            ->assertSee('View as table')
            ->assertSee('Figures are calculated from live platform data. Demo data may be included in non-production environments.')
            ->assertSee(route('platform.analytics'), false);
    }

    public function test_the_sidebar_links_to_it(): void
    {
        $this->page()->assertSee('href="'.route('platform.analytics').'"', false);
    }

    public function test_the_default_range_is_thirty_days_and_the_choice_is_in_the_url(): void
    {
        $this->page()->assertSee('(30 days, Asia/Kuala_Lumpur)', false)->assertSee('21 Sep 2026 – 20 Oct 2026');

        $this->page(['range' => 7])->assertSee('(7 days, Asia/Kuala_Lumpur)', false)->assertSee('14 Oct 2026 – 20 Oct 2026');
        $this->page(['range' => 90])->assertSee('(90 days, Asia/Kuala_Lumpur)', false);
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $this->page(['range' => 'abc'])->assertOk()->assertSee('(30 days, Asia/Kuala_Lumpur)', false);
        $this->page(['range' => 45])->assertOk()->assertSee('(30 days, Asia/Kuala_Lumpur)', false);
        $this->page(['range' => ['x']])->assertOk()->assertSee('(30 days, Asia/Kuala_Lumpur)', false);
        $this->page(['range' => 'custom', 'from' => ['x'], 'to' => ['y']])->assertOk()->assertSee('Choose a valid start date and end date.');
    }

    public function test_a_custom_range_is_used(): void
    {
        $this->page(['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10'])
            ->assertOk()->assertSee('1 Oct 2026 – 10 Oct 2026')->assertSee('(10 days, Asia/Kuala_Lumpur)', false)->assertDontSee('role="alert"', false);
    }

    /** @dataProvider badRanges */
    public function test_a_bad_custom_range_shows_why_and_falls_back(array $query, string $message): void
    {
        $this->page(['range' => 'custom'] + $query)->assertOk()->assertSee($message)->assertSee('Showing the last 30 days instead.')->assertSee('(30 days, Asia/Kuala_Lumpur)', false);
    }

    public static function badRanges(): array
    {
        return [
            'reversed' => [['from' => '2026-10-10', 'to' => '2026-10-01'], 'The start date must be on or before the end date.'],
            'future' => [['from' => '2026-10-01', 'to' => '2026-10-21'], 'The end date cannot be in the future.'],
            'too long' => [['from' => '2025-01-01', 'to' => '2026-10-01'], 'The range can be at most 366 days.'],
            'not a date' => [['from' => 'soon', 'to' => '2026-10-01'], 'Choose a valid start date and end date.'],
            'missing' => [[], 'Choose a valid start date and end date.'],
        ];
    }

    public function test_the_range_is_in_the_admins_timezone(): void
    {
        $this->admin->forceFill(['timezone' => 'America/New_York'])->save();

        $this->page()->assertSee('(30 days, America/New_York)', false);
    }

    public function test_an_empty_platform_shows_a_distinct_message_for_each_chart(): void
    {
        $this->page()->assertOk()
            ->assertSee('No growth to show yet')
            ->assertSee('No session data yet')
            ->assertSee('No specializations recorded yet')
            ->assertSee('No applications submitted in this period');
    }

    public function test_with_sessions_in_other_periods_the_message_says_this_period(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['Stress']);
        $this->booking($counselor, $this->platform(), 'completed', '2026-03-05 10:00');

        $this->page()->assertOk()
            ->assertSee('No completed sessions in this period')
            ->assertDontSee('No session data yet');
    }

    public function test_the_session_cards_say_why_they_are_empty_until_sessions_can_be_completed(): void
    {
        $this->page()->assertSee('A session counts once it is marked completed, and that step is not available yet.');
    }

    public function test_a_small_number_is_shown_as_less_than_five_and_never_as_a_number(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-01-10 08:00:00', null, 1, ['Stress']);
        $university = $this->org('university', 'active', '2026-01-10 08:00:00');
        $corporate = $this->org('corporate', 'active', '2026-01-10 08:00:00');

        $this->sessions(3, $counselor, $university, 'completed', 2, '2026-10');
        $this->sessions(7, $counselor, $corporate, 'completed', 8, '2026-10');

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('University &lt;5', $html, 'the summary says "<5"');
        $this->assertStringContainsString('Corporate 7', $html);
        $this->assertStringNotContainsString('University 3', $html);
        $this->assertStringNotContainsString('University: 3', $html);
        $this->assertStringContainsString('fewer than 5 sessions', $html);
    }

    public function test_the_page_never_prints_a_name_an_email_or_a_kb_number(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $org = $this->org('university', 'active', '2026-10-05 08:00:00', '2026-10-01 08:00:00');
        $this->sessions(6, $counselor, $org, 'completed', 2, '2026-10');

        $html = $this->page()->assertOk()->getContent();

        foreach (['Fixture Org', 'Fixture Counselor', 'example.test', 'FIX-KB', 'FIX-PA', 'Admin Analitik@'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_every_chart_has_a_table_view_with_the_same_numbers(): void
    {
        $counselor = $this->counselor('independent', 'approved', '2026-10-05 08:00:00', '2026-10-01 08:00:00', 1, ['Stress']);
        $org = $this->org('corporate', 'active', '2026-10-05 08:00:00', '2026-10-01 08:00:00');
        $this->sessions(6, $counselor, $org, 'completed', 2, '2026-10');

        $html = $this->page()->assertOk()->getContent();

        foreach (['growth', 'types', 'specializations', 'outcomes'] as $id) {
            $this->assertStringContainsString('id="'.$id.'-table"', $html);
            $this->assertStringContainsString('aria-controls="'.$id.'-table"', $html);
        }

        $this->assertStringContainsString('<th scope="row" class="py-2 pr-3 font-medium">Corporate</th><td class="py-2 pl-3 text-right">6</td>', $html);
    }

    public function test_the_charts_have_sane_geometry_for_every_range_with_demo_data(): void
    {
        $this->seed(\Database\Seeders\AnalyticsDemoSeeder::class);

        foreach ([['range' => 7], ['range' => 30], ['range' => 90], ['range' => 'custom', 'from' => '2025-10-21', 'to' => '2026-10-20'], ['range' => 'custom', 'from' => '2026-10-20', 'to' => '2026-10-20']] as $query) {
            $html = $this->page($query)->assertOk()->getContent();

            // Every number inside a chart's x/y/width/height/points attribute must be a real number.
            preg_match_all('/\b(?:x|y|x1|x2|y1|y2|cx|cy|width|points)="([^"]*)"/', $html, $found);
            foreach ($found[1] as $value) {
                $this->assertDoesNotMatchRegularExpression('/nan|inf/i', $value, 'a chart coordinate is not a number for '.json_encode($query));
            }

            $this->assertStringNotContainsString('[Demo]', $html);
            $this->assertStringNotContainsString('@example.test', $html);
            // One single day has one point, so markers but no line.
            $this->assertStringContainsString(($query['from'] ?? null) === ($query['to'] ?? 0) ? '<circle' : '<polyline', $html);
        }
    }

    public function test_the_page_marks_the_chosen_range_and_each_chart_has_a_text_summary(): void
    {
        $this->page(['range' => 90])->assertSee('aria-current="page"', false)
            ->assertSee('Organizations: 0 submitted', false);
    }

    public function test_opening_the_page_is_not_audited(): void
    {
        $before = AuditLog::count();

        $this->page()->assertOk();
        $this->page(['range' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-10'])->assertOk();

        $this->assertSame($before, AuditLog::count());
    }
}
