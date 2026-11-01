<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\ReviewService;
use App\Support\ReportCsv;
use App\Support\ReportFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** An organization's reports: counts only, small numbers hidden. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgReportsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hr;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        config(['org-reports.min_group' => 5]);
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->aina = $this->counselor('Aina Counselor', [$this->acme]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hr()
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
    }

    /** $n sessions of one kind, one a day from $day on (each at its own hour), for the organization (default) or another. */
    private function sessions(int $n, string $day, BookingStatus $status = BookingStatus::Completed, ?Organization $org = null, ?CounselorProfile $who = null): void
    {
        $org ??= $this->acme;
        $who ??= $this->aina;

        for ($i = 0; $i < $n; $i++) {
            $this->book($this->slot($who, $org, Carbon::parse($day)->addHours($i)->format('Y-m-d H:i')), User::factory()->create(['name' => 'Siti Secret']), $status);
        }
    }

    // ---- the one rule --------------------------------------------------------------------------------------------

    public function test_a_single_small_number_is_shown_as_fewer_than_and_zero_stays_zero(): void
    {
        $this->assertSame('0', ReportFigures::one(0));
        $this->assertSame('fewer than 5', ReportFigures::one(1));
        $this->assertSame('fewer than 5', ReportFigures::one(4));
        $this->assertSame('5', ReportFigures::one(5));
        $this->assertSame('1,234', ReportFigures::one(1234));
    }

    public function test_a_set_hides_small_cells_and_one_more_when_a_single_hidden_cell_could_be_worked_out(): void
    {
        $set = ReportFigures::set(['A' => 7, 'B' => 3, 'C' => 9, 'D' => 0]);

        $this->assertSame('19', $set['total']);
        $this->assertSame('fewer than 5', $set['cells']['B']['label']);
        $this->assertSame('hidden', $set['cells']['A']['label']);        // the smallest other cell goes too
        $this->assertSame('9', $set['cells']['C']['label']);
        $this->assertSame('0', $set['cells']['D']['label']);

        $two = ReportFigures::set(['A' => 7, 'B' => 3, 'C' => 2, 'D' => 9]);
        $this->assertSame('7', $two['cells']['A']['label']);              // two are hidden already: nobody can subtract to one person

        $small = ReportFigures::set(['A' => 2, 'B' => 1]);
        $this->assertSame('fewer than 5', $small['total']);
        $this->assertSame('fewer than 5', $small['cells']['A']['label']);
    }

    // ---- use ------------------------------------------------------------------------------------------------------

    public function test_the_use_page_counts_this_organizations_sessions_and_hides_the_small_numbers(): void
    {
        $this->sessions(6, '2026-10-20 08:00');                                          // completed
        $this->sessions(2, '2026-10-21 08:00', BookingStatus::NoShow);
        $this->sessions(1, '2026-10-22 08:00', BookingStatus::Cancelled);
        $this->sessions(9, '2026-10-23 08:00', org: $this->org('Beta', AccessModel::Closed, OrgType::Corporate), who: $this->counselor('Bakar', [$this->platform]));

        $page = $this->hr()->get(route('org.reports.use'))->assertOk();

        $page->assertSeeInOrder(['How sessions ended', 'Total', '9', 'Completed', '6', 'No-show', 'fewer than 5', 'Cancelled', 'fewer than 5']);
        $page->assertDontSee('Siti Secret')->assertDontSee('Aina Counselor');
    }

    public function test_the_period_decides_which_sessions_count(): void
    {
        $this->sessions(6, '2026-08-10 08:00');   // inside 12 months, outside 3 months (from 1 Sept)
        $this->sessions(6, '2026-11-03 08:00');   // this month

        $this->hr()->get(route('org.reports.use', ['range' => 'month']))->assertSeeInOrder(['How sessions ended', 'Total', '6']);
        $this->hr()->get(route('org.reports.use', ['range' => '3months']))->assertSeeInOrder(['How sessions ended', 'Total', '6']);
        $this->hr()->get(route('org.reports.use', ['range' => '12months']))->assertSeeInOrder(['How sessions ended', 'Total', '12']);
        $this->hr()->get(route('org.reports.use', ['range' => 'nonsense']))->assertOk()->assertSee('Last 3 months');
    }

    public function test_online_in_person_and_how_far_ahead_are_counted_for_sessions_that_were_not_cancelled(): void
    {
        $this->sessions(6, '2026-11-10 08:00');         // booked on 2 Nov for 10 Nov: more than a week ahead
        $this->sessions(2, '2026-11-03 08:00', BookingStatus::Cancelled);

        $page = $this->hr()->get(route('org.reports.use', ['range' => 'month']))->assertOk();

        $page->assertSeeInOrder(['Online or in person', 'Total', '6', 'Online', '6', 'In person', '0']);
        $page->assertSeeInOrder(['How far ahead people book', 'More than a week ahead', '6']);
    }

    public function test_time_requests_are_counted_and_the_speed_needs_enough_answers(): void
    {
        $make = fn (BookingRequestStatus $status, ?string $answeredAfter = null) => BookingRequest::create([
            'user_id' => User::factory()->create()->id, 'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->acme->id,
            'requested_date' => '2026-11-05', 'requested_time' => '10:00:00', 'duration_minutes' => 60, 'session_mode' => 'online',
            'status' => $status, 'expires_at' => now()->addHours(48),
            'responded_at' => $answeredAfter ? now()->addHours((int) $answeredAfter) : null,
        ]);

        foreach (range(1, 3) as $i) {
            $make(BookingRequestStatus::Approved, '2');
        }
        $make(BookingRequestStatus::Rejected, '30');

        $this->hr()->get(route('org.reports.use'))->assertSee('not shown (fewer than 5 answered)');

        $make(BookingRequestStatus::Approved, '1');
        $make(BookingRequestStatus::Approved, '5');

        $this->hr()->get(route('org.reports.use'))->assertSee('Answered within 24 hours:')->assertSee('83%');
    }

    // ---- members --------------------------------------------------------------------------------------------------

    private function member(MembershipStatus $status, ?string $joined = null, JoinSource $source = JoinSource::Domain, ?string $left = null): Membership
    {
        return Membership::create([
            'user_id' => User::factory()->create(['name' => 'Aiman Secret'])->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee,
            'status' => $status, 'join_source' => $source, 'joined_at' => $joined ?? now(), 'offboarded_at' => $left,
        ]);
    }

    public function test_the_members_page_counts_who_joined_who_left_and_how(): void
    {
        foreach (range(1, 6) as $i) {
            $this->member(MembershipStatus::Active, '2026-10-15 10:00:00', JoinSource::Domain);
        }
        $this->member(MembershipStatus::Active, '2026-10-16 10:00:00', JoinSource::InviteCode);
        $this->member(MembershipStatus::PendingApproval, '2026-10-17 10:00:00', JoinSource::InviteCode);
        foreach (range(1, 5) as $i) {
            $this->member(MembershipStatus::Alumni, '2025-01-01 10:00:00', JoinSource::Domain, '2026-10-20 10:00:00');
        }

        $page = $this->hr()->get(route('org.reports.members'))->assertOk();

        $page->assertSeeInOrder(['New members', 'Total', '7']);                                  // the one waiting is not counted
        $page->assertSeeInOrder(['Members who left', 'Total', '5']);
        $page->assertSeeInOrder(['How new members joined', 'By their e-mail domain', '6', 'With a reference code', 'fewer than 5']);
        $page->assertDontSee('Aiman Secret');
    }

    public function test_the_share_of_members_who_booked_is_shown_only_from_five_active_members(): void
    {
        foreach (range(1, 4) as $i) {
            $this->member(MembershipStatus::Active);
        }
        $this->hr()->get(route('org.reports.members'))->assertSee('Not shown')->assertSee('shown from 5 active members');

        $fifth = $this->member(MembershipStatus::Active);
        $this->book($this->slot($this->aina, $this->acme, '2026-11-05 10:00'), $fifth->user);
        $this->book($this->slot($this->aina, $this->acme, '2026-11-05 12:00'), Membership::where('org_id', $this->acme->id)->where('role', MembershipRole::Employee)->first()->user, BookingStatus::Cancelled);

        $this->hr()->get(route('org.reports.members'))->assertSee('20%');
    }

    // ---- counselors -----------------------------------------------------------------------------------------------

    public function test_the_counselors_page_shows_capacity_hours_sessions_and_the_first_free_time(): void
    {
        $this->sessions(6, '2026-11-10 08:00');                                  // six booked hours (one slot an hour)
        $this->slot($this->aina, $this->acme, '2026-11-12 10:00');               // one free hour, 10 days ahead
        $this->slot($this->aina, $this->acme, '2026-11-04 15:00');               // the nearest free hour: in 2 days
        $bakar = $this->counselor('Bakar Counselor', [$this->acme]);
        $this->sessions(2, '2026-11-11 08:00', who: $bakar);

        $page = $this->hr()->get(route('org.reports.counselors', ['range' => 'month']))->assertOk();

        $page->assertSeeInOrder(['Hours offered', '10', 'Hours booked', '8'])
            ->assertSeeInOrder(['Aina Counselor', '8', 'hidden', '—', 'Wed, 4 Nov (in 2 days)'])
            ->assertSeeInOrder(['Bakar Counselor', '2', 'fewer than 5', 'Nothing open'])
            ->assertDontSee('Siti Secret');
    }

    public function test_when_every_counselor_has_enough_sessions_each_share_booked_is_shown(): void
    {
        $bakar = $this->counselor('Bakar Counselor', [$this->acme]);
        $this->sessions(6, '2026-11-10 08:00');
        $this->slot($this->aina, $this->acme, '2026-11-12 10:00');
        $this->sessions(5, '2026-11-11 08:00', who: $bakar);

        $this->hr()->get(route('org.reports.counselors', ['range' => 'month']))->assertOk()
            ->assertSeeInOrder(['Aina Counselor', '7', '6', '86%'])->assertSeeInOrder(['Bakar Counselor', '5', '5', '100%']);
    }

    public function test_the_capacity_share_needs_five_sessions_and_another_organizations_hours_are_not_counted(): void
    {
        $this->sessions(2, '2026-11-10 08:00');
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $this->sessions(9, '2026-11-10 12:00', org: $beta);

        $page = $this->hr()->get(route('org.reports.counselors', ['range' => 'month']))->assertOk();

        $page->assertSee('Shown from 5 sessions in the period')->assertSeeInOrder(['Hours offered', '2', 'Hours booked', '2']);
    }

    // ---- quality --------------------------------------------------------------------------------------------------

    private function reviewed(int $n, int $stars, ?CounselorProfile $who = null, string $comment = 'A private remark about the session'): void
    {
        $who ??= $this->aina;

        for ($i = 0; $i < $n; $i++) {
            $booking = $this->book($this->slot($who, $this->acme, Carbon::parse('2026-10-20 08:00')->addHours($i)->format('Y-m-d H:i')), User::factory()->create(), BookingStatus::Completed);
            app(ReviewService::class)->submit($booking->user, $booking->public_id, $stars, [], $comment);
        }
    }

    public function test_the_average_is_shown_only_from_five_reviews_and_never_a_comment(): void
    {
        $this->reviewed(4, 5);

        $this->hr()->get(route('org.reports.quality'))->assertOk()->assertSee('Not enough reviews yet')->assertSee('Not enough reviews')->assertDontSee('private remark');

        $this->reviewed(1, 3, comment: 'Another private remark');   // the fifth: 5, 5, 5, 5, 3

        $page = $this->hr()->get(route('org.reports.quality'))->assertOk();
        $page->assertSee('4.6')->assertDontSee('private remark')->assertDontSee('Not enough reviews yet');
    }

    public function test_a_counselors_own_average_needs_their_own_five_reviews(): void
    {
        $bakar = $this->counselor('Bakar Counselor', [$this->acme]);
        $this->reviewed(5, 4);
        $this->reviewed(2, 1, who: $bakar);

        $page = $this->hr()->get(route('org.reports.quality'))->assertOk();

        $page->assertSeeInOrder(['Aina Counselor', '5', '4.0'])->assertSeeInOrder(['Bakar Counselor', 'fewer than 5', 'Not enough reviews']);
    }

    public function test_a_removed_review_does_not_count_and_the_cancelling_rate_needs_five_sessions(): void
    {
        $this->reviewed(5, 5);
        \App\Models\Review::query()->first()->update(['status' => 'removed']);

        $this->hr()->get(route('org.reports.quality'))->assertSee('Not enough reviews yet');

        $this->sessions(6, '2026-11-10 08:00');
        $this->sessions(2, '2026-11-11 08:00', BookingStatus::Cancelled);

        $page = $this->hr()->get(route('org.reports.quality', ['range' => 'month']))->assertOk();
        $page->assertSeeInOrder(['Cancelling', '25%', 'Sessions in this period', '8'])->assertSeeInOrder(['Who cancelled', 'Total', 'fewer than 5']);
    }

    // ---- comparing two periods ------------------------------------------------------------------------------------

    public function test_comparing_puts_the_period_before_next_to_this_one_with_the_same_hiding(): void
    {
        $this->sessions(6, '2026-11-03 08:00');                                  // this month, completed
        $this->sessions(2, '2026-10-20 08:00', BookingStatus::NoShow);           // last month: too few to show
        $this->sessions(7, '2026-10-21 08:00');                                  // last month, completed

        $page = $this->hr()->get(route('org.reports.use', ['range' => 'month', 'compare' => 1]))->assertOk();

        $page->assertSee('compared with 1 Oct 2026 to 31 Oct 2026')->assertSee('before 9')->assertSeeTextInOrder(['Completed', '6', 'Before: hidden'])     // last month's 7 sits next to a hidden 2, so it is hidden too
            ->assertSee('Compare with the period before');
        $page->assertDontSee('Siti Secret');

        $without = $this->hr()->get(route('org.reports.use', ['range' => 'month']))->assertOk();
        $without->assertDontSee('compared with')->assertDontSee('Before:');
    }

    public function test_the_period_before_has_the_same_length(): void
    {
        $reports = app(\App\Services\OrgReports::class);

        $month = $reports->previous($reports->period('month'));
        $this->assertSame('2026-10-01', $month['from']->format('Y-m-d'));
        $this->assertSame('2026-10-31', $month['to']->format('Y-m-d'));

        $quarter = $reports->previous($reports->period('3months'));
        $this->assertSame('2026-06-01', $quarter['from']->format('Y-m-d'));
        $this->assertSame('2026-08-31', $quarter['to']->format('Y-m-d'));
    }

    public function test_the_counselors_and_quality_pages_do_not_offer_a_comparison(): void
    {
        $this->hr()->get(route('org.reports.counselors', ['compare' => 1]))->assertOk()->assertDontSee('Compare with the period before')->assertDontSee('compared with');
        $this->hr()->get(route('org.reports.quality', ['compare' => 1]))->assertOk()->assertDontSee('Compare with the period before');
    }

    // ---- the CSV --------------------------------------------------------------------------------------------------

    public function test_the_csv_says_what_the_page_says_and_never_more(): void
    {
        $this->sessions(6, '2026-10-20 08:00');
        $this->sessions(2, '2026-10-21 08:00', BookingStatus::NoShow);
        $this->sessions(1, '2026-10-22 08:00', BookingStatus::Cancelled);

        $response = $this->hr()->get(route('org.reports.export', ['page' => 'use', 'range' => '3months']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Acme Berhad', $csv);
        $this->assertStringContainsString('Last 3 months', $csv);
        $this->assertMatchesRegularExpression('/Status,Completed,6/', $csv);
        $this->assertStringContainsString('Status,No-show,"fewer than 5"', $csv);
        $this->assertStringContainsString('Status,Cancelled,"fewer than 5"', $csv);
        $this->assertStringNotContainsString('Siti Secret', $csv);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="acme-berhad-report-use-3months.csv"', $response->headers->get('Content-Disposition'));
    }

    public function test_every_page_can_be_downloaded_an_unknown_page_is_a_404_and_each_download_is_audited_without_figures(): void
    {
        foreach (['overview', 'use', 'members', 'counselors', 'quality'] as $page) {
            $this->hr()->get(route('org.reports.export', ['page' => $page]))->assertOk();
        }

        $this->hr()->get(route('org.reports.export', ['page' => 'secrets']))->assertNotFound();
        $this->assertSame(5, AuditLog::where('action', 'organization.report_exported')->count());
        $this->assertStringNotContainsString('Completed', json_encode(AuditLog::where('action', 'organization.report_exported')->get()->toArray()));
    }

    public function test_the_csv_lists_the_counselors_and_makes_a_formula_in_a_name_harmless(): void
    {
        $this->counselor('=HYPERLINK("http://evil.example")', [$this->acme]);

        $csv = $this->hr()->get(route('org.reports.export', ['page' => 'counselors']))->assertOk()->getContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertSame("'=1+1", ReportCsv::safe('=1+1'));
        $this->assertSame("'@x", ReportCsv::safe('@x'));
        $this->assertSame('Aina', ReportCsv::safe('Aina'));
        $this->assertSame('', ReportCsv::safe(''));
    }

    public function test_only_an_organization_admin_downloads_and_only_their_own_organization(): void
    {
        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.reports.export', ['page' => 'use']))->assertForbidden();

        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $this->sessions(9, '2026-10-20 08:00', org: $beta, who: $this->counselor('Bakar', [$this->platform]));

        $csv = $this->hr()->get(route('org.reports.export', ['page' => 'use']))->assertOk()->getContent();
        $this->assertStringNotContainsString('Beta', $csv);
        $this->assertDoesNotMatchRegularExpression('/Status,Completed,9/', $csv);
    }

    // ---- the overview and who may open it -------------------------------------------------------------------------

    public function test_the_overview_has_the_numbers_and_the_comparison_only_when_both_are_large_enough(): void
    {
        $this->sessions(6, '2026-11-03 08:00');
        $this->member(MembershipStatus::Active);
        $this->member(MembershipStatus::PendingApproval);

        $page = $this->hr()->get(route('org.reports', ['range' => 'month']))->assertOk();
        $page->assertSee('Active members')->assertSee('Waiting for approval')->assertSeeInOrder(['Sessions', '6', 'Before: 0']);
        $page->assertDontSee('Before: 0 ·');

        $this->sessions(6, '2026-10-05 08:00');
        $this->hr()->get(route('org.reports', ['range' => 'month']))->assertSee('Before: 6 · the same');
    }

    public function test_only_an_organization_admin_opens_the_reports_and_they_are_not_cached(): void
    {
        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        foreach (['org.reports', 'org.reports.use', 'org.reports.members', 'org.reports.counselors', 'org.reports.quality'] as $route) {
            $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route($route))->assertForbidden();
            $this->hr()->get(route($route))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
    }
}
