<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\AdminInvitation;
use App\Notifications\PlatformAdminInvitation;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

class PlatformAdminsTest extends CounselorTestCase
{
    use AdminInvitationHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function platformMembership(User $user): ?Membership
    {
        return Membership::where('user_id', $user->id)->where('org_id', $this->session['current_org_id'])->first();
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    public function test_only_platform_admins_can_manage_admins(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $target = $this->admin;
        $invitation = AdminInvitation::create(['name' => 'Someone', 'email' => 'someone@example.com', 'token_hash' => str_repeat('a', 64), 'active_email' => 'someone@example.com', 'status' => 'pending', 'invited_by_user_id' => $this->admin->id, 'expires_at' => now()->addDay(), 'last_sent_at' => now()]);

        foreach ([
            fn ($t) => $t->get(route('platform.admins')),
            fn ($t) => $t->post(route('platform.admins.store'), ['name' => 'X Y', 'email' => 'x@y.com']),
            fn ($t) => $t->post(route('platform.admins.invitations.resend', $invitation)),
            fn ($t) => $t->post(route('platform.admins.invitations.revoke', $invitation)),
            fn ($t) => $t->get(route('platform.admins', ['tab' => 'invitations'])),
            fn ($t) => $t->post(route('platform.admins.remove', $target), ['reason' => 'Because I want to.']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->assertNull(User::where('email', 'x@y.com')->first());
        $this->assertSame('pending', $invitation->fresh()->status->value);
    }

    public function test_the_page_lists_the_admins_and_is_in_the_menu(): void
    {
        $this->as()->get(route('platform.admins'))->assertOk()
            ->assertSee('Platform Administrator')->assertSee('admin@mykaunsel.com')->assertSee('You')
            ->assertSee('Not signed in yet');

        $this->as()->get(route('platform.dashboard'))->assertSee(route('platform.admins'), false);
    }

    public function test_inviting_changes_nothing_for_the_person_until_they_accept(): void
    {
        [$invitation, $url] = $this->invite('Nur Aisyah', 'Aisyah@MyKaunsel.com');

        $this->assertSame('aisyah@mykaunsel.com', $invitation->email);
        $this->assertSame('pending', $invitation->status->value);
        $this->assertNull(User::where('email', 'aisyah@mykaunsel.com')->first(), 'no account yet');
        $this->assertSame(1, Membership::where('role', 'platform_admin')->count(), 'no role yet');

        Notification::assertSentOnDemand(PlatformAdminInvitation::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'aisyah@mykaunsel.com');
        $this->assertStringContainsString('/admin-invitation/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('/reset-password/', $url, 'no longer the password-reset link');
    }

    public function test_the_invitation_is_audited_by_number_without_the_name_or_the_email(): void
    {
        [$invitation] = $this->invite('Nur Aisyah', 'aisyah@mykaunsel.com');

        $log = $this->logs('admin.invited')->sole();
        $this->assertSame(['admin_invitation', $invitation->id, "Admin invitation #{$invitation->id}", $this->admin->id], [$log->target_type, (int) $log->target_id, $log->target_name, (int) $log->actor_user_id]);

        $row = strtolower(json_encode($log->getAttributes()));
        $this->assertStringNotContainsString('aisyah', $row);
    }

    public function test_adding_validates_name_and_email(): void
    {
        $this->as()->post(route('platform.admins.store'), ['name' => '', 'email' => 'not-an-email'])
            ->assertSessionHasErrorsIn('invite', ['name', 'email']);

        $this->assertCount(0, $this->logs('admin.invited'));
    }

    public function test_adding_an_existing_admin_is_refused(): void
    {
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => 'Whoever', 'email' => 'ADMIN@mykaunsel.com'])
            ->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame(0, AdminInvitation::count());
        $this->assertSame(1, Membership::where('role', 'platform_admin')->count());
        $this->assertCount(0, $this->logs('admin.invited'));
    }

    public function test_removing_keeps_the_row_blocks_access_and_is_audited_with_the_name(): void
    {
        $user = $this->addAdmin();

        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.'])->assertSessionHas('status');

        $membership = $this->platformMembership($user);
        $this->assertSame(MembershipStatus::Offboarded, $membership->status);
        $this->assertNotNull($membership->offboarded_at);
        $this->assertNotNull(User::find($user->id));

        $log = $this->logs('admin.removed')->sole();
        $this->assertSame('Left the team in October.', $log->metadata['reason']);
        $this->assertSame($user->name, $log->target_name);

        // Signed out on their next request.
        $this->flushSession();
        $this->actingAs($user)->withSession($this->session)->get(route('platform.dashboard'))->assertRedirect(route('login'));
    }

    public function test_removing_needs_a_reason(): void
    {
        $user = $this->addAdmin();

        $this->as()->post(route('platform.admins.remove', $user), ['reason' => ''])->assertSessionHasErrorsIn('remove-'.$user->id, 'reason');

        $this->assertSame(MembershipStatus::Active, $this->platformMembership($user)->status);
    }

    public function test_you_cannot_remove_yourself(): void
    {
        $this->addAdmin();

        $this->as()->post(route('platform.admins.remove', $this->admin), ['reason' => 'I want to leave now.'])->assertSessionHas('error');

        $this->assertSame(MembershipStatus::Active, $this->platformMembership($this->admin)->status);
        $this->assertCount(0, $this->logs('admin.removed'));
    }

    public function test_the_last_active_admin_cannot_be_removed(): void
    {
        $second = $this->addAdmin();
        // Second admin removes the first, leaving only the second.
        $this->flushSession();
        $this->as($second)->post(route('platform.admins.remove', $this->admin), ['reason' => 'Handing over the keys.'])->assertSessionHas('status');

        // The only active admin now is $second; another (stale) request cannot remove them.
        $third = User::factory()->create();
        Membership::create(['user_id' => $third->id, 'org_id' => $this->session['current_org_id'], 'role' => 'platform_admin', 'status' => 'offboarded', 'joined_at' => now()]);
        $this->flushSession();
        $this->as($this->admin)->post(route('platform.admins.remove', $second), ['reason' => 'Trying to empty it.']);

        $this->assertSame(MembershipStatus::Active, $this->platformMembership($second)->status);
        $this->assertCount(1, $this->logs('admin.removed'));
    }

    public function test_removing_someone_already_removed_changes_nothing(): void
    {
        $user = $this->addAdmin();
        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.']);

        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.'])->assertSessionHas('error');

        $this->assertCount(1, $this->logs('admin.removed'));
    }

    public function test_a_removed_admin_can_be_invited_again_and_is_restored_when_they_accept(): void
    {
        $user = $this->addAdmin();
        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.']);

        [, $url] = $this->invite('Nur Aisyah', $user->email);
        $this->assertSame(MembershipStatus::Offboarded, $this->platformMembership($user)->status, 'still removed until they accept');

        // Their account exists with a verified email, so they confirm with their own password.
        $this->asGuest()->post($url, ['password' => self::NEW_PASSWORD])->assertRedirect(route('login'));

        $membership = $this->platformMembership($user);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertNull($membership->offboarded_at);
        $this->assertSame(1, Membership::where('user_id', $user->id)->where('org_id', $this->session['current_org_id'])->count());
    }

    public function test_the_removed_tab_shows_who_and_why(): void
    {
        $user = $this->addAdmin();
        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.']);

        $this->as()->get(route('platform.admins', ['tab' => 'removed']))->assertOk()
            ->assertSee($user->email)->assertSee('Left the team in October.');
        $this->as()->get(route('platform.admins'))->assertDontSee($user->email);
    }

    public function test_an_unknown_tab_falls_back_to_active(): void
    {
        $this->as()->get(route('platform.admins', ['tab' => 'nonsense']))->assertOk()->assertSee('admin@mykaunsel.com');
    }
}
