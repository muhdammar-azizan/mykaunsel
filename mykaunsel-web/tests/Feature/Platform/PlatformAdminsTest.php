<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformAdminInvited;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Counselor\CounselorTestCase;

class PlatformAdminsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function platformMembership(User $user): ?Membership
    {
        return Membership::where('user_id', $user->id)->where('org_id', $this->session['current_org_id'])->first();
    }

    private function addAdmin(string $name = 'Nur Aisyah', string $email = 'aisyah@mykaunsel.com'): User
    {
        $this->as()->post(route('platform.admins.store'), ['name' => $name, 'email' => $email])->assertSessionHasNoErrors();

        return User::where('email', strtolower($email))->firstOrFail();
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

        foreach ([
            fn ($t) => $t->get(route('platform.admins')),
            fn ($t) => $t->post(route('platform.admins.store'), ['name' => 'X Y', 'email' => 'x@y.com']),
            fn ($t) => $t->post(route('platform.admins.resend', $target)),
            fn ($t) => $t->post(route('platform.admins.remove', $target), ['reason' => 'Because I want to.']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->assertNull(User::where('email', 'x@y.com')->first());
    }

    public function test_the_page_lists_the_admins_and_is_in_the_menu(): void
    {
        $this->as()->get(route('platform.admins'))->assertOk()
            ->assertSee('Platform Administrator')->assertSee('admin@mykaunsel.com')->assertSee('You')
            ->assertSee('Not signed in yet');

        $this->as()->get(route('platform.dashboard'))->assertSee(route('platform.admins'), false);
    }

    public function test_adding_a_new_email_creates_the_account_audits_and_sends_a_set_password_link(): void
    {
        Notification::fake();

        $user = $this->addAdmin('Nur Aisyah', 'Aisyah@MyKaunsel.com');

        $this->assertSame('aisyah@mykaunsel.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(MembershipRole::PlatformAdmin, $this->platformMembership($user)->role);
        $this->assertSame(MembershipStatus::Active, $this->platformMembership($user)->status);

        Notification::assertSentTo($user, PlatformAdminInvited::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($mail->actionUrl, '/reset-password/') && ! str_contains(implode(' ', $mail->introLines), 'password:');
        });

        $log = $this->logs('admin.invited')->sole();
        $this->assertSame($user->id, (int) $log->target_id);
        $this->assertSame($this->admin->id, (int) $log->actor_user_id);
    }

    public function test_the_emailed_link_lets_the_new_admin_choose_a_password(): void
    {
        Notification::fake();
        $user = $this->addAdmin();

        $url = null;
        Notification::assertSentTo($user, PlatformAdminInvited::class, function ($n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        preg_match('#/reset-password/([^?]+)\?email=#', $url, $m);

        $this->assertTrue(Password::broker()->tokenExists($user, urldecode($m[1])));
        $this->flushSession();
        auth()->logout();
        $this->post('/reset-password', ['token' => urldecode($m[1]), 'email' => $user->email, 'password' => 'A-very-Strong-pass-123', 'password_confirmation' => 'A-very-Strong-pass-123'])
            ->assertRedirect(route('login'));
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
        $this->assertSame(1, Membership::where('role', 'platform_admin')->count());
        $this->assertCount(0, $this->logs('admin.invited'));
    }

    public function test_an_existing_account_keeps_its_password_and_gets_the_role(): void
    {
        Notification::fake();
        $counselor = $this->profile('KB10234')->user;
        $hash = $counselor->password;

        $this->as()->post(route('platform.admins.store'), ['name' => 'Ignored Name', 'email' => $counselor->email])->assertSessionHas('status');

        $this->assertSame($hash, $counselor->fresh()->password);
        $this->assertSame($counselor->name, $counselor->fresh()->name);
        $this->assertSame(MembershipStatus::Active, $this->platformMembership($counselor)->status);
        Notification::assertSentTo($counselor, PlatformAdminInvited::class, fn ($n) => $n->toMail($counselor)->actionUrl === route('login'));
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

        // Blocked on their next request.
        $this->flushSession();
        $this->actingAs($user)->withSession($this->session)->get(route('platform.dashboard'))->assertRedirect(route('membership.blocked'));
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

    public function test_a_removed_admin_can_be_added_again(): void
    {
        Notification::fake();
        $user = $this->addAdmin();
        $this->as()->post(route('platform.admins.remove', $user), ['reason' => 'Left the team in October.']);

        $this->as()->post(route('platform.admins.store'), ['name' => 'Nur Aisyah', 'email' => $user->email])->assertSessionHas('status');

        $membership = $this->platformMembership($user);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertNull($membership->offboarded_at);
        $this->assertSame(1, Membership::where('user_id', $user->id)->where('org_id', $this->session['current_org_id'])->count());
    }

    public function test_the_invite_can_be_sent_again_only_until_the_first_sign_in(): void
    {
        Notification::fake();
        $user = $this->addAdmin();

        $this->as()->post(route('platform.admins.resend', $user))->assertSessionHas('status');
        Notification::assertSentToTimes($user, PlatformAdminInvited::class, 2);
        $this->assertCount(2, $this->logs('admin.invited'));

        $user->forceFill(['last_login_at' => now()])->save();
        $this->as()->post(route('platform.admins.resend', $user))->assertSessionHas('error');
        Notification::assertSentToTimes($user, PlatformAdminInvited::class, 2);
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
