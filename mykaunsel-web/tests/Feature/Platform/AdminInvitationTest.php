<?php

namespace Tests\Feature\Platform;

use App\Enums\AdminInvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AdminInvitation;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\PlatformAdminInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Invitations to become a Platform Admin: sending, the link, the three ways to accept, and everything that must not work.
 */
class AdminInvitationTest extends CounselorTestCase
{
    use AdminInvitationHelpers;

    private const EMAIL = 'aisyah@mykaunsel.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        RateLimiter::clear('admin-invite:'.$this->admin->id);
    }

    private function token(string $url): string
    {
        preg_match('#/admin-invitation/([a-f0-9]{64})#', $url, $m);

        return $m[1];
    }

    private function adminMembership(User $user): ?Membership
    {
        return Membership::where('user_id', $user->id)->where('role', MembershipRole::PlatformAdmin->value)->first();
    }

    private function accountWith(string $password, bool $verified = true): User
    {
        $user = User::factory()->create(['email' => self::EMAIL, 'name' => 'Existing Aisyah', 'password' => $password]);
        $user->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $user;
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    // ---- sending ---------------------------------------------------------------------------------------------

    public function test_the_token_is_random_stored_only_as_a_hash_and_the_link_has_the_same_time_limit(): void
    {
        [$invitation, $url] = $this->invite();
        $token = $this->token($url);

        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame(64, strlen($token));
        $this->assertSame(0, DB::table('admin_invitations')->where('token_hash', $token)->count());
        $this->assertStringNotContainsString($token, json_encode(DB::table('admin_invitations')->get()));

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($invitation->expires_at->timestamp, (int) $query['expires']);
        $this->assertEqualsWithDelta(48 * 3600, $invitation->expires_at->timestamp - now()->timestamp, 5, 'two days by default');

        [, $other] = $this->invite('Someone Else', 'else@mykaunsel.com');
        $this->assertNotSame($token, $this->token($other));
    }

    public function test_the_time_limit_comes_from_the_configuration(): void
    {
        config(['platform-admins.invitation.expires_hours' => 6]);

        [$invitation] = $this->invite();

        $this->assertEqualsWithDelta(6 * 3600, $invitation->expires_at->timestamp - now()->timestamp, 5);
    }

    public function test_the_email_does_not_say_whether_the_address_has_an_account_and_is_queued_after_the_commit(): void
    {
        $this->accountWith('Existing!Passw0rd1');
        [$invitation, $url] = $this->invite('Whoever', self::EMAIL);

        Notification::assertSentOnDemand(PlatformAdminInvitation::class, function ($n, $channels, $notifiable) {
            $text = strtolower(implode(' ', $n->toMail($notifiable)->introLines).' '.implode(' ', $n->toMail($notifiable)->outroLines));

            return ! str_contains($text, 'account') && str_contains($text, 'hours') && str_contains($text, 'nothing changes');
        });

        $notification = new PlatformAdminInvitation('A', $url, $invitation->expires_at);
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
    }

    public function test_only_one_live_invitation_per_email_even_with_different_capital_letters(): void
    {
        $this->invite('Nur Aisyah', self::EMAIL);
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => 'Again', 'email' => 'AISYAH@MyKaunsel.com'])->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame(1, AdminInvitation::count());
        $this->assertCount(1, $this->logs('admin.invited'));
    }

    public function test_the_database_itself_refuses_two_waiting_invitations_for_one_email(): void
    {
        $this->invite();
        $row = AdminInvitation::first()->only(['name', 'email', 'active_email', 'status', 'invited_by_user_id', 'expires_at', 'last_sent_at']);

        $this->expectException(UniqueConstraintViolationException::class);
        AdminInvitation::create($row + ['token_hash' => str_repeat('b', 64)]);
    }

    public function test_a_waiting_invitation_that_ran_out_makes_way_for_a_new_one(): void
    {
        [$old] = $this->invite();
        Carbon::setTestNow(now()->addHours(49));

        $this->invite('Nur Aisyah', self::EMAIL);

        $this->assertSame(AdminInvitationStatus::Expired, $old->fresh()->status);
        $this->assertNull($old->fresh()->active_email);
        $this->assertSame(1, AdminInvitation::live()->count());
        $this->assertSame(2, AdminInvitation::count());
    }

    public function test_an_active_admin_cannot_be_invited_and_a_failed_invite_sends_nothing(): void
    {
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => 'X', 'email' => 'admin@mykaunsel.com'])->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame(0, AdminInvitation::count());
    }

    public function test_one_admin_can_only_send_so_many_new_invitations_an_hour(): void
    {
        config(['platform-admins.invitation.new_per_admin_per_hour' => 2]);
        RateLimiter::clear('admin-invite:'.$this->admin->id);

        $this->invite('One', 'one@mykaunsel.com');
        $this->invite('Two', 'two@mykaunsel.com');
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => 'Three', 'email' => 'three@mykaunsel.com'])->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame(2, AdminInvitation::count());
    }

    // ---- send again / revoke ---------------------------------------------------------------------------------

    public function test_sending_again_replaces_the_link_and_the_old_one_stops_working(): void
    {
        [$invitation, $old] = $this->invite();
        Carbon::setTestNow(now()->addMinutes(5));
        $other = User::factory()->create(['email_verified_at' => now()]);
        Membership::create(['user_id' => $other->id, 'org_id' => $this->session['current_org_id'], 'role' => 'platform_admin', 'status' => 'active', 'joined_at' => now()]);

        Notification::fake();
        $this->flushSession();
        $this->as($other)->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('status');
        $new = $this->emailedLink();

        $this->assertNotSame($this->token($old), $this->token($new));
        $fresh = $invitation->fresh();
        $this->assertSame([2, $other->id], [$fresh->sent_count, $fresh->invited_by_user_id]);
        $this->assertSame(1, AdminInvitation::count(), 'the same invitation, not a second one');
        $this->assertSame(hash('sha256', $this->token($new)), $fresh->token_hash);
        $this->assertEqualsWithDelta(48 * 3600, $fresh->expires_at->timestamp - now()->timestamp, 5);

        $this->asGuest()->get($old)->assertStatus(410);
        $this->asGuest()->post($old, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertStatus(410);
        $this->assertNull(User::where('email', self::EMAIL)->first());
        $this->asGuest()->get($new)->assertOk();

        $log = $this->logs('admin.invite_resent')->sole();
        $this->assertSame([$invitation->id, $other->id, 2], [(int) $log->target_id, (int) $log->actor_user_id, $log->metadata['send_number']]);
    }

    public function test_sending_again_has_a_cooldown_and_a_limit(): void
    {
        [$invitation] = $this->invite();

        $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('error');
        $this->assertSame(1, $invitation->fresh()->sent_count, 'too soon after the first send');

        for ($i = 2; $i <= 5; $i++) {
            Carbon::setTestNow(now()->addMinutes(2));
            $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('status');
            $this->assertSame($i, $invitation->fresh()->sent_count);
        }

        Carbon::setTestNow(now()->addMinutes(2));
        Notification::fake();
        $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('error');

        $this->assertSame(5, $invitation->fresh()->sent_count);
        Notification::assertNothingSent();
        $this->assertCount(4, $this->logs('admin.invite_resent'));
    }

    public function test_sending_again_renews_an_invitation_that_ran_out(): void
    {
        [$invitation, $old] = $this->invite();
        Carbon::setTestNow(now()->addHours(50));
        $this->asGuest()->get($old)->assertStatus(410);

        Notification::fake();
        $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('status');

        $this->asGuest()->get($this->emailedLink())->assertOk();
        $this->assertTrue($invitation->fresh()->isLive());
    }

    public function test_revoking_kills_the_link_at_once_and_frees_the_email(): void
    {
        [$invitation, $url] = $this->invite();

        $this->as()->post(route('platform.admins.invitations.revoke', $invitation))->assertSessionHas('status');

        $fresh = $invitation->fresh();
        $this->assertSame(AdminInvitationStatus::Revoked, $fresh->status);
        $this->assertNull($fresh->active_email);
        $this->assertNotNull($fresh->revoked_at);
        $this->asGuest()->get($url)->assertStatus(410);
        $this->asGuest()->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertStatus(410);
        $this->assertNull(User::where('email', self::EMAIL)->first());

        $log = $this->logs('admin.invite_revoked')->sole();
        $this->assertSame([$invitation->id, $this->admin->id], [(int) $log->target_id, (int) $log->actor_user_id]);

        // The same email can be invited again.
        $this->invite('Nur Aisyah', self::EMAIL);
        $this->assertSame(2, AdminInvitation::count());
    }

    public function test_only_a_waiting_invitation_can_be_revoked_or_sent_again(): void
    {
        [$revoked] = $this->invite('A One', 'a@mykaunsel.com');
        $this->as()->post(route('platform.admins.invitations.revoke', $revoked));
        [$accepted, $url] = $this->invite('B Two', 'b@mykaunsel.com');
        $this->acceptWithNewPassword($url)->assertRedirect(route('login'));
        Notification::fake();
        Carbon::setTestNow(now()->addMinutes(5));

        foreach ([$revoked, $accepted] as $invitation) {
            $this->as()->post(route('platform.admins.invitations.revoke', $invitation))->assertSessionHas('error');
            $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('error');
        }

        Notification::assertNothingSent();
        $this->assertCount(1, $this->logs('admin.invite_revoked'));
        $this->assertCount(0, $this->logs('admin.invite_resent'));
    }

    // ---- the page behind the link ----------------------------------------------------------------------------

    public function test_the_link_shows_who_and_what_and_is_never_cached_or_indexed(): void
    {
        [, $url] = $this->invite();

        $response = $this->asGuest()->get($url)->assertOk()
            ->assertSee('Nur Aisyah')->assertSee(self::EMAIL)->assertSee($this->admin->name)
            ->assertSee('Choose a password to create your account');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $response->headers->get('X-Robots-Tag'));
    }

    public function test_every_way_a_link_can_be_wrong_gets_the_same_answer(): void
    {
        [$invitation, $url] = $this->invite();
        $token = $this->token($url);
        $answers = [];

        // a changed signature
        $answers['signature'] = $this->asGuest()->get($url.'0');
        // a made-up token with a correct-looking signature for it
        $answers['unknown'] = $this->asGuest()->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('admin-invitation.show', now()->addDay(), ['token' => str_repeat('c', 64)]));
        // no signature at all
        $answers['unsigned'] = $this->asGuest()->get(route('admin-invitation.show', ['token' => $token]));
        // a link whose own time limit has passed
        Carbon::setTestNow(now()->addHours(49));
        $answers['expired'] = $this->asGuest()->get($url);
        Carbon::setTestNow();
        // a revoked one
        $invitation->update(['status' => 'revoked']);
        $answers['revoked'] = $this->asGuest()->get($url);

        $bodies = [];
        foreach ($answers as $why => $response) {
            $response->assertStatus(410);
            $bodies[$why] = $response->getContent();
        }

        $this->assertCount(1, array_unique(array_map(fn ($b) => preg_replace('/csrf-token" content="[^"]+"/', '', $b), $bodies)), 'the page does not tell which one it was');
        $this->assertStringContainsString('no longer valid', reset($bodies));
    }

    public function test_the_token_in_the_link_must_be_64_hex_characters(): void
    {
        $this->asGuest()->get('/admin-invitation/short?signature=x')->assertNotFound();
        $this->asGuest()->post('/admin-invitation/'.str_repeat('Z', 64))->assertNotFound();
    }

    // ---- accepting: no account yet ---------------------------------------------------------------------------

    public function test_a_new_person_chooses_a_password_and_the_account_and_role_are_created_then(): void
    {
        [$invitation, $url] = $this->invite('Nur Aisyah', 'Aisyah@MyKaunsel.com');

        $this->acceptWithNewPassword($url)->assertRedirect(route('login'))->assertSessionHas('status');

        $user = User::where('email', self::EMAIL)->sole();
        $this->assertSame('Nur Aisyah', $user->name);
        $this->assertNotNull($user->email_verified_at, 'opening the link from the mailbox verifies it');
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertSame(MembershipStatus::Active, $this->adminMembership($user)->status);

        $fresh = $invitation->fresh();
        $this->assertSame([AdminInvitationStatus::Accepted, $user->id, null], [$fresh->status, $fresh->accepted_user_id, $fresh->active_email]);
        $this->assertNotNull($fresh->accepted_at);

        $log = $this->logs('admin.invite_accepted')->sole();
        $this->assertSame(['user', $user->id, $user->id, 'Nur Aisyah', 'platform_admin'], [$log->actor_type, (int) $log->actor_user_id, (int) $log->target_id, $log->target_name, $log->actor_role]);
        $this->assertSame(['invitation_id' => $invitation->id, 'how' => 'new_account', 'summary' => 'Invitation accepted'], $log->metadata);

        $this->asGuest()->post(route('login.store'), ['email' => self::EMAIL, 'password' => self::NEW_PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_link_works_only_once(): void
    {
        [, $url] = $this->invite();
        $this->acceptWithNewPassword($url)->assertRedirect(route('login'));

        $this->asGuest()->get($url)->assertStatus(410);
        $this->asGuest()->post($url, ['password' => 'An0ther!Passw0rd', 'password_confirmation' => 'An0ther!Passw0rd'])->assertStatus(410);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, User::where('email', self::EMAIL)->sole()->password), 'the second try changed nothing');
        $this->assertCount(1, $this->logs('admin.invite_accepted'));
    }

    public function test_a_weak_or_unconfirmed_password_changes_nothing_and_the_link_keeps_working(): void
    {
        [$invitation, $url] = $this->invite();

        foreach ([['password' => 'weak', 'password_confirmation' => 'weak'], ['password' => self::NEW_PASSWORD, 'password_confirmation' => 'Different!Passw0rd'], ['password' => '']] as $input) {
            $this->asGuest()->post($url, $input)->assertSessionHasErrors('password');
        }

        $this->assertNull(User::where('email', self::EMAIL)->first());
        $this->assertTrue($invitation->fresh()->isLive());
        $this->acceptWithNewPassword($url)->assertRedirect(route('login'));
    }

    public function test_the_email_and_name_cannot_be_changed_when_accepting(): void
    {
        [, $url] = $this->invite();

        $this->asGuest()->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD, 'email' => 'someone.else@example.com', 'name' => 'Someone Else'])->assertRedirect(route('login'));

        $this->assertNull(User::where('email', 'someone.else@example.com')->first());
        $this->assertSame('Nur Aisyah', User::where('email', self::EMAIL)->sole()->name);
    }

    // ---- accepting: an account with a verified email ---------------------------------------------------------

    public function test_an_account_with_a_verified_email_confirms_with_its_own_password_and_keeps_everything(): void
    {
        $user = $this->accountWith('Existing!Passw0rd1');
        $user->createToken('phone');
        $hash = $user->password;
        [$invitation, $url] = $this->invite('Ignored', self::EMAIL);

        $this->asGuest()->get($url)->assertOk()->assertSee('Type its password to confirm');
        $this->asGuest()->post($url, ['password' => 'Existing!Passw0rd1'])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertSame([$hash, 'Existing Aisyah'], [$user->password, $user->name]);
        $this->assertSame(1, $user->tokens()->count(), 'nobody is signed out');
        $this->assertSame(MembershipStatus::Active, $this->adminMembership($user)->status);
        $this->assertSame('existing_account', $this->logs('admin.invite_accepted')->sole()->metadata['how']);
        $this->assertSame($user->id, $invitation->fresh()->accepted_user_id);
        $this->assertSame(1, User::where('email', self::EMAIL)->count());
    }

    public function test_a_wrong_password_is_refused_counted_and_after_five_the_invitation_is_locked_for_a_while(): void
    {
        $user = $this->accountWith('Existing!Passw0rd1');
        [$invitation, $url] = $this->invite('Ignored', self::EMAIL);

        for ($i = 0; $i < 5; $i++) {
            $this->asGuest()->post($url, ['password' => 'Wrong!Passw0rd'.$i])->assertSessionHasErrors(['password' => 'That password is not correct.']);
        }

        // Locked: even the right password is not tried.
        $this->asGuest()->post($url, ['password' => 'Existing!Passw0rd1'])->assertSessionHasErrors(['password' => 'Too many wrong passwords. Try again in a few minutes.']);
        $this->assertNull($this->adminMembership($user));
        $this->assertTrue($invitation->fresh()->isLive(), 'locked, not cancelled');
        $this->asGuest()->get($url)->assertOk()->assertSee('Too many wrong passwords');

        Carbon::setTestNow(now()->addMinutes(16));
        $this->asGuest()->post($url, ['password' => 'Existing!Passw0rd1'])->assertRedirect(route('login'));
        $this->assertNotNull($this->adminMembership($user));
    }

    public function test_an_account_with_two_factor_must_be_signed_in_first(): void
    {
        $user = $this->accountWith('Existing!Passw0rd1');
        $user->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();
        [, $url] = $this->invite('Ignored', self::EMAIL);

        // A visitor who only knows the password and has the link is not enough.
        $this->asGuest()->get($url)->assertOk()->assertSee('uses two-factor authentication')->assertDontSee('Accept and become');
        $this->asGuest()->post($url, ['password' => 'Existing!Passw0rd1'])->assertSessionHasErrors('password');
        $this->assertNull($this->adminMembership($user));

        // Signed in as that account (which is where the second factor was asked), the same password finishes it.
        $this->flushSession();
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Accept and become');
        $this->actingAs($user)->post($url, ['password' => 'Existing!Passw0rd1'])->assertRedirect(route('login'));
        $this->assertNotNull($this->adminMembership($user));
    }

    // ---- accepting: an account whose email was never verified ------------------------------------------------

    public function test_an_unverified_account_must_choose_a_new_password_and_loses_every_old_way_in(): void
    {
        $user = $this->accountWith('Old!Passw0rd-of-someone', verified: false);
        $user->forceFill(['remember_token' => 'old-cookie-token'])->save();
        $user->createToken('their-phone');
        config(['session.driver' => 'database']);
        DB::table('sessions')->insert(['id' => 'their-laptop', 'user_id' => $user->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
        DB::table('password_reset_tokens')->insert(['email' => self::EMAIL, 'token' => 'old-reset', 'created_at' => now()]);
        [, $url] = $this->invite('Ignored', self::EMAIL);

        $this->asGuest()->get($url)->assertOk()->assertSee('never verified')->assertSee('NEW password');

        // The old password is not a way in: a new one (with its confirmation) is required.
        $this->asGuest()->post($url, ['password' => 'weak'])->assertSessionHasErrors('password');
        $this->assertNull($this->adminMembership($user));

        $this->acceptWithNewPassword($url, 'Brand-New!Passw0rd9')->assertRedirect(route('login'));

        $user->refresh();
        $this->assertFalse(Hash::check('Old!Passw0rd-of-someone', $user->password));
        $this->assertTrue(Hash::check('Brand-New!Passw0rd9', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame('old-cookie-token', $user->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', self::EMAIL)->count());
        $this->assertNotNull($this->adminMembership($user));
        $this->assertSame('unverified_account_new_password', $this->logs('admin.invite_accepted')->sole()->metadata['how']);
        $this->assertSame('Existing Aisyah', $user->name, 'their name is not overwritten');
    }

    // ---- who can still use a link ----------------------------------------------------------------------------

    public function test_removing_the_admin_who_sent_it_revokes_their_waiting_invitations_and_only_theirs(): void
    {
        $sender = $this->addAdmin('Sender One', 'sender@mykaunsel.com');
        [$mine, $myUrl] = $this->inviteAs($sender, 'Guest One', 'guest1@mykaunsel.com');
        [$ours, $ourUrl] = $this->invite('Guest Two', 'guest2@mykaunsel.com');   // sent by the first admin

        $this->as()->post(route('platform.admins.remove', $sender), ['reason' => 'Left the team in October.'])->assertSessionHas('status');

        $this->assertSame(AdminInvitationStatus::Revoked, $mine->fresh()->status);
        $this->assertNull($mine->fresh()->active_email);
        $this->asGuest()->get($myUrl)->assertStatus(410);
        $this->assertSame(AdminInvitationStatus::Pending, $ours->fresh()->status, 'someone else\'s invitation is untouched');
        $this->asGuest()->get($ourUrl)->assertOk();

        $log = $this->logs('admin.invite_revoked')->sole();
        $this->assertSame(['sender_removed', $mine->id, $this->admin->id], [$log->metadata['cause'], (int) $log->target_id, (int) $log->actor_user_id]);
    }

    public function test_an_invitation_sent_again_by_another_admin_belongs_to_that_admin_now(): void
    {
        $first = $this->addAdmin('Sender One', 'sender@mykaunsel.com');
        [$invitation] = $this->inviteAs($first, 'Guest One', 'guest1@mykaunsel.com');
        Carbon::setTestNow(now()->addMinutes(5));
        Notification::fake();
        $this->as()->post(route('platform.admins.invitations.resend', $invitation))->assertSessionHas('status');
        $url = $this->emailedLink();

        $this->as()->post(route('platform.admins.remove', $first), ['reason' => 'Left the team in October.']);

        $this->assertSame(AdminInvitationStatus::Pending, $invitation->fresh()->status);
        $this->asGuest()->get($url)->assertOk();
    }

    public function test_a_link_stops_working_if_its_sender_loses_access_even_without_the_revoking_step(): void
    {
        [$invitation, $url] = $this->invite();
        // A sender whose access ended by some other route (a direct change in the database).
        Membership::where('user_id', $this->admin->id)->update(['status' => 'suspended']);

        $this->asGuest()->get($url)->assertStatus(410);
        $this->asGuest()->post($url, ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertStatus(410);
        $this->assertNull(User::where('email', self::EMAIL)->first());
        $this->assertTrue($invitation->fresh()->isLive());
    }

    public function test_someone_who_became_an_admin_in_the_meantime_cannot_use_the_link_again(): void
    {
        $user = $this->accountWith('Existing!Passw0rd1');
        [, $url] = $this->invite('Ignored', self::EMAIL);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => 'platform_admin', 'status' => 'active', 'joined_at' => now()]);

        $this->asGuest()->post($url, ['password' => 'Existing!Passw0rd1'])->assertStatus(410);

        $this->assertSame(1, Membership::where('user_id', $user->id)->count());
        $this->assertCount(0, $this->logs('admin.invite_accepted'));
    }

    // ---- the list --------------------------------------------------------------------------------------------

    public function test_the_invitations_tab_shows_each_state_with_its_actions(): void
    {
        [$waiting] = $this->invite('Waiting Person', 'waiting@mykaunsel.com');
        [, $acceptUrl] = $this->invite('Accepted Person', 'accepted@mykaunsel.com');
        $this->acceptWithNewPassword($acceptUrl);
        [$revoked] = $this->invite('Revoked Person', 'revoked@mykaunsel.com');
        $this->as()->post(route('platform.admins.invitations.revoke', $revoked));
        [$expired] = $this->invite('Expired Person', 'expired@mykaunsel.com');
        $expired->update(['expires_at' => now()->subHour()]);

        $page = $this->as()->get(route('platform.admins', ['tab' => 'invitations']))->assertOk();

        $page->assertSeeInOrder(['Expired Person', 'Expired'])->assertSeeInOrder(['Revoked Person', 'Revoked'])
            ->assertSeeInOrder(['Accepted Person', 'Accepted'])->assertSeeInOrder(['Waiting Person', 'Pending']);
        $page->assertSee(route('platform.admins.invitations.resend', $waiting), false)->assertSee(route('platform.admins.invitations.revoke', $waiting), false);
        $page->assertDontSee(route('platform.admins.invitations.revoke', $revoked), false);

        $this->assertSame(AdminInvitationStatus::Expired, $expired->fresh()->displayStatus());
        $this->assertSame(1, AdminInvitation::live()->count());

        $this->as()->get(route('platform.admins'))->assertOk()->assertSee('Invitations')->assertDontSee('waiting@mykaunsel.com');
    }

    public function test_the_sample_invitations_cover_every_state_and_none_of_their_links_can_be_opened(): void
    {
        $this->seed(\Database\Seeders\AdminInvitationSeeder::class);

        $states = AdminInvitation::all()->map->displayStatus()->map->value->sort()->values()->all();
        $this->assertSame(['accepted', 'expired', 'pending', 'revoked'], $states);
        $this->assertSame(1, AdminInvitation::live()->count());
        $this->assertSame(1, AdminInvitation::whereNotNull('active_email')->where('email', 'sample.pending@example.com')->count());

        $this->as()->get(route('platform.admins', ['tab' => 'invitations']))->assertOk()->assertSee('Sample Pending')->assertSee('Sample Expired');

        // The seeder, like the demo admin seeder, refuses to run on a real server.
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(\Database\Seeders\AdminInvitationSeeder::class)->run();
    }

    // ---- nothing secret in the audit log ---------------------------------------------------------------------

    public function test_no_token_password_or_email_address_reaches_the_audit_log(): void
    {
        $user = $this->accountWith('Existing!Passw0rd1');
        [, $existingUrl] = $this->invite('Ignored', self::EMAIL);
        [$other, $otherUrl] = $this->invite('Other Person', 'other@mykaunsel.com');
        Carbon::setTestNow(now()->addMinutes(5));
        $this->as()->post(route('platform.admins.invitations.resend', $other));
        $this->as()->post(route('platform.admins.invitations.revoke', $other));
        $this->asGuest()->post($existingUrl, ['password' => 'Existing!Passw0rd1']);

        $everything = strtolower(json_encode(AuditLog::where('category', 'admins')->get()->map->getAttributes()->all()));

        foreach ([$this->token($existingUrl), $this->token($otherUrl), 'existing!passw0rd1', 'aisyah@', 'other@mykaunsel', 'signature='] as $secret) {
            $this->assertStringNotContainsString(strtolower($secret), $everything, $secret);
        }
        $this->assertNotEmpty($user);
    }

    // ---- helpers for a second sender -------------------------------------------------------------------------

    /** Invite as another admin (that admin must be able to sign in with the session of the platform organization). */
    private function inviteAs(User $sender, string $name, string $email): array
    {
        Notification::fake();

        $this->flushSession();
        $this->as($sender)->post(route('platform.admins.store'), ['name' => $name, 'email' => $email])->assertSessionHasNoErrors();
        $this->flushSession();

        return [AdminInvitation::where('email', $email)->firstOrFail(), $this->emailedLink()];
    }
}
