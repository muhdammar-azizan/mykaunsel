<?php

namespace Tests\Feature\Account;

use App\Facades\Audit;
use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityTabTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Secret#123';

    private const NEW = 'Brand#New9pass';

    private array $session = [];

    private function admin(string $name = 'Ammar Azizan'): User
    {
        $platform = Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], [
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create(['name' => $name, 'password' => self::OLD]);

        Membership::create([
            'user_id' => $admin->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];

        return $admin;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    private function fakeSession(User $user, string $id, string $agent, string $ip, ?int $lastActivity = null): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => $ip,
            'user_agent' => $agent,
            'payload' => 'x',
            'last_activity' => $lastActivity ?? now()->timestamp,
        ]);
    }

    public function test_security_page_renders_all_sections(): void
    {
        $admin = $this->admin();

        $this->as($admin)->get(route('platform.account.security'))
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('Active sessions')
            ->assertSee('Recent security activity')
            ->assertSee('At least 8 characters');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $admin = $this->admin();

        $this->as($admin)->put(route('platform.account.security.password'), [
            'current_password' => 'wrong', 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check(self::OLD, $admin->fresh()->password));
    }

    public function test_weak_unconfirmed_or_unchanged_passwords_are_rejected(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $admin = $this->admin();

        foreach ([
            ['password' => 'short1!', 'password_confirmation' => 'short1!'],
            ['password' => 'alllowercase1!', 'password_confirmation' => 'alllowercase1!'],
            ['password' => 'NoNumbers!!', 'password_confirmation' => 'NoNumbers!!'],
            ['password' => 'NoSpecial123', 'password_confirmation' => 'NoSpecial123'],
            ['password' => self::NEW, 'password_confirmation' => 'Different#9'],
            ['password' => self::OLD, 'password_confirmation' => self::OLD],
        ] as $payload) {
            $this->as($admin)->put(route('platform.account.security.password'), ['current_password' => self::OLD] + $payload)
                ->assertSessionHasErrorsIn('updatePassword', 'password');
        }

        $this->assertTrue(Hash::check(self::OLD, $admin->fresh()->password));
    }

    public function test_password_change_succeeds_logs_out_other_devices_only_and_is_audited(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = $this->admin('Second Admin');
        $this->fakeSession($admin, 'other-device-1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Mobile/15E148 Safari/604.1', '10.0.0.5');
        $this->fakeSession($other, 'someone-elses', 'Chrome/1 Windows', '10.9.9.9');

        $this->as($admin)->put(route('platform.account.security.password'), [
            'current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertRedirect(route('platform.account.security'))->assertSessionHas('status');

        $this->assertTrue(Hash::check(self::NEW, $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-1']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_changed', 'actor_user_id' => $admin->id]);
        $this->assertTrue(Hash::check(self::OLD, $other->fresh()->password), 'another admin must not be affected');
    }

    public function test_password_endpoint_is_rate_limited(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->as($admin)->put(route('platform.account.security.password'), ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'x']);
        }

        $this->as($admin)->put(route('platform.account.security.password'), ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'x'])
            ->assertStatus(429);
    }

    public function test_sessions_list_shows_only_own_sessions_with_readable_labels(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = $this->admin('Second Admin');
        $this->fakeSession($admin, 'mine-1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0.0.0 Safari/537.36', '203.0.113.20');
        $this->fakeSession($other, 'theirs-1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Version/17.5 Safari/605.1.15', '198.51.100.77');

        $this->as($admin)->get(route('platform.account.security'))
            ->assertOk()
            ->assertSee('Chrome on Windows')
            ->assertSee('203.0.113.20')
            ->assertDontSee('198.51.100.77')
            ->assertDontSee('Safari on macOS');
    }

    public function test_revoking_other_sessions_needs_password_and_keeps_other_users_intact(): void
    {
        config(['session.driver' => 'database']);
        $admin = $this->admin();
        $other = $this->admin('Second Admin');
        $this->fakeSession($admin, 'laptop', 'Chrome/1 Windows', '10.0.0.1');
        $this->fakeSession($admin, 'phone', 'iPhone Mobile', '10.0.0.2');
        $this->fakeSession($other, 'theirs', 'Chrome/1 Windows', '10.0.0.3');

        $this->as($admin)->post(route('platform.account.security.sessions.revoke'), ['current_password' => 'nope'])
            ->assertSessionHasErrorsIn('revokeSessions', 'current_password');
        $this->assertDatabaseHas('sessions', ['id' => 'laptop']);

        $this->as($admin)->post(route('platform.account.security.sessions.revoke'), ['current_password' => self::OLD])
            ->assertRedirect(route('platform.account.security'));

        $this->assertDatabaseMissing('sessions', ['id' => 'laptop']);
        $this->assertDatabaseMissing('sessions', ['id' => 'phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'theirs']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.other_sessions_revoked', 'actor_user_id' => $admin->id]);
    }

    public function test_recent_activity_lists_only_this_admins_security_events(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Second Admin');

        Audit::log('account.password_changed', $admin, [], $admin);
        Audit::log('auth.login_failed', $admin, [], $admin);
        Audit::log('account.avatar_changed', $admin, ['change' => 'uploaded'], $admin);
        Audit::log('account.email_changed', $other, [], $other);

        $this->as($admin)->get(route('platform.account.security'))
            ->assertOk()
            ->assertSee('Password changed')
            ->assertSee('Failed sign-in attempt')
            ->assertDontSee('Email address changed')   // belongs to the other admin
            ->assertDontSee('Photo');                  // avatar changes are not security events
    }

    public function test_activity_is_capped_at_ten_events(): void
    {
        $admin = $this->admin();
        foreach (range(1, 14) as $i) {
            Audit::log('account.password_changed', $admin, [], $admin);
        }

        $response = $this->as($admin)->get(route('platform.account.security'))->assertOk();

        $this->assertSame(10, substr_count($response->getContent(), 'Password changed'));
    }

    public function test_sign_in_and_failed_sign_in_are_recorded(): void
    {
        $user = User::factory()->create(['password' => self::OLD]);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'actor_user_id' => $user->id]);

        $this->post('/login', ['email' => $user->email, 'password' => self::OLD]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $user->id]);
    }

    public function test_non_platform_admin_cannot_reach_security_endpoints(): void
    {
        $user = $this->admin();
        $session = ['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student'];

        $this->actingAs($user)->withSession($session)->get(route('platform.account.security'))->assertForbidden();
        $this->actingAs($user)->withSession($session)->put(route('platform.account.security.password'), [])->assertForbidden();
        $this->actingAs($user)->withSession($session)->post(route('platform.account.security.sessions.revoke'), [])->assertForbidden();
    }
}
