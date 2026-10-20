<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\DomainMatchType;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\DomainVerificationReminder;
use App\Services\DomainVerificationService;
use App\Services\MembershipService;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationDnsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $session;

    /** Domains whose TXT record "exists" in the fake DNS. */
    private array $dnsHas = [];

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Organization::create([
            'name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active,
        ]);
        $this->admin = User::factory()->create(['name' => 'Admin Satu']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];

        // No real DNS in tests: a domain counts as verified only if the test says so.
        $test = $this;
        $this->app->bind(DomainVerificationService::class, fn () => new class($test) extends DomainVerificationService
        {
            public function __construct(private $test) {}

            protected function tokenPresentInDns(OrgDomain $orgDomain): bool
            {
                return in_array($orgDomain->domain, $this->test->dnsHas(), true);
            }
        });
    }

    public function dnsHas(): array
    {
        return $this->dnsHas;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    private function named(string $name): Organization
    {
        return Organization::where('name', $name)->firstOrFail();
    }

    private function skipped(): Organization
    {
        $this->seed(OrganizationReviewSeeder::class);

        return $this->named('Kolej Universiti Skip DNS'); // domain kuskip.edu.my, unverified, submitted 6 days ago
    }

    // ---- Check DNS now ---------------------------------------------------------------------

    public function test_check_dns_now_verifies_a_domain_whose_record_is_present(): void
    {
        $org = $this->skipped();
        $this->dnsHas = ['kuskip.edu.my'];

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org))
            ->assertRedirect(route('platform.organizations.show', $org).'#domains')
            ->assertSessionHas('status', 'DNS checked: 1 of 1 verified.');

        $domain = $org->orgDomains->first()->fresh();
        $this->assertTrue($domain->dns_verified);
        $this->assertNotNull($domain->verified_at);
        $this->assertSame(4, $domain->check_attempts);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_dns_checked', 'actor_user_id' => $this->admin->id, 'target_id' => $org->id]);

        // It now counts as a normal pending review, not a "skipped" one.
        $this->assertSame(0, Organization::skippedVerification()->count());
    }

    public function test_check_dns_now_reports_when_nothing_is_found(): void
    {
        $org = $this->skipped();

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org))
            ->assertSessionHas('error', 'DNS checked: no matching TXT record found yet.');

        $domain = $org->orgDomains->first()->fresh();
        $this->assertFalse($domain->dns_verified);
        $this->assertSame(4, $domain->check_attempts);
        $this->assertNotNull($domain->last_checked_at);
    }

    public function test_check_dns_creates_a_missing_token_first(): void
    {
        $org = $this->skipped();
        $org->orgDomains->first()->update(['verification_token' => null]);

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org));

        $this->assertStringStartsWith('mykaunsel-verify=', $org->orgDomains->first()->fresh()->verification_token);
    }

    public function test_dns_actions_are_refused_when_there_is_nothing_to_do(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        Notification::fake();

        $allVerified = $this->named('Universiti Teknologi Contoh');
        $clinic = $this->named('Klinik Minda Sihat');
        $rejected = $this->named('Syarikat Ditolak Enterprise');

        foreach ([$allVerified, $clinic, $rejected] as $org) {
            $this->as($this->admin)->post(route('platform.organizations.dns.check', $org))->assertSessionHas('error');
            $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))->assertSessionHas('error');
        }

        Notification::assertNothingSent();
    }

    public function test_only_platform_admins_can_use_dns_actions(): void
    {
        Notification::fake();
        $org = $this->skipped();
        $otherOrg = Organization::create(['name' => 'Org Aktif Lain', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
        $orgAdmin = User::factory()->create();
        Membership::create(['user_id' => $orgAdmin->id, 'org_id' => $otherOrg->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->flushSession();
        $orgSession = ['current_org_id' => $otherOrg->id, 'current_role' => 'org_admin'];
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.dns.check', $org))->assertForbidden();
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.dns.remind', $org))->assertForbidden();

        Notification::assertNothingSent();
    }

    // ---- Reminder email --------------------------------------------------------------------

    public function test_reminder_emails_the_admin_the_exact_txt_record_and_is_audited(): void
    {
        Notification::fake();
        $org = $this->skipped();
        $registrant = $org->registrant->user;
        $token = $org->orgDomains->first()->verification_token;

        $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))
            ->assertSessionHas('status', "Reminder emailed to {$registrant->email}.");

        Notification::assertSentTo($registrant, DomainVerificationReminder::class, function ($n) use ($registrant, $token, $org) {
            $mail = $n->toMail($registrant);
            $text = implode("\n", $mail->introLines);

            return str_contains($text, 'kuskip.edu.my')
                && str_contains($text, $token)
                && $mail->actionUrl === route('organizations.signup.verify-domain', $org);
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_dns_reminder_sent', 'target_id' => $org->id]);
    }

    public function test_reminders_are_rate_limited_per_organization(): void
    {
        Notification::fake();
        $org = $this->skipped();

        $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))->assertSessionHas('status');
        $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))->assertSessionHas('error');

        Notification::assertSentTimes(DomainVerificationReminder::class, 1);

        // Later (after the cool-down) it works again.
        $this->travel(7)->hours();
        $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))->assertSessionHas('status');
        Notification::assertSentTimes(DomainVerificationReminder::class, 2);
    }

    public function test_a_real_reminder_email_renders_without_errors(): void
    {
        $org = $this->skipped();

        $this->as($this->admin)->post(route('platform.organizations.dns.remind', $org))->assertSessionHas('status');
    }

    // ---- Detail page -----------------------------------------------------------------------

    public function test_detail_page_shows_pending_expired_and_the_action_buttons(): void
    {
        $org = $this->skipped();

        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertSee('Check DNS now')->assertSee('Send reminder email')
            ->assertSee('Pending')->assertSee('Auto-checks until')
            ->assertSee(route('platform.organizations.dns.check', $org), false);

        $org->orgDomains->first()->update(['dns_check_expired_at' => now()]);
        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertSee('Expired')->assertSee('Auto-checks stopped')->assertSee('Check DNS now');

        $verified = $this->named('Universiti Teknologi Contoh');
        $this->as($this->admin)->get(route('platform.organizations.show', $verified))
            ->assertDontSee('Check DNS now')->assertDontSee('Send reminder email');
    }

    // ---- Scheduler command -----------------------------------------------------------------

    public function test_command_checks_pending_domains_inside_the_window(): void
    {
        $org = $this->skipped();
        $this->dnsHas = ['kuskip.edu.my'];

        $this->artisan('organizations:check-dns')->expectsOutput('Checked 1, verified 1, expired 0, failed 0.')->assertSuccessful();

        $this->assertTrue($org->orgDomains->first()->fresh()->dns_verified);
    }

    public function test_command_expires_domains_after_seven_days_and_then_leaves_them_alone(): void
    {
        $org = $this->skipped();
        $org->update(['submitted_at' => now()->subDays(8)]);
        $this->dnsHas = ['kuskip.edu.my']; // even if DNS is now right, an expired window is not re-checked

        $this->artisan('organizations:check-dns')->expectsOutput('Checked 0, verified 0, expired 1, failed 0.');

        $domain = $org->orgDomains->first()->fresh();
        $this->assertNotNull($domain->dns_check_expired_at);
        $this->assertFalse($domain->dns_verified);
        $this->assertSame(3, $domain->check_attempts, 'no further checks were made');

        $this->artisan('organizations:check-dns')->expectsOutput('Checked 0, verified 0, expired 0, failed 0.');
    }

    public function test_command_ignores_rejected_and_suspended_organizations(): void
    {
        $org = $this->skipped();
        $this->dnsHas = ['kuskip.edu.my'];
        $org->update(['subscription_status' => OrgStatus::Rejected]);

        $this->artisan('organizations:check-dns')->expectsOutput('Checked 0, verified 0, expired 0, failed 0.');

        $org->update(['subscription_status' => OrgStatus::Suspended]);
        $this->artisan('organizations:check-dns')->expectsOutput('Checked 0, verified 0, expired 0, failed 0.');
        $this->assertFalse($org->orgDomains->first()->fresh()->dns_verified);
    }

    public function test_a_manual_check_can_still_rescue_an_expired_domain(): void
    {
        $org = $this->skipped();
        $org->orgDomains->first()->update(['dns_check_expired_at' => now()->subDay()]);
        $this->dnsHas = ['kuskip.edu.my'];

        $this->as($this->admin)->post(route('platform.organizations.dns.check', $org))->assertSessionHas('status');

        $domain = $org->orgDomains->first()->fresh();
        $this->assertTrue($domain->dns_verified);
        $this->assertNull($domain->dns_check_expired_at);
        $this->assertSame('verified', app(DomainVerificationService::class)->state($domain));
    }

    public function test_the_command_is_scheduled_every_thirty_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'organizations:check-dns'));

        $this->assertNotNull($event, 'organizations:check-dns is not scheduled');
        $this->assertSame('*/30 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // ---- Security: only DNS-verified domains may match members ----------------------------

    private function activeOrg(string $name, string $domain, bool $verified, OrgStatus $status = OrgStatus::Active, DomainMatchType $match = DomainMatchType::Exact): OrgDomain
    {
        $org = Organization::create(['name' => $name, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => $status]);

        return OrgDomain::create([
            'org_id' => $org->id, 'domain' => $domain, 'default_role' => MembershipRole::Student,
            'match_type' => $match, 'dns_verified' => $verified,
        ]);
    }

    public function test_an_unverified_domain_never_matches_an_email(): void
    {
        $this->activeOrg('Uni A', 'unia.edu.my', verified: false);
        $service = app(DomainVerificationService::class);

        $this->assertNull($service->verifiedDomainFor('ali@unia.edu.my'));
    }

    public function test_a_verified_domain_of_a_not_yet_active_organization_never_matches(): void
    {
        foreach ([OrgStatus::Pending, OrgStatus::Rejected, OrgStatus::Suspended] as $i => $status) {
            $this->activeOrg("Uni {$i}", "uni{$i}.edu.my", verified: true, status: $status);
            $this->assertNull(app(DomainVerificationService::class)->verifiedDomainFor("ali@uni{$i}.edu.my"), $status->value);
        }
    }

    public function test_a_verified_domain_of_an_active_organization_matches_including_wildcards_and_the_most_specific_wins(): void
    {
        $this->activeOrg('Uni Utama', 'uni.edu.my', verified: true, match: DomainMatchType::Wildcard);
        $faculty = $this->activeOrg('Fakulti', 'sains.uni.edu.my', verified: true);
        $service = app(DomainVerificationService::class);

        $this->assertSame('uni.edu.my', $service->verifiedDomainFor('ali@uni.edu.my')->domain);
        $this->assertSame('uni.edu.my', $service->verifiedDomainFor('ali@mail.uni.edu.my')->domain);
        $this->assertSame($faculty->id, $service->verifiedDomainFor('ali@sains.uni.edu.my')->id);
        $this->assertNull($service->verifiedDomainFor('ali@lain.com'));
        $this->assertNull($service->verifiedDomainFor('ali@evil-uni.edu.my'));
    }

    public function test_joining_via_domain_requires_a_verified_email_and_a_verified_domain_of_that_organization(): void
    {
        $verified = $this->activeOrg('Uni Sah', 'sah.edu.my', verified: true);
        $unverified = $this->activeOrg('Uni Belum', 'belum.edu.my', verified: false);
        $service = app(MembershipService::class);

        // Email not verified yet
        $imposter = User::factory()->unverified()->create(['email' => 'ali@sah.edu.my']);
        try {
            $service->joinViaDomain($imposter, $verified->organization);
            $this->fail('An unverified email must not join.');
        } catch (AuthorizationException) {
        }

        // Domain not DNS-verified
        $user = User::factory()->create(['email' => 'siti@belum.edu.my']);
        try {
            $service->joinViaDomain($user, $unverified->organization);
            $this->fail('An unverified domain must not grant membership.');
        } catch (AuthorizationException) {
        }

        // Right email, wrong organization
        $other = User::factory()->create(['email' => 'abu@sah.edu.my']);
        try {
            $service->joinViaDomain($other, $unverified->organization);
            $this->fail('A domain of another organization must not grant membership.');
        } catch (AuthorizationException) {
        }

        $this->assertSame(0, Membership::whereIn('user_id', [$imposter->id, $user->id, $other->id])->count());
    }

    public function test_joining_via_a_verified_domain_creates_the_membership_once(): void
    {
        $domain = $this->activeOrg('Uni Sah', 'sah.edu.my', verified: true);
        $user = User::factory()->create(['email' => 'ali@sah.edu.my']);
        $service = app(MembershipService::class);

        $membership = $service->joinViaDomain($user, $domain->organization);
        $again = $service->joinViaDomain($user, $domain->organization);

        $this->assertSame($membership->id, $again->id);
        $this->assertSame(MembershipRole::Student, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertSame(JoinSource::Domain, $membership->join_source);
        $this->assertSame(1, Membership::where('user_id', $user->id)->count());
    }
}
