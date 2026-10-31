<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\DomainMatchType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\LkmDirectorySnapshot;
use App\Models\Organization;
use App\Models\OrgDomain;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\AnalyticsService;
use App\Services\DashboardService;
use App\Services\PlatformSettings;
use App\Services\PricingStats;
use App\Services\WaitlistHasher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DashboardService: every figure of the Dashboard, worked out in one place from the methods the owning pages use. Controlled
 * fixtures here (ordering, thresholds, cap, privacy, banner, query count); DashboardMatchesPagesTest proves the numbers equal the
 * pages' own tab counts on the real sample data.
 */
class DashboardServiceTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const NOW = '2026-10-20 12:00:00';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->admin = User::factory()->create(['name' => 'Aminah binti Yusof', 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    /** Waiting since this many days (and hours) before NOW. */
    private function ago(int $days, int $hours = 0): string
    {
        return Carbon::parse(self::NOW)->subDays($days)->subHours($hours)->format('Y-m-d H:i:s');
    }

    private function pendingOrg(int $days, int $hours = 0, ?string $name = null): Organization
    {
        $org = $this->org('university', 'pending', null, $this->ago($days, $hours));

        if ($name) {
            $org->forceFill(['name' => $name])->saveQuietly();
        }

        return $org;
    }

    private function skippedOrg(int $days): Organization
    {
        $org = $this->pendingOrg($days);
        OrgDomain::create(['org_id' => $org->id, 'domain' => "org{$org->id}.edu.my", 'default_role' => MembershipRole::Student, 'match_type' => DomainMatchType::Exact, 'dns_verified' => false]);

        return $org;
    }

    /** A pending independent counselor whose LKM record backs the application up (so no "LKM could not be verified"). */
    private function pendingCounselor(int $days, bool $inLkm = true): CounselorProfile
    {
        $profile = $this->counselor('independent', 'pending', null, $this->ago($days));

        if ($inLkm) {
            LkmDirectorySnapshot::create([
                'kb_number' => $profile->kb_number, 'pa_number' => $profile->pa_number, 'full_name' => $profile->user->name, 'status' => 'Aktif',
                'pa_valid_from' => '2025-01-01', 'pa_valid_until' => '2030-01-01',
            ]);
        }

        return $profile;
    }

    private function complaint(ComplaintStatus $status, int $days, string $text = 'Ordinary words.'): Complaint
    {
        $reporter = User::factory()->create();
        $profile = $this->counselor('independent', 'approved');
        $complaint = Complaint::create(['reporter_user_id' => $reporter->id, 'counselor_user_id' => $profile->user_id, 'description' => $text, 'status' => $status]);
        $complaint->forceFill(['created_at' => $this->ago($days)])->saveQuietly();

        return $complaint;
    }

    private function approvalLog(string $action, string $name, string $when, string $actor = 'Admin Satu', bool $system = false, ?int $targetId = 1): AuditLog
    {
        return AuditLog::create([
            'actor_type' => $system ? 'system' : 'user', 'actor_user_id' => null, 'actor_name' => $system ? null : $actor, 'actor_role' => $system ? null : 'platform_admin',
            'action' => $action, 'category' => str_starts_with($action, 'organization') ? 'organizations' : 'counselors',
            'target_type' => str_starts_with($action, 'organization') ? 'organization' : 'counselor', 'target_id' => $targetId, 'target_name' => $name,
            'created_at' => $when,
        ]);
    }

    private function queries(callable $run): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $run();

        return $count;
    }

    // ---- the four cards -----------------------------------------------------------------------------------------------------

    public function test_the_cards_count_what_their_tabs_count_and_link_to_those_tabs(): void
    {
        $this->pendingOrg(1);
        $this->pendingOrg(2);
        $this->skippedOrg(3);
        $this->org('university', 'active', $this->ago(30));
        $this->org('university', 'rejected', $this->ago(30));
        $this->pendingCounselor(1);
        $this->pendingCounselor(2);
        $this->counselor('organization', 'pending');            // needs no approval: on neither the page nor the card
        $this->counselor('independent', 'approved');
        $this->complaint(ComplaintStatus::Open, 1);
        $this->complaint(ComplaintStatus::Open, 1);
        $this->complaint(ComplaintStatus::UnderReview, 1);
        $this->complaint(ComplaintStatus::Resolved, 1);

        $cards = $this->service()->cards();

        $this->assertSame(2, $cards['pending_organizations']['count'], 'the skipped one is on its own card, not counted twice');
        $this->assertSame(1, $cards['skipped_dns']['count']);
        $this->assertSame(2, $cards['pending_counselors']['count']);
        $this->assertSame(3, $cards['active_complaints']['count']);
        $this->assertSame(2, $cards['active_complaints']['open']);
        $this->assertSame(1, $cards['active_complaints']['under_review']);

        $this->assertSame(route('platform.organizations', ['tab' => 'pending']), $cards['pending_organizations']['url']);
        $this->assertSame(route('platform.organizations', ['tab' => 'skipped']), $cards['skipped_dns']['url']);
        $this->assertSame(route('platform.counselors', ['tab' => 'pending']), $cards['pending_counselors']['url']);
        $this->assertSame(route('platform.complaints', ['tab' => 'open']), $cards['active_complaints']['url']);
    }

    public function test_the_platform_organization_is_never_counted(): void
    {
        $this->platform()->forceFill(['subscription_status' => 'pending'])->saveQuietly();

        $this->assertSame(0, $this->service()->pendingOrganizations());
    }

    // ---- needs your attention --------------------------------------------------------------------------------------------------

    public function test_the_waiting_thresholds_are_five_days_amber_and_eight_days_red_to_the_hour(): void
    {
        $under = $this->pendingOrg(4, 23, 'Four Days Org');
        $five = $this->pendingOrg(5, 0, 'Five Days Org');
        $seven = $this->pendingOrg(7, 23, 'Seven Days Org');
        $eight = $this->pendingOrg(8, 0, 'Eight Days Org');

        $items = collect($this->service()->attention()['items'])->keyBy('key');

        $this->assertNull($items->get("organization:{$under->id}"), 'just under 5 days is not shown');
        $this->assertSame('amber', $items["organization:{$five->id}"]['tone']);
        $this->assertSame('amber', $items["organization:{$seven->id}"]['tone']);
        $this->assertSame('red', $items["organization:{$eight->id}"]['tone']);
        $this->assertSame('Waiting 5 days', $items["organization:{$five->id}"]['detail']);
        $this->assertSame('Eight Days Org — organization awaiting approval', $items["organization:{$eight->id}"]['text']);
    }

    public function test_the_same_thresholds_serve_the_pages_and_the_dashboard(): void
    {
        $this->assertSame('ok', $this->pendingOrg(4, 23)->waitingTone());
        $this->assertSame('amber', $this->pendingOrg(5)->waitingTone());
        $this->assertSame('red', $this->pendingOrg(8)->waitingTone());
        $this->assertSame('amber', $this->pendingCounselor(5)->waitingTone());
        $this->assertSame('red', $this->pendingCounselor(8)->waitingTone());
    }

    public function test_the_most_urgent_come_first_red_before_amber_then_the_longest_wait_and_it_is_the_same_every_time(): void
    {
        $amberOld = $this->pendingOrg(7, 0, 'Amber Old');
        $amberNew = $this->pendingOrg(5, 0, 'Amber New');
        $redOld = $this->pendingOrg(12, 0, 'Red Old');
        $complaint = $this->complaint(ComplaintStatus::Open, 9);
        $counselor = $this->pendingCounselor(6);
        $skippedRecent = $this->skippedOrg(1);

        $first = array_column($this->service()->attention()['items'], 'key');
        $second = array_column($this->service()->attention()['items'], 'key');

        $this->assertSame([
            "organization:{$redOld->id}",       // red, 12 days
            "complaint:{$complaint->id}",       // red, 9 days
            "organization:{$amberOld->id}",     // amber, 7 days
            "counselor:{$counselor->id}",       // amber, 6 days
            "organization:{$amberNew->id}",     // amber, 5 days
            "organization-dns:{$skippedRecent->id}", // under 5 days, shown because it needs a manual check
        ], $first);
        $this->assertSame($first, $second);
    }

    public function test_it_stops_at_eight_items_counts_the_rest_and_says_where_they_are(): void
    {
        foreach (range(6, 17) as $days) {
            $this->pendingOrg($days);
        }

        $attention = $this->service()->attention();

        $this->assertCount(8, $attention['items']);
        $this->assertSame(12, $attention['total']);
        $this->assertSame(4, $attention['more']);
        $this->assertSame([17, 16, 15, 14, 13, 12, 11, 10], array_column($attention['items'], 'days'), 'the eight longest waits');
        $this->assertSame([['label' => 'Organizations waiting for review', 'url' => route('platform.organizations', ['tab' => 'pending']), 'count' => 4]], $attention['more_by_destination']);
    }

    public function test_a_more_urgent_item_from_another_source_is_never_cut_off(): void
    {
        foreach (range(1, 9) as $i) {
            $this->complaint(ComplaintStatus::Open, 6);
        }
        $old = $this->pendingOrg(30, 0, 'Oldest Org');

        $attention = $this->service()->attention();

        $this->assertSame("organization:{$old->id}", $attention['items'][0]['key']);
        $this->assertCount(8, $attention['items']);
        $this->assertSame(10, $attention['total']);
        $this->assertSame(2, $attention['more']);
    }

    public function test_when_nothing_is_wrong_the_list_is_empty(): void
    {
        $this->pendingOrg(1);
        $this->pendingCounselor(1);

        $attention = $this->service()->attention();

        $this->assertSame([], $attention['items']);
        $this->assertSame(0, $attention['total']);
        $this->assertSame(0, $attention['more']);
        $this->assertSame([], $attention['more_by_destination']);
    }

    public function test_a_counselor_whose_lkm_record_does_not_back_them_up_is_shown_once_at_any_age_with_the_kb_number(): void
    {
        $profile = $this->pendingCounselor(1, inLkm: false);

        $items = $this->service()->attention()['items'];

        $this->assertCount(1, $items);
        $this->assertSame('counselor_lkm', $items[0]['kind']);
        $this->assertSame("{$profile->user->name} ({$profile->kb_number}) — LKM number could not be verified", $items[0]['text']);
        $this->assertSame(route('platform.counselors.show', $profile), $items[0]['url']);
    }

    public function test_an_old_pending_counselor_that_lkm_confirms_says_credentials_awaiting_verification(): void
    {
        $profile = $this->pendingCounselor(9);

        $item = $this->service()->attention()['items'][0];

        $this->assertSame('counselor_waiting', $item['kind']);
        $this->assertSame("{$profile->user->name} — credentials awaiting verification", $item['text']);
        $this->assertSame('red', $item['tone']);
    }

    public function test_a_duplicate_kb_attempt_is_listed_whatever_the_status_of_the_account(): void
    {
        $profile = $this->counselor('independent', 'approved');
        CounselorKbAttempt::create(['counselor_profile_id' => $profile->id, 'kb_number' => $profile->kb_number, 'submitted_name' => 'Someone Else', 'ip' => '203.0.113.9']);

        $item = $this->service()->attention()['items'][0];

        $this->assertSame('counselor_duplicate_kb', $item['kind']);
        $this->assertSame("{$profile->user->name} ({$profile->kb_number}) — duplicate KB registration attempt", $item['text']);
        $this->assertSame('Last attempt today', $item['detail']);
        $this->assertSame(route('platform.counselors.show', $profile), $item['url']);
        $this->assertStringNotContainsString('Someone Else', json_encode($item), 'the name typed by the other person is not shown');
        $this->assertStringNotContainsString('203.0.113.9', json_encode($item));
    }

    public function test_an_open_complaint_is_listed_by_reference_number_only_and_links_to_it(): void
    {
        $complaint = $this->complaint(ComplaintStatus::Open, 2, 'SECRET-COMPLAINT-TEXT about a counselor');
        $this->complaint(ComplaintStatus::UnderReview, 20, 'SECRET-UNDER-REVIEW-TEXT');

        $attention = $this->service()->attention();

        $this->assertCount(1, $attention['items'], 'only an open (not yet taken up) complaint is an item');
        $this->assertSame("Complaint #{$complaint->id} — open, waiting for a response", $attention['items'][0]['text']);
        $this->assertSame(route('platform.complaints.show', $complaint), $attention['items'][0]['url']);
        $this->assertStringNotContainsString('SECRET', json_encode($this->service()->build($this->admin)), 'no complaint text anywhere in what the Dashboard receives');
    }

    public function test_a_pending_organization_that_skipped_dns_is_listed_at_any_age_and_not_twice(): void
    {
        $skipped = $this->skippedOrg(9);

        $attention = $this->service()->attention();

        $this->assertCount(1, $attention['items'], 'it is not also in the normal waiting list');
        $this->assertSame("organization-dns:{$skipped->id}", $attention['items'][0]['key']);
        $this->assertSame('red', $attention['items'][0]['tone']);
        $this->assertStringContainsString('domain not verified', $attention['items'][0]['text']);
    }

    public function test_counselors_whose_pa_runs_out_within_thirty_days_are_one_summary_line(): void
    {
        foreach ([10, 30, 31, -1] as $daysLeft) {
            $this->counselor('independent', 'approved')->forceFill(['pa_valid_until' => Carbon::parse(self::NOW)->addDays($daysLeft)->toDateString()])->saveQuietly();
        }

        $items = collect($this->service()->attention()['items'])->where('kind', 'counselors_pa_expiring');

        $this->assertCount(1, $items);
        $this->assertSame('2 counselors with a practising certificate (PA) expiring within 30 days', $items->first()['text']);
        $this->assertSame(route('platform.counselors', ['tab' => 'active', 'pa' => 'expiring']), $items->first()['url']);
        $this->assertSame('amber', $items->first()['tone']);
    }

    public function test_plans_ending_soon_are_one_summary_line_and_only_when_there_are_some(): void
    {
        $this->mock(PricingStats::class, fn ($mock) => $mock->shouldReceive('subscriptionsEndingWithin')->with(30)->andReturn(3)->getMock()->shouldReceive('subscriptionsEnded')->andReturn(0));
        $this->assertSame('3 organizations with a plan ending within 30 days', collect($this->service()->attention()['items'])->firstWhere('kind', 'subscriptions_ending')['text']);

        $this->mock(PricingStats::class, fn ($mock) => $mock->shouldReceive('subscriptionsEndingWithin')->with(30)->andReturn(0)->getMock()->shouldReceive('subscriptionsEnded')->andReturn(0));
        $this->assertNull(collect(app(DashboardService::class)->attention()['items'])->firstWhere('kind', 'subscriptions_ending'));
    }

    public function test_every_link_points_to_an_existing_route(): void
    {
        $this->pendingOrg(9);
        $this->skippedOrg(9);
        $this->pendingCounselor(9);
        $this->pendingCounselor(1, inLkm: false);
        $this->complaint(ComplaintStatus::Open, 9);
        $this->counselor('independent', 'approved')->forceFill(['pa_valid_until' => Carbon::parse(self::NOW)->addDays(5)->toDateString()])->saveQuietly();

        $data = $this->service()->build($this->admin);
        $urls = array_merge(array_column($data['cards'], 'url'), array_column($data['attention']['items'], 'url'), array_column($data['attention']['more_by_destination'], 'url'), [$data['approvals']['view_all_url']]);

        $admin = $this->admin;
        $session = ['current_org_id' => $this->platform()->id, 'current_role' => 'platform_admin'];

        foreach (array_unique($urls) as $url) {
            $this->actingAs($admin)->withSession($session)->get($url)->assertOk();
        }
    }

    // ---- platform stats -------------------------------------------------------------------------------------------------------

    public function test_the_stats_are_the_analytics_numbers(): void
    {
        $this->org('university', 'active', $this->ago(40));
        $this->org('corporate', 'suspended', $this->ago(40));
        $this->org('clinic', 'pending');
        $this->counselor('independent', 'approved', $this->ago(40));
        $this->counselor('independent', 'suspended', $this->ago(40));
        $this->counselor('independent', 'pending', null, $this->ago(1));

        $stats = $this->service()->stats('Asia/Kuala_Lumpur');
        $analytics = app(AnalyticsService::class);

        $this->assertSame(2, $stats['organizations'], 'approved includes the suspended one; the platform organization is left out');
        $this->assertSame(2, $stats['counselors']);
        $this->assertSame($analytics->totalOrganizations(), $stats['organizations']);
        $this->assertSame($analytics->totalCounselors(), $stats['counselors']);
        $this->assertSame($analytics->activeMembers(), $stats['members']);
    }

    public function test_active_members_are_distinct_people_in_real_organizations(): void
    {
        $one = $this->org('university', 'active', $this->ago(40));
        $two = $this->org('corporate', 'active', $this->ago(40));
        $both = User::factory()->create();
        $makeMember = fn (User $user, Organization $org, MembershipRole $role, MembershipStatus $status = MembershipStatus::Active) => Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);

        $makeMember($both, $one, MembershipRole::Student);
        $makeMember($both, $two, MembershipRole::Employee);                                   // the same person in a second organization
        $makeMember(User::factory()->create(), $one, MembershipRole::Staff);
        $makeMember(User::factory()->create(), $one, MembershipRole::Student, MembershipStatus::Suspended);
        $makeMember(User::factory()->create(), $one, MembershipRole::Counselor);               // not a member in this sense
        $makeMember(User::factory()->create(), $this->platform(), MembershipRole::Student);    // the virtual platform organization is not real

        $this->assertSame(2, $this->service()->stats('UTC')['members']);
    }

    public function test_sessions_this_month_are_by_session_date_in_the_admins_timezone_and_say_when_there_is_no_data(): void
    {
        $this->assertSame(['count' => 0, 'has_data' => false], $this->service()->stats('Asia/Kuala_Lumpur')['sessions'], 'no session was ever marked completed: "No session data yet"');

        $counselor = $this->counselor('independent', 'approved');
        $org = $this->org('university', 'active', $this->ago(60));
        $this->booking($counselor, $org, 'completed', '2026-10-01 00:00');   // first minute of October in Kuala Lumpur
        $this->booking($counselor, $org, 'completed', '2026-10-10 10:00');
        $this->booking($counselor, $org, 'completed', '2026-09-30 23:59');   // September
        $this->booking($counselor, $org, 'no_show', '2026-10-12 10:00');     // not completed
        $this->booking($counselor, $org, 'confirmed', '2026-10-25 10:00');   // not yet

        $this->assertSame(['count' => 2, 'has_data' => true], $this->service()->stats('Asia/Kuala_Lumpur')['sessions']);
    }

    // ---- recent approvals --------------------------------------------------------------------------------------------------------

    public function test_recent_approvals_come_from_the_audit_log_newest_first_with_the_names_saved_at_the_time(): void
    {
        $this->approvalLog('organization.approved', 'Universiti Lama', '2026-10-01 08:00:00', 'Admin Satu');
        $this->approvalLog('counselor.approved', 'Dr. Aisyah', '2026-10-03 09:30:00', 'Admin Dua');
        $this->approvalLog('organization.approved', 'Syarikat Baru', '2026-10-03 09:30:00', 'Admin Tiga');     // same minute: higher id first
        $this->approvalLog('organization.rejected', 'Ditolak Sdn Bhd', '2026-10-19 08:00:00');
        $this->approvalLog('counselor.approved', 'Dibuat Sistem', '2026-10-02 10:00:00', system: true);

        $approvals = $this->service()->approvals('Asia/Kuala_Lumpur');

        $this->assertSame(['Syarikat Baru', 'Dr. Aisyah', 'Dibuat Sistem', 'Universiti Lama'], array_column($approvals['rows'], 'name'), 'rejections are not approvals');
        $this->assertSame(['Organization', 'Counselor', 'Counselor', 'Organization'], array_column($approvals['rows'], 'type'));
        $this->assertSame(['Admin Tiga', 'Admin Dua', 'System', 'Admin Satu'], array_column($approvals['rows'], 'by'));
        $this->assertSame('2026-10-03 17:30', $approvals['rows'][0]['at']->format('Y-m-d H:i'), '09:30 UTC is 17:30 in Kuala Lumpur');
        $this->assertSame('Asia/Kuala_Lumpur', $approvals['rows'][0]['at']->getTimezone()->getName());
    }

    public function test_recent_approvals_show_the_saved_name_even_when_the_record_is_gone_and_do_not_backfill(): void
    {
        $gone = $this->org('university', 'active', $this->ago(10));
        $this->approvalLog('organization.approved', 'Name As Saved', '2026-10-10 08:00:00', targetId: $gone->id);
        $this->org('corporate', 'active', $this->ago(5));                                         // approved, but nothing in the audit log
        $gone->forceFill(['name' => 'Renamed Later'])->saveQuietly();

        $rows = $this->service()->approvals('UTC')['rows'];

        $this->assertSame(['Name As Saved'], array_column($rows, 'name'));
    }

    public function test_recent_approvals_stop_at_five_and_link_to_the_audit_log_filtered_to_approvals(): void
    {
        foreach (range(1, 7) as $i) {
            $this->approvalLog('counselor.approved', "Counselor {$i}", Carbon::parse(self::NOW)->subDays($i)->format('Y-m-d H:i:s'));
        }

        $approvals = $this->service()->approvals('UTC');

        $this->assertCount(5, $approvals['rows']);
        $this->assertSame('Counselor 1', $approvals['rows'][0]['name']);
        $this->assertSame(route('platform.audit-log', ['actions' => 'organization.approved,counselor.approved']), $approvals['view_all_url']);
    }

    public function test_the_audit_log_page_filters_to_several_actions_at_once(): void
    {
        $this->approvalLog('organization.approved', 'Org One', '2026-10-19 08:00:00');
        $this->approvalLog('counselor.approved', 'Counselor One', '2026-10-19 09:00:00');
        $this->approvalLog('organization.rejected', 'Org Rejected', '2026-10-19 10:00:00');
        $session = ['current_org_id' => $this->platform()->id, 'current_role' => 'platform_admin'];

        $page = $this->actingAs($this->admin)->withSession($session)
            ->get(route('platform.audit-log', ['actions' => 'organization.approved,counselor.approved']))->assertOk();

        $page->assertSee('Org One')->assertSee('Counselor One')->assertDontSee('Org Rejected');
        $page->assertSee('Organization approved, Counselor approved');

        // Not a list of keys: dropped and reported, never trusted (so nothing is filtered and the text is not echoed back).
        $this->actingAs($this->admin)->withSession($session)->get(route('platform.audit-log', ['actions' => 'organization.approved,<b>x</b>']))
            ->assertOk()->assertSee('Unknown actions.')->assertSee('Org Rejected')->assertDontSee('<b>x</b>', false);
    }

    public function test_with_no_audit_entries_recent_approvals_is_empty(): void
    {
        $this->assertSame([], $this->service()->approvals('UTC')['rows']);
    }

    // ---- the banner ------------------------------------------------------------------------------------------------------------------

    private function waiting(string $type, int $howMany, string $status = 'waiting'): void
    {
        foreach (range(1, $howMany) as $i) {
            $email = "{$status}.{$type}.{$i}@example.test";
            WaitlistEntry::create(['type' => $type, 'email' => $email, 'email_hash' => app(WaitlistHasher::class)->emailHash($type, $email), 'status' => $status]);
        }
    }

    public function test_the_banner_is_hidden_when_everything_is_normal(): void
    {
        $this->assertSame(['state' => 'hidden'], $this->service()->banner('UTC'));
    }

    public function test_the_banner_says_which_sign_ups_are_closed_and_counts_only_people_still_waiting(): void
    {
        app(PlatformSettings::class)->setRegistration($this->admin, ['organization', 'individual'], false);
        $this->waiting('organization', 3);
        $this->waiting('organization', 2, 'notified');       // already told: not waiting
        $this->waiting('counselor', 4);                       // counselor sign-up is open, so it is not part of this banner

        $banner = $this->service()->banner('UTC');

        $this->assertSame('registration', $banner['state']);
        $this->assertSame(['organization' => 'Organizations', 'individual' => 'Individuals'], $banner['closed']);
        $this->assertFalse($banner['all_closed']);
        $this->assertSame(['organization' => 3], $banner['waiting'], 'individuals have no waitlist');
        $this->assertSame(route('platform.registration'), $banner['url']);
    }

    public function test_the_banner_knows_when_every_sign_up_is_closed(): void
    {
        app(PlatformSettings::class)->setRegistration($this->admin, ['organization', 'counselor', 'individual'], false);
        $this->waiting('organization', 1);
        $this->waiting('counselor', 2);

        $banner = $this->service()->banner('UTC');

        $this->assertTrue($banner['all_closed']);
        $this->assertSame(['organization' => 1, 'counselor' => 2], $banner['waiting']);
    }

    public function test_maintenance_wins_and_carries_the_reopen_time_in_the_admins_timezone(): void
    {
        app(PlatformSettings::class)->setRegistration($this->admin, ['organization'], false);
        app(PlatformSettings::class)->setMaintenance($this->admin, true, null, CarbonImmutable::parse('2026-10-21 02:00:00', 'UTC'));

        $banner = $this->service()->banner('Asia/Kuala_Lumpur');

        $this->assertSame('maintenance', $banner['state']);
        $this->assertSame('2026-10-21 10:00', $banner['reopen_at']->format('Y-m-d H:i'));
    }

    public function test_the_banner_is_a_fresh_read_every_time(): void
    {
        $this->assertSame('hidden', $this->service()->banner('UTC')['state']);

        app(PlatformSettings::class)->setRegistration($this->admin, ['counselor'], false);
        $this->assertSame('registration', app(DashboardService::class)->banner('UTC')['state']);

        app(PlatformSettings::class)->setRegistration($this->admin, ['counselor'], true);
        $this->assertSame('hidden', app(DashboardService::class)->banner('UTC')['state']);
    }

    // ---- greeting --------------------------------------------------------------------------------------------------------------------

    public function test_the_greeting_follows_the_time_of_day_in_the_admins_own_timezone(): void
    {
        $now = CarbonImmutable::parse('2026-10-20 22:00:00', 'UTC');   // 06:00 in Kuala Lumpur, 18:00 in New York, 22:00 in London

        $kl = User::factory()->create(['name' => 'Aminah binti Yusof', 'timezone' => 'Asia/Kuala_Lumpur']);
        $ny = User::factory()->create(['name' => 'Nora', 'timezone' => 'America/New_York']);
        $utc = User::factory()->create(['name' => 'Umar Lim', 'timezone' => 'UTC']);

        $this->assertSame(['text' => 'Good morning', 'first_name' => 'Aminah', 'timezone' => 'Asia/Kuala_Lumpur'], $this->service()->greeting($kl, $now));
        $this->assertSame('Good evening', $this->service()->greeting($ny, $now)['text']);
        $this->assertSame('Good evening', $this->service()->greeting($utc, $now)['text']);
        $this->assertSame('Nora', $this->service()->greeting($ny, $now)['first_name'], 'a single name stays whole');
    }

    public function test_the_greeting_changes_exactly_at_noon_and_six(): void
    {
        $at = fn (string $time) => $this->service()->greeting($this->admin, CarbonImmutable::parse("2026-10-20 {$time}", 'Asia/Kuala_Lumpur'))['text'];

        $this->assertSame('Good morning', $at('11:59'));
        $this->assertSame('Good afternoon', $at('12:00'));
        $this->assertSame('Good afternoon', $at('17:59'));
        $this->assertSame('Good evening', $at('18:00'));
        $this->assertSame('Good morning', $at('00:00'));
    }

    // ---- empty, privacy and cost ----------------------------------------------------------------------------------------------------

    public function test_an_empty_database_gives_every_part_without_errors(): void
    {
        $data = $this->service()->build($this->admin);

        $this->assertSame(['pending_organizations', 'pending_counselors', 'active_complaints', 'skipped_dns'], array_keys($data['cards']));
        $this->assertSame([0, 0, 0, 0], array_column($data['cards'], 'count'));
        $this->assertSame([], $data['attention']['items']);
        $this->assertSame(0, $data['attention']['total']);
        $this->assertSame(['organizations' => 0, 'counselors' => 0, 'members' => 0, 'sessions' => ['count' => 0, 'has_data' => false]], $data['stats']);
        $this->assertSame([], $data['approvals']['rows']);
        $this->assertSame('hidden', $data['banner']['state']);
        $this->assertSame('Aminah', $data['greeting']['first_name']);
    }

    public function test_nothing_private_passes_through_the_service(): void
    {
        $org = $this->pendingOrg(9, 0, 'Visible Org Name');
        $org->forceFill(['rejection_reason' => 'PRIVATE-REASON'])->saveQuietly();
        $profile = $this->pendingCounselor(9);
        $profile->reviewNotes()->create(['user_id' => $this->admin->id, 'body' => 'PRIVATE-NOTE-TEXT']);
        $this->complaint(ComplaintStatus::Open, 9, 'PRIVATE-COMPLAINT-TEXT');

        $json = json_encode($this->service()->build($this->admin));

        $this->assertStringContainsString('Visible Org Name', $json);
        foreach (['PRIVATE-REASON', 'PRIVATE-NOTE-TEXT', 'PRIVATE-COMPLAINT-TEXT', 'example.test', '@'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_the_number_of_queries_does_not_grow_with_the_data(): void
    {
        // One of everything first, so the baseline already runs every query a filled Dashboard runs.
        $this->pendingOrg(6);
        $this->skippedOrg(6);
        $this->pendingCounselor(6, inLkm: false);
        $this->complaint(ComplaintStatus::Open, 6);
        $this->approvalLog('organization.approved', 'Org 0', $this->ago(1));
        $first = $this->counselor('independent', 'approved');
        CounselorKbAttempt::create(['counselor_profile_id' => $first->id, 'kb_number' => $first->kb_number, 'submitted_name' => 'X', 'ip' => '203.0.113.1']);

        $this->service()->build($this->admin);   // warm-up: the platform settings are read once per request and then reused
        $few = $this->queries(fn () => $this->service()->build($this->admin));

        foreach (range(1, 25) as $i) {
            $this->pendingOrg(5 + $i);
            $this->skippedOrg(5 + $i);
            $this->pendingCounselor(5 + $i, inLkm: $i % 2 === 0);
            $this->complaint(ComplaintStatus::Open, 5 + $i);
            $this->approvalLog('organization.approved', "Org {$i}", Carbon::parse(self::NOW)->subDays($i)->format('Y-m-d H:i:s'));
            $profile = $this->counselor('independent', 'approved');
            CounselorKbAttempt::create(['counselor_profile_id' => $profile->id, 'kb_number' => $profile->kb_number, 'submitted_name' => 'X', 'ip' => '203.0.113.1']);
        }

        $many = $this->queries(fn () => $this->service()->build($this->admin));

        $this->assertSame($few, $many, 'the same queries however much data there is: no N+1');
        $this->assertLessThanOrEqual(30, $many, 'the service stays cheap ('.$many.' queries)');
    }
}
