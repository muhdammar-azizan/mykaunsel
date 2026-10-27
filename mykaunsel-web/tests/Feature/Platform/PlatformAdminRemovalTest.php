<?php

namespace Tests\Feature\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PlatformAdminRemoved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Removing a Platform Admin ends every way they could still get in, and tells them (briefly).
 */
class PlatformAdminRemovalTest extends CounselorTestCase
{
    private const REASON = 'Left the team in October.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function newAdmin(string $name = 'Nur Aisyah', string $email = 'aisyah@mykaunsel.com'): User
    {
        Notification::fake();
        $this->as()->post(route('platform.admins.store'), ['name' => $name, 'email' => $email])->assertSessionHasNoErrors();
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['password' => 'Str0ng!Passw0rd'])->save();

        return $user;
    }

    private function remove(User $user, ?User $by = null)
    {
        return $this->as($by)->post(route('platform.admins.remove', $user), ['reason' => self::REASON]);
    }

    private function useDatabaseSessions(): void
    {
        config(['session.driver' => 'database']);
    }

    private function sessionRow(string $id, ?int $userId): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => time()]);
    }

    private function adminStatus(User $user): MembershipStatus
    {
        return Membership::where('user_id', $user->id)->where('role', MembershipRole::PlatformAdmin->value)->firstOrFail()->status;
    }

    // ---- signed out everywhere -------------------------------------------------------------------------------

    public function test_every_database_session_of_the_removed_admin_is_deleted_and_nobody_elses(): void
    {
        $user = $this->newAdmin();
        $other = User::factory()->create();
        $this->useDatabaseSessions();
        $this->sessionRow('laptop', $user->id);
        $this->sessionRow('phone', $user->id);
        $this->sessionRow('other-person', $other->id);
        $this->sessionRow('visitor', null);

        $this->remove($user)->assertSessionHas('status');

        $left = DB::table('sessions')->pluck('id')->all();
        $this->assertNotContains('laptop', $left);
        $this->assertNotContains('phone', $left);
        $this->assertContains('other-person', $left);
        $this->assertContains('visitor', $left);
    }

    public function test_sessions_are_left_alone_when_they_are_not_kept_in_the_database(): void
    {
        $user = $this->newAdmin();
        $this->useDatabaseSessions();
        $this->sessionRow('laptop', $user->id);
        config(['session.driver' => 'array']);

        $this->remove($user)->assertSessionHas('status');

        $this->assertSame(1, DB::table('sessions')->where('id', 'laptop')->count(), 'the second line of defence (the middleware) is what ends those');
    }

    public function test_the_remember_me_token_is_replaced_so_old_cookies_stop_working(): void
    {
        $user = $this->newAdmin();
        $user->forceFill(['remember_token' => 'the-token-in-an-old-cookie'])->save();

        $this->remove($user);

        $new = $user->fresh()->remember_token;
        $this->assertNotSame('the-token-in-an-old-cookie', $new);
        $this->assertSame(60, strlen($new));
    }

    public function test_api_tokens_are_revoked_for_the_removed_admin_only(): void
    {
        $user = $this->newAdmin();
        $other = User::factory()->create();
        $user->createToken('phone');
        $user->createToken('tablet');
        $other->createToken('phone');

        $this->remove($user);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_the_person_and_their_other_access_stay_but_the_platform_admin_role_ends(): void
    {
        $user = $this->newAdmin();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $other = Membership::create(['user_id' => $user->id, 'org_id' => $umpsa->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->remove($user);

        $this->assertNotNull(User::find($user->id));
        $this->assertSame(MembershipStatus::Active, $other->fresh()->status);
        $this->assertSame(MembershipStatus::Offboarded, $this->adminStatus($user));
    }

    // ---- the email -------------------------------------------------------------------------------------------

    public function test_the_removed_person_gets_one_short_email_without_the_reason(): void
    {
        $user = $this->newAdmin();
        Notification::fake();

        $this->remove($user);

        Notification::assertSentToTimes($user, PlatformAdminRemoved::class, 1);
        Notification::assertCount(1);

        $mail = (new PlatformAdminRemoved)->toMail($user)->render()->toHtml();
        $this->assertStringContainsString('Your Platform Admin access on MyKaunsel has ended', $mail);
        $this->assertStringNotContainsString('October', $mail, 'the reason is not in the email');
        $this->assertStringNotContainsString($this->admin->name, $mail, 'neither is who removed them');
    }

    public function test_the_email_is_queued_and_waits_for_the_commit(): void
    {
        $notification = new PlatformAdminRemoved;

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
    }

    public function test_no_email_when_nothing_was_removed(): void
    {
        $user = $this->newAdmin();
        $this->remove($user);
        Notification::fake();

        $this->remove($user);                              // already removed
        $this->remove($this->admin, $this->admin);         // yourself

        Notification::assertNothingSent();
    }

    public function test_the_last_active_admin_gets_no_email_and_loses_nothing(): void
    {
        $other = User::factory()->create();
        $this->admin->createToken('phone');
        $other->createToken('phone');
        $this->useDatabaseSessions();
        $this->sessionRow('mine', $this->admin->id);
        Notification::fake();

        // The only admin removing themselves is refused: nothing is signed out and no email goes.
        $this->remove($this->admin, $this->admin);

        $this->assertSame(MembershipStatus::Active, $this->adminStatus($this->admin));
        $this->assertSame(1, $this->admin->tokens()->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'mine')->count());
        Notification::assertNothingSent();
    }

    // ---- all or nothing --------------------------------------------------------------------------------------

    public function test_if_signing_them_out_fails_the_removal_and_its_audit_entry_are_undone(): void
    {
        $user = $this->newAdmin();
        Notification::fake();
        $this->useDatabaseSessions();
        config(['session.table' => 'sessions_that_do_not_exist']);

        $this->withoutExceptionHandling();
        try {
            $this->remove($user);
            $this->fail('expected the sign-out to fail');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(MembershipStatus::Active, $this->adminStatus($user));
        $this->assertSame(0, AuditLog::where('action', 'admin.removed')->count());
        Notification::assertNothingSent();
    }

    // ---- the audit log ---------------------------------------------------------------------------------------

    public function test_the_removal_is_audited_with_the_name_and_the_older_entries_they_wrote_do_not_change(): void
    {
        $user = $this->newAdmin();
        $target = User::factory()->create();
        Audit::log('admin.invited', $target, ['summary' => 'Written while they were an admin'], $user);
        $before = AuditLog::where('actor_user_id', $user->id)->get()->map->getAttributes()->all();
        $this->assertSame('Nur Aisyah', $before[0]['actor_name']);
        $this->assertSame('platform_admin', $before[0]['actor_role']);

        $this->remove($user);

        $this->assertSame($before, AuditLog::where('actor_user_id', $user->id)->get()->map->getAttributes()->all(), 'their old entries are exactly as they were');

        $entry = AuditLog::where('action', 'admin.removed')->sole();
        $this->assertSame([$user->id, 'Nur Aisyah', self::REASON, true], [$entry->target_id, $entry->target_name, $entry->metadata['reason'], $entry->metadata['signed_out']]);
        $this->assertSame([$this->admin->id, $this->admin->name, 'platform_admin'], [$entry->actor_user_id, $entry->actor_name, $entry->actor_role]);
    }

    // ---- the second line of defence --------------------------------------------------------------------------

    public function test_a_removed_admin_who_still_has_a_session_is_signed_out_on_the_next_request(): void
    {
        $user = $this->newAdmin();
        $this->remove($user);

        $this->flushSession();
        $this->actingAs($user)->withSession($this->session)->get(route('platform.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'You have been signed out because your Platform Admin access has ended.');
        $this->assertGuest();
    }

    public function test_every_page_of_the_area_does_the_same_not_only_the_dashboard(): void
    {
        $user = $this->newAdmin();
        $this->remove($user);

        foreach (['platform.admins', 'platform.complaints', 'platform.counselors', 'platform.audit-log'] as $route) {
            $this->flushSession();
            $this->actingAs($user)->withSession($this->session)->get(route($route))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_a_suspended_platform_membership_is_signed_out_too(): void
    {
        $user = $this->newAdmin();
        Membership::where('user_id', $user->id)->where('role', MembershipRole::PlatformAdmin->value)->update(['status' => MembershipStatus::Suspended->value]);

        $this->flushSession();
        $this->actingAs($user)->withSession($this->session)->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_other_roles_keep_the_blocked_page(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        Membership::where('user_id', $orgAdmin->id)->where('org_id', $umpsa->id)->update(['status' => MembershipStatus::Suspended->value]);

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'])->get(route('org.dashboard'))
            ->assertRedirect(route('membership.blocked'));
    }

    public function test_choosing_the_platform_role_again_gets_a_removed_admin_nowhere(): void
    {
        $user = $this->newAdmin();
        $this->remove($user);
        $platform = Organization::where('org_type', OrgType::Platform->value)->firstOrFail();

        $this->flushSession();
        $this->actingAs($user)->post(route('context.store'), ['org_id' => $platform->id, 'role' => 'platform_admin'])->assertRedirect(route('dashboard'));
        $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signing_in_again_after_being_removed_leads_back_to_the_sign_in_page(): void
    {
        $user = $this->newAdmin();
        $this->remove($user);
        $this->flushSession();
        $this->app['auth']->forgetGuards(); // the admin who removed them is no longer the signed-in user of this test

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Str0ng!Passw0rd']);
        $this->get(route('dashboard'))->assertRedirect();
        $this->followingRedirects()->get(route('dashboard'))->assertSee('You have been signed out because your Platform Admin access has ended.');
        $this->assertGuest();
    }

    // ---- two admins at once ----------------------------------------------------------------------------------

    public function test_two_admins_removing_each_other_only_one_succeeds_and_one_admin_remains(): void
    {
        $second = $this->newAdmin();

        $this->remove($second)->assertSessionHas('status');
        // The second one still has a session in this test; their attempt on the first admin goes nowhere.
        $this->flushSession();
        $this->remove($this->admin, $second)->assertRedirect(route('login'));

        $this->assertSame(MembershipStatus::Active, $this->adminStatus($this->admin));
        $this->assertSame(MembershipStatus::Offboarded, $this->adminStatus($second));
        $this->assertSame(1, AuditLog::where('action', 'admin.removed')->count());
        $this->assertSame(1, Membership::where('role', MembershipRole::PlatformAdmin->value)->where('status', MembershipStatus::Active->value)->count());
    }

    public function test_removing_the_same_person_twice_signs_out_and_audits_once(): void
    {
        $user = $this->newAdmin();
        Notification::fake();

        $this->remove($user)->assertSessionHas('status');
        $this->remove($user)->assertSessionHas('error');

        $this->assertSame(1, AuditLog::where('action', 'admin.removed')->count());
        Notification::assertSentToTimes($user, PlatformAdminRemoved::class, 1);
    }

    public function test_removing_someone_never_touches_their_password(): void
    {
        $user = $this->newAdmin();
        $before = $user->password;

        $this->remove($user);

        $this->assertSame($before, $user->fresh()->password, 'removing someone never touches their password');
        $this->assertTrue(Hash::check('Str0ng!Passw0rd', $user->fresh()->password));
    }
}
