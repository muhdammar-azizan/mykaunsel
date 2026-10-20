<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\ComplaintStatus;
use App\Enums\DomainMatchType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\User;
use App\Services\DomainVerificationService;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $session;

    /** Domains whose TXT record "exists" in the fake DNS. */
    public array $dnsHas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Organization::create([
            'name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active,
        ]);
        $this->admin = $this->person('Admin Satu', $platform, MembershipRole::PlatformAdmin);
        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];

        $test = $this;
        $this->app->bind(DomainVerificationService::class, fn () => new class($test) extends DomainVerificationService
        {
            public function __construct(private $test) {}

            protected function tokenPresentInDns(OrgDomain $orgDomain): bool
            {
                return in_array($orgDomain->domain, $this->test->dnsHas, true);
            }
        });
    }

    private function person(string $name, Organization $org, MembershipRole $role, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    private function activeOrg(string $name = 'Universiti Aktif'): Organization
    {
        return Organization::create([
            'name' => $name, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Active, 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()->subDays(20),
        ]);
    }

    // ---- Suspend / reactivate --------------------------------------------------------------

    public function test_suspending_requires_a_reason_and_changes_nothing_without_one(): void
    {
        $org = $this->activeOrg();

        foreach (['', '   ', 'pendek', str_repeat('a', 1001)] as $bad) {
            $this->as($this->admin)->from(route('platform.organizations.show', $org))
                ->post(route('platform.organizations.suspend', $org), ['reason' => $bad])
                ->assertSessionHasErrors('reason', null, 'suspend');
        }

        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'organization_suspended']);
    }

    public function test_suspending_saves_reason_time_and_audit_and_keeps_the_original_approval(): void
    {
        $org = $this->activeOrg();
        $member = $this->person('Ahli', $org, MembershipRole::Student);

        $this->as($this->admin)->post(route('platform.organizations.suspend', $org), ['reason' => 'Aduan berulang tentang amalan tidak sah.'])
            ->assertRedirect(route('platform.organizations.show', $org))
            ->assertSessionHas('status');

        $org->refresh();
        $this->assertSame(OrgStatus::Suspended, $org->subscription_status);
        $this->assertSame('Aduan berulang tentang amalan tidak sah.', $org->suspension_reason);
        $this->assertNotNull($org->suspended_at);
        $this->assertSame($this->admin->id, $org->reviewed_by, 'approval details are untouched');
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_suspended', 'actor_user_id' => $this->admin->id, 'target_id' => $org->id]);
        $this->assertNotNull($member->fresh(), 'no data is deleted');
        $this->assertSame(1, Membership::where('org_id', $org->id)->where('user_id', $member->id)->count());
    }

    public function test_reactivating_restores_the_organization_and_clears_the_reason(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $org = Organization::where('name', 'Pusat Kaunseling Digantung')->first();

        $this->as($this->admin)->post(route('platform.organizations.reactivate', $org))->assertSessionHas('status');

        $org->refresh();
        $this->assertSame(OrgStatus::Active, $org->subscription_status);
        $this->assertNull($org->suspension_reason);
        $this->assertNull($org->suspended_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_reactivated', 'target_id' => $org->id]);
    }

    public function test_only_the_right_state_can_be_suspended_or_reactivated(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $pending = Organization::where('name', 'Universiti Teknologi Contoh')->first();
        $rejected = Organization::where('name', 'Syarikat Ditolak Enterprise')->first();
        $active = $this->activeOrg();

        foreach ([$pending, $rejected] as $org) {
            $this->as($this->admin)->post(route('platform.organizations.suspend', $org), ['reason' => 'Cuba gantung yang belum aktif.'])->assertSessionHas('error');
            $this->as($this->admin)->post(route('platform.organizations.reactivate', $org))->assertSessionHas('error');
        }
        $this->as($this->admin)->post(route('platform.organizations.reactivate', $active))->assertSessionHas('error');

        $this->assertSame(OrgStatus::Pending, $pending->fresh()->subscription_status);
        $this->assertSame(OrgStatus::Rejected, $rejected->fresh()->subscription_status);
        $this->assertSame(OrgStatus::Active, $active->fresh()->subscription_status);
    }

    public function test_a_second_admin_on_a_stale_page_is_told_who_suspended_it(): void
    {
        $org = $this->activeOrg();
        $other = $this->person('Admin Dua', Organization::where('org_type', OrgType::Platform->value)->first(), MembershipRole::PlatformAdmin);

        $this->as($other)->post(route('platform.organizations.suspend', $org), ['reason' => 'Admin dua gantung dahulu.']);
        $this->as($this->admin)->post(route('platform.organizations.suspend', $org), ['reason' => 'Admin satu lambat sedikit.'])
            ->assertSessionHas('error');

        $this->assertStringContainsString('Admin Dua', session('error'));
        $this->assertSame('Admin dua gantung dahulu.', $org->fresh()->suspension_reason);
    }

    public function test_org_admins_cannot_suspend_or_reactivate(): void
    {
        $org = $this->activeOrg();
        $orgAdmin = $this->person('Pentadbir Org', $org, MembershipRole::OrgAdmin);
        $orgSession = ['current_org_id' => $org->id, 'current_role' => 'org_admin'];

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.suspend', $org), ['reason' => 'Gantung diri sendiri'])->assertForbidden();
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.reactivate', $org))->assertForbidden();

        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
    }

    // ---- Access is really cut off ----------------------------------------------------------

    public function test_suspension_locks_out_org_admins_members_and_counselors_and_reactivation_lets_them_back(): void
    {
        $org = $this->activeOrg();
        $orgAdmin = $this->person('Pentadbir Org', $org, MembershipRole::OrgAdmin);
        $counselor = $this->person('Kaunselor', $org, MembershipRole::Counselor);

        $adminSession = ['current_org_id' => $org->id, 'current_role' => 'org_admin'];
        $counselorSession = ['current_org_id' => $org->id, 'current_role' => 'counselor'];

        $this->actingAs($orgAdmin)->withSession($adminSession)->get(route('org.dashboard'))->assertOk();
        $this->actingAs($counselor)->withSession($counselorSession)->get(route('counselor.dashboard'))->assertOk();

        $this->as($this->admin)->post(route('platform.organizations.suspend', $org), ['reason' => 'Gantung untuk siasatan dalaman.']);

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($adminSession)->get(route('org.dashboard'))->assertRedirect(route('organization.suspended'));
        $this->actingAs($counselor)->withSession($counselorSession)->get(route('counselor.dashboard'))->assertRedirect(route('organization.suspended'));

        $this->as($this->admin)->post(route('platform.organizations.reactivate', $org));

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($adminSession)->get(route('org.dashboard'))->assertOk();
        $this->actingAs($counselor)->withSession($counselorSession)->get(route('counselor.dashboard'))->assertOk();
    }

    public function test_a_pending_organization_cannot_use_the_app_yet(): void
    {
        $org = Organization::create(['name' => 'Baru', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Pending]);
        $orgAdmin = $this->person('Pemohon', $org, MembershipRole::OrgAdmin);

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->get(route('org.dashboard'))->assertRedirect(route('organization.pending'));
    }

    public function test_the_platform_admin_is_never_locked_out(): void
    {
        $this->as($this->admin)->get(route('platform.dashboard'))->assertOk();
        $this->as($this->admin)->get(route('platform.organizations'))->assertOk();
    }

    public function test_someone_in_two_organizations_keeps_the_other_one_when_one_is_suspended(): void
    {
        $suspended = $this->activeOrg('Org Digantung');
        $fine = $this->activeOrg('Org Baik');
        $user = User::factory()->create();
        foreach ([$suspended, $fine] as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }
        $suspended->update(['subscription_status' => OrgStatus::Suspended]);

        $this->actingAs($user)->withSession(['current_org_id' => $suspended->id, 'current_role' => 'org_admin'])->get(route('org.dashboard'))->assertRedirect(route('organization.suspended'));
        $this->actingAs($user)->withSession(['current_org_id' => $fine->id, 'current_role' => 'org_admin'])->get(route('org.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('organization.suspended'))->assertSee('Pilih organisasi lain');
    }

    // ---- Detail page for approved organizations --------------------------------------------

    public function test_overview_numbers_are_correct(): void
    {
        $org = $this->activeOrg();
        $other = $this->activeOrg('Org Lain');

        $this->person('Pelajar 1', $org, MembershipRole::Student);
        $this->person('Pelajar 2', $org, MembershipRole::Student);
        $this->person('Staf', $org, MembershipRole::Staff);
        $this->person('Bekas Pelajar', $org, MembershipRole::Student, MembershipStatus::Offboarded);
        $this->person('Pentadbir', $org, MembershipRole::OrgAdmin);
        $counselor = $this->person('Kaunselor', $org, MembershipRole::Counselor);
        $this->person('Pelajar Lain', $other, MembershipRole::Student);

        $student = User::where('name', 'Pelajar 1')->first();
        $mk = fn (Organization $o, $when) => tap(Booking::create([
            'org_id' => $o->id, 'user_id' => $student->id, 'counselor_user_id' => $counselor->id,
            'booking_mode' => BookingMode::SlotBased, 'session_mode' => SessionMode::Online, 'status' => BookingStatus::Confirmed,
        ]), fn ($b) => $b->forceFill(['created_at' => $when])->save());
        $thisMonth = $mk($org, now());
        $mk($org, now()->subMonths(2));
        $mk($other, now());

        Complaint::create(['reporter_user_id' => $student->id, 'counselor_user_id' => $counselor->id, 'booking_id' => $thisMonth->id, 'description' => 'Aduan terbuka', 'status' => ComplaintStatus::Open]);
        Complaint::create(['reporter_user_id' => $student->id, 'counselor_user_id' => $counselor->id, 'booking_id' => $thisMonth->id, 'description' => 'Aduan lama', 'status' => ComplaintStatus::Dismissed]);

        $overview = $this->as($this->admin)->get(route('platform.organizations.show', $org))->assertOk()->viewData('overview');

        $this->assertSame(3, $overview['members']);
        $this->assertSame(1, $overview['counselors']);
        $this->assertSame(1, $overview['sessions_this_month']);
        $this->assertSame(2, $overview['complaints_total']);
        $this->assertSame(1, $overview['complaints_open']);
        $this->assertSame($org->reviewed_at->timestamp, $overview['approved_at']->timestamp);
    }

    public function test_manage_buttons_follow_the_status(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $active = $this->activeOrg();
        $suspended = Organization::where('name', 'Pusat Kaunseling Digantung')->first();
        $pending = Organization::where('name', 'Universiti Teknologi Contoh')->first();
        $rejected = Organization::where('name', 'Syarikat Ditolak Enterprise')->first();

        $this->as($this->admin)->get(route('platform.organizations.show', $active))
            ->assertSee('Suspend organization')->assertDontSee('Reactivate organization')
            ->assertSee('Sessions this month')->assertSee('Subscription')->assertSee('Not set')
            ->assertSee('View complaints involving this organization')
            ->assertSee(route('platform.complaints', ['organization' => $active->id]), false)
            ->assertSee('All data is kept');

        $this->as($this->admin)->get(route('platform.organizations.show', $suspended))
            ->assertSee('Reactivate organization')->assertDontSee('Suspend organization')
            ->assertSee('Repeated complaints about unlicensed practitioners');

        foreach ([$pending, $rejected] as $org) {
            $this->as($this->admin)->get(route('platform.organizations.show', $org))
                ->assertDontSee('Suspend organization')->assertDontSee('Reactivate organization')->assertDontSee('Sessions this month');
        }
    }

    public function test_suspend_and_reactivate_appear_in_the_activity_timeline(): void
    {
        $org = $this->activeOrg();
        $this->as($this->admin)->post(route('platform.organizations.suspend', $org), ['reason' => 'Siasatan sedang dijalankan.']);
        $this->as($this->admin)->post(route('platform.organizations.reactivate', $org));

        $titles = app(\App\Services\OrganizationTimeline::class)->for($org->fresh())->pluck('title')->all();

        $this->assertSame('Reactivated', $titles[0]);
        $this->assertSame('Suspended', $titles[1]);

        $this->as($this->admin)->get(route('platform.organizations.show', $org))->assertSee('Siasatan sedang dijalankan.');
    }

    // ---- Re-verify a single domain ---------------------------------------------------------

    private function verifiedDomain(Organization $org, string $domain = 'aktif.edu.my'): OrgDomain
    {
        return OrgDomain::create([
            'org_id' => $org->id, 'domain' => $domain, 'default_role' => MembershipRole::Student,
            'match_type' => DomainMatchType::Exact, 'verification_token' => 'mykaunsel-verify=abc',
            'dns_verified' => true, 'verified_at' => now()->subDays(3),
        ]);
    }

    public function test_reverifying_a_domain_whose_record_is_still_there_keeps_it_verified(): void
    {
        $org = $this->activeOrg();
        $domain = $this->verifiedDomain($org);
        $this->dnsHas = ['aktif.edu.my'];

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org), ['domain' => $domain->id])
            ->assertSessionHas('status', 'DNS checked: 1 of 1 verified.');

        $this->assertTrue($domain->fresh()->dns_verified);
    }

    public function test_reverifying_a_domain_whose_record_is_gone_unverifies_it_and_stops_member_matching(): void
    {
        $org = $this->activeOrg();
        $domain = $this->verifiedDomain($org);
        $service = app(DomainVerificationService::class);
        $this->assertNotNull($service->verifiedDomainFor('ali@aktif.edu.my'));

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org), ['domain' => $domain->id])
            ->assertSessionHas('error');
        $this->assertStringContainsString('aktif.edu.my is no longer verified', session('error'));

        $this->assertFalse($domain->fresh()->dns_verified);
        $this->assertNull($service->verifiedDomainFor('ali@aktif.edu.my'), 'the lapsed domain no longer matches members');
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_dns_checked', 'target_id' => $org->id]);
    }

    public function test_a_domain_of_another_organization_cannot_be_reverified_through_this_one(): void
    {
        $org = $this->activeOrg();
        $foreign = $this->verifiedDomain($this->activeOrg('Org Lain'), 'lain.edu.my');

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org), ['domain' => $foreign->id])->assertNotFound();
        $this->assertTrue($foreign->fresh()->dns_verified);
    }

    public function test_reverify_buttons_show_only_for_active_organizations(): void
    {
        $active = $this->activeOrg();
        $this->verifiedDomain($active);
        $pending = Organization::create(['name' => 'Belum', 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Pending]);
        $this->verifiedDomain($pending, 'belum.edu.my');

        $this->as($this->admin)->get(route('platform.organizations.show', $active))->assertSee('Re-verify');
        $this->as($this->admin)->get(route('platform.organizations.show', $pending))->assertDontSee('Re-verify');
    }
}
