<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationApproved;
use App\Notifications\OrganizationRejected;
use Database\Seeders\OrganizationReviewSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrganizationDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $platform;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $this->admin = $this->platformAdmin('Admin Satu');
        $this->session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
    }

    private function platformAdmin(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create([
            'user_id' => $user->id,
            'org_id' => $this->platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    private function pending(): Organization
    {
        $this->seed(OrganizationReviewSeeder::class);

        return Organization::where('name', 'Universiti Teknologi Contoh')->firstOrFail();
    }

    public function test_approving_activates_the_organization_audits_and_emails_the_admin(): void
    {
        Notification::fake();
        $org = $this->pending();
        $registrant = $org->registrant->user;

        $this->as($this->admin)->post(route('platform.organizations.approve', $org))
            ->assertRedirect(route('platform.organizations.show', $org))
            ->assertSessionHas('status');

        $org->refresh();
        $this->assertSame(OrgStatus::Active, $org->subscription_status);
        $this->assertSame($this->admin->id, $org->reviewed_by);
        $this->assertNotNull($org->reviewed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'organization_approved', 'actor_user_id' => $this->admin->id,
            'target_type' => Organization::class, 'target_id' => $org->id,
        ]);
        Notification::assertSentTo($registrant, OrganizationApproved::class);
    }

    public function test_rejecting_saves_the_reason_audits_and_emails_it_to_the_applicant(): void
    {
        Notification::fake();
        $org = $this->pending();
        $registrant = $org->registrant->user;

        $this->as($this->admin)->post(route('platform.organizations.reject', $org), ['reason' => 'SSM number does not match.'])
            ->assertRedirect(route('platform.organizations.show', $org))
            ->assertSessionHas('status');

        $org->refresh();
        $this->assertSame(OrgStatus::Rejected, $org->subscription_status);
        $this->assertSame('SSM number does not match.', $org->rejection_reason);
        $this->assertSame($this->admin->id, $org->reviewed_by);

        $log = AuditLog::where('action', 'organization_rejected')->where('target_id', $org->id)->firstOrFail();
        $this->assertSame('SSM number does not match.', $log->metadata['reason']);

        Notification::assertSentTo($registrant, OrganizationRejected::class, function ($n) use ($registrant) {
            $mail = $n->toMail($registrant);

            return str_contains(implode(' ', $mail->introLines), 'SSM number does not match.');
        });
    }

    public function test_a_rejection_reason_is_required_and_long_enough(): void
    {
        Notification::fake();
        $org = $this->pending();

        foreach (['', '   ', 'short', str_repeat('a', 1001)] as $bad) {
            $this->as($this->admin)->from(route('platform.organizations.show', $org))
                ->post(route('platform.organizations.reject', $org), ['reason' => $bad])
                ->assertSessionHasErrors('reason');
        }

        $this->assertSame(OrgStatus::Pending, $org->fresh()->subscription_status);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'organization_rejected', 'target_id' => $org->id]);
        Notification::assertNothingSent();
    }

    public function test_second_admin_deciding_on_a_stale_page_is_refused_and_nothing_changes(): void
    {
        Notification::fake();
        $org = $this->pending();
        $other = $this->platformAdmin('Admin Dua');

        // Admin Dua approves first.
        $this->as($other)->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        // Admin Satu still has the old page open and tries to reject.
        $response = $this->as($this->admin)->post(route('platform.organizations.reject', $org), ['reason' => 'Terlambat menolak ini.']);

        $response->assertRedirect(route('platform.organizations.show', $org))->assertSessionHas('error');
        $this->assertStringContainsString('Admin Dua', session('error'));

        $org->refresh();
        $this->assertSame(OrgStatus::Active, $org->subscription_status);
        $this->assertSame($other->id, $org->reviewed_by);
        $this->assertNull($org->rejection_reason);
        $this->assertSame(1, AuditLog::whereIn('action', ['organization_approved', 'organization_rejected'])->where('target_id', $org->id)->count());
        Notification::assertSentTimes(OrganizationApproved::class, 1);
        Notification::assertNotSentTo($org->registrant->user, OrganizationRejected::class);
    }

    public function test_only_pending_applications_can_be_decided(): void
    {
        Notification::fake();
        $this->seed(OrganizationReviewSeeder::class);

        foreach (['Pusat Kaunseling Digantung', 'Syarikat Ditolak Enterprise'] as $name) {
            $org = Organization::where('name', $name)->first();
            $before = $org->subscription_status;

            $this->as($this->admin)->post(route('platform.organizations.approve', $org))->assertSessionHas('error');
            $this->assertSame($before, $org->fresh()->subscription_status);
        }

        Notification::assertNothingSent();
    }

    public function test_the_real_emails_render_and_send_without_errors(): void
    {
        $approved = $this->pending();
        $this->as($this->admin)->post(route('platform.organizations.approve', $approved))->assertSessionHas('status');

        $rejected = Organization::where('name', 'Klinik Minda Sihat')->first();
        $this->as($this->admin)->post(route('platform.organizations.reject', $rejected), ['reason' => 'Gambar premis tidak jelas.'])->assertSessionHas('status');

        $this->assertSame(OrgStatus::Active, $approved->fresh()->subscription_status);
        $this->assertSame(OrgStatus::Rejected, $rejected->fresh()->subscription_status);
    }

    public function test_a_decision_still_works_when_the_applicant_has_no_admin_to_email(): void
    {
        Notification::fake();
        $org = Organization::create([
            'name' => 'Tanpa Admin', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Pending,
        ]);

        $this->as($this->admin)->post(route('platform.organizations.approve', $org))->assertSessionHas('status');

        $this->assertSame(OrgStatus::Active, $org->fresh()->subscription_status);
        Notification::assertNothingSent();
    }

    public function test_org_admins_and_guests_cannot_decide(): void
    {
        Notification::fake();
        $org = $this->pending();
        $this->post(route('platform.organizations.approve', $org))->assertRedirect();

        // An admin of a different, active organization reaches the role check and is refused.
        $other = Organization::create(['name' => 'Org Lain', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
        $orgAdmin = User::factory()->create();
        Membership::create(['user_id' => $orgAdmin->id, 'org_id' => $other->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $orgSession = ['current_org_id' => $other->id, 'current_role' => 'org_admin'];

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.approve', $org))->assertForbidden();
        $this->actingAs($orgAdmin)->withSession($orgSession)->post(route('platform.organizations.reject', $org), ['reason' => 'Saya lulus sendiri'])->assertForbidden();

        $this->flushSession();
        // The applicant's own admin can't use the app (or these routes) until the organization is approved.
        $this->actingAs($org->registrant->user)->withSession(['current_org_id' => $org->id, 'current_role' => 'org_admin'])
            ->post(route('platform.organizations.approve', $org))->assertRedirect(route('organization.pending'));

        $this->assertSame(OrgStatus::Pending, $org->fresh()->subscription_status);
    }

    public function test_the_platform_organization_cannot_be_decided(): void
    {
        $this->as($this->admin)->post(route('platform.organizations.reject', $this->platform), ['reason' => 'Cuba tolak platform'])->assertNotFound();
        $this->assertSame(OrgStatus::Active, $this->platform->fresh()->subscription_status);
    }

    public function test_detail_page_shows_decision_buttons_only_while_pending(): void
    {
        $org = $this->pending();

        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertSee('Approve organization')
            ->assertSee('Reject application')
            ->assertSee('sent to the applicant')
            ->assertSee(route('platform.organizations.approve', $org), false)
            ->assertSee(route('platform.organizations.reject', $org), false);

        $this->as($this->admin)->post(route('platform.organizations.approve', $org));

        $this->as($this->admin)->get(route('platform.organizations.show', $org))
            ->assertDontSee('Approve organization')
            ->assertDontSee('Reject application')
            ->assertSee('Manage organization')
            ->assertSee('Approved by Admin Satu');
    }

    public function test_approve_modal_warns_about_unverified_domains(): void
    {
        $this->seed(OrganizationReviewSeeder::class);
        $skipped = Organization::where('name', 'Kolej Universiti Skip DNS')->first();
        $verified = Organization::where('name', 'Universiti Teknologi Contoh')->first();

        $this->as($this->admin)->get(route('platform.organizations.show', $skipped))
            ->assertSee('not DNS-verified — members will not be matched');
        $this->as($this->admin)->get(route('platform.organizations.show', $verified))
            ->assertDontSee('members will not be matched by email domain until verified');
    }

    public function test_a_reopened_reject_modal_keeps_what_was_typed(): void
    {
        $org = $this->pending();

        $this->as($this->admin)->from(route('platform.organizations.show', $org))
            ->post(route('platform.organizations.reject', $org), ['reason' => 'pendek'])
            ->assertSessionHasErrors('reason');

        $this->as($this->admin)->withSession(['_old_input' => ['reason' => 'pendek'], 'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['reason' => ['x']]))])
            ->get(route('platform.organizations.show', $org))
            ->assertSee('pendek');
    }
}
