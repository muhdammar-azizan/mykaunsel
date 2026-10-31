<?php

namespace Tests\Feature\Api;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\OrgMemberInvitation;
use App\Models\User;
use App\Notifications\MemberInvitation;
use App\Services\OrgMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Invitations by e-mail: the organization's admin invites an address; the person, signed in to the app with that address CONFIRMED, accepts
 * or declines under Account > Your organization. Accepting makes them an employee at once. The clock stands at 2 November 2026, 09:00.
 */
class OrganizationInvitationTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function invite(string $email, $org = null): OrgMemberInvitation
    {
        return app(OrgMemberService::class)->invite($org ?? $this->closed, $this->hr, $email);
    }

    private function person(string $email = 'aina@gmail.test', bool $verified = true): User
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => $verified ? now() : null]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_the_admin_invites_an_address_and_it_is_mailed_and_audited_without_the_address(): void
    {
        Notification::fake();

        $invitation = $this->invite('  Aina@Gmail.test ');

        $this->assertSame('aina@gmail.test', $invitation->email);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
        Notification::assertSentOnDemand(MemberInvitation::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'aina@gmail.test');

        $log = AuditLog::where('action', 'member.invited')->sole();
        $this->assertSame($invitation->id, $log->metadata['invitation_id']);
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
    }

    public function test_the_invited_person_sees_it_accepts_and_is_an_employee_at_once(): void
    {
        $invitation = $this->invite('aina@gmail.test');
        $user = $this->person('AINA@gmail.test'); // capitals do not matter

        $seen = $this->getJson('/api/v1/account/organization')->assertOk()
            ->assertJsonPath('organization', null)
            ->assertJsonPath('invitations.0.id', $invitation->public_id)
            ->assertJsonPath('invitations.0.organization', 'Universiti Contoh');

        $this->assertSame(1, count($seen->json('invitations')));

        $this->postJson("/api/v1/account/organization/invitations/{$invitation->public_id}/accept")->assertOk()
            ->assertJsonPath('organization.status', 'member')->assertJsonPath('invitations', []);

        $row = Membership::where('user_id', $user->id)->sole();
        $this->assertSame(MembershipStatus::Active, $row->status);
        $this->assertSame(MembershipRole::Employee, $row->role);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertCount(1, AuditLog::where('action', 'member.invitation_accepted')->get());
    }

    public function test_declining_closes_it_and_changes_nothing_else(): void
    {
        $invitation = $this->invite('aina@gmail.test');
        $user = $this->person();

        $this->postJson("/api/v1/account/organization/invitations/{$invitation->public_id}/decline")->assertOk()->assertJsonPath('invitations', []);

        $this->assertNotNull($invitation->fresh()->declined_at);
        $this->assertSame(0, Membership::where('user_id', $user->id)->count());
        $this->postJson("/api/v1/account/organization/invitations/{$invitation->public_id}/accept")->assertNotFound()->assertJsonPath('code', 'invitation_not_found');
    }

    public function test_only_the_invited_confirmed_address_can_see_or_answer_it(): void
    {
        $invitation = $this->invite('aina@gmail.test');
        $url = "/api/v1/account/organization/invitations/{$invitation->public_id}";

        $this->person('someone.else@gmail.test');
        $this->getJson('/api/v1/account/organization')->assertJsonPath('invitations', []);
        $this->postJson("$url/accept")->assertNotFound();
        $this->postJson("$url/decline")->assertNotFound();

        // the same address, but not confirmed: the address is not proven to be theirs
        $unconfirmed = User::factory()->create(['email' => 'aina@gmail.test', 'email_verified_at' => null]);
        $this->assertSame(0, app(\App\Services\MembershipService::class)->invitationsFor($unconfirmed)->count());
        $this->assertThrows(fn () => app(\App\Services\MembershipService::class)->acceptInvitation($unconfirmed, $invitation->public_id), \App\Exceptions\MembershipRefused::class);

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertSame(0, Membership::where('org_id', $this->closed->id)->where('role', MembershipRole::Employee->value)->count());
    }

    public function test_a_cancelled_expired_or_unknown_invitation_is_gone_and_a_suspended_organization_does_not_count(): void
    {
        $cancelled = $this->invite('aina@gmail.test');
        app(OrgMemberService::class)->revokeInvitation($this->closed, $cancelled->id, $this->hr);
        app(OrgMemberService::class)->revokeInvitation($this->closed, $cancelled->id, $this->hr); // safe to repeat
        $this->assertSame(1, AuditLog::where('action', 'member.invitation_revoked')->count());

        $this->person();
        $this->getJson('/api/v1/account/organization')->assertJsonPath('invitations', []);
        $this->postJson("/api/v1/account/organization/invitations/{$cancelled->public_id}/accept")->assertNotFound();
        $this->postJson('/api/v1/account/organization/invitations/'.str_repeat('0', 26).'/accept')->assertNotFound();

        $expired = $this->invite('aina@gmail.test');
        $this->travel(31)->days();
        $this->getJson('/api/v1/account/organization')->assertJsonPath('invitations', []);
        $this->postJson("/api/v1/account/organization/invitations/{$expired->public_id}/accept")->assertNotFound();
    }

    public function test_accepting_follows_the_one_organization_rule(): void
    {
        $invitation = $this->invite('aina@gmail.test');
        $user = $this->person();
        Membership::create(['user_id' => $user->id, 'org_id' => $this->open->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->postJson("/api/v1/account/organization/invitations/{$invitation->public_id}/accept")
            ->assertStatus(409)->assertJsonPath('code', 'in_other_organization');

        $this->assertNull($invitation->fresh()->accepted_at, 'a refused answer leaves the invitation open');
        $this->assertSame(0, Membership::where('user_id', $user->id)->where('org_id', $this->closed->id)->count());
    }

    public function test_someone_waiting_with_a_code_needs_no_approval_once_invited(): void
    {
        $invitation = $this->invite('aina@gmail.test');
        $user = $this->person();
        $row = Membership::create(['user_id' => $user->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::PendingApproval, 'joined_at' => now()]);

        $this->postJson("/api/v1/account/organization/invitations/{$invitation->public_id}/accept")->assertOk()->assertJsonPath('organization.status', 'member');

        $this->assertSame(MembershipStatus::Active, $row->fresh()->status);
        $this->assertSame(1, Membership::where('user_id', $user->id)->count());
    }

    public function test_the_admin_can_not_invite_a_member_or_the_same_address_twice(): void
    {
        $member = User::factory()->create(['email' => 'budi@gmail.test']);
        Membership::create(['user_id' => $member->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->assertThrows(fn () => $this->invite('Budi@gmail.test'), \RuntimeException::class, 'already belongs');

        $this->invite('aina@gmail.test');
        $this->assertThrows(fn () => $this->invite('AINA@gmail.test'), \RuntimeException::class, 'already been invited');
    }

    public function test_the_members_page_invites_lists_and_cancels_and_keeps_organizations_apart(): void
    {
        Notification::fake();
        $session = ['current_org_id' => $this->closed->id, 'current_role' => 'org_admin'];

        $this->actingAs($this->hr)->withSession($session)->post(route('org.invitations.store'), ['email' => 'aina@gmail.test'])->assertSessionHas('status');
        $this->actingAs($this->hr)->withSession($session)->post(route('org.invitations.store'), ['email' => 'aina@gmail.test'])->assertSessionHas('error');
        $this->actingAs($this->hr)->withSession($session)->post(route('org.invitations.store'), ['email' => 'not an address'])->assertSessionHasErrors('email');

        $this->actingAs($this->hr)->withSession($session)->get(route('org.members'))->assertOk()->assertSee('aina@gmail.test')->assertSee('Waiting');

        $mine = OrgMemberInvitation::sole();
        $theirs = $this->invite('orang@beta.test', $this->open);

        $this->actingAs($this->hr)->withSession($session)->post(route('org.invitations.revoke', $theirs->id))->assertNotFound();
        $this->assertTrue($theirs->fresh()->isOpen());

        $this->actingAs($this->hr)->withSession($session)->post(route('org.invitations.revoke', $mine->id))->assertSessionHas('status');
        $this->assertNotNull($mine->fresh()->revoked_at);
        $this->actingAs($this->hr)->withSession($session)->get(route('org.members'))->assertSee('Cancelled')->assertDontSee('orang@beta.test');
    }

    public function test_a_failing_mail_does_not_undo_the_invitation(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $invitation = $this->invite('aina@gmail.test');

        $this->assertTrue($invitation->fresh()->isOpen());
    }
}
