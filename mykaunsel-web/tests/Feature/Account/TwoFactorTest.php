<?php

namespace Tests\Feature\Account;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret#123';

    private array $session = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Setup/confirm/disable endpoints share a throttle; these tests exercise many steps in a row.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function admin(string $name = 'Ammar Azizan'): User
    {
        $platform = Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], [
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create(['name' => $name, 'password' => self::PASSWORD]);

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

    /** A valid code for the NEXT 30s step: the current step is already used up by setup (replay protection). */
    private function nextCode(string $secret): string
    {
        $google = new Google2FA;

        return $google->oathTotp($secret, $google->getTimestamp() + 1);
    }

    /** Walk the real setup flow and return [user, secret, recoveryCodes]. */
    private function enable(User $admin): array
    {
        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD])
            ->assertRedirect();

        $secret = $admin->fresh()->two_factor_secret;
        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->as($admin)->post(route('platform.account.two-factor.confirm'), ['code' => $code]);
        $response->assertSessionHas('recovery_codes');

        return [$admin->fresh(), $secret, session('recovery_codes')];
    }

    public function test_enabling_needs_the_password_and_does_not_turn_on_until_a_code_is_confirmed(): void
    {
        $admin = $this->admin();

        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('twoFactor', 'current_password');
        $this->assertNull($admin->fresh()->two_factor_secret);

        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD]);

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertTrue($admin->hasPendingTwoFactor());
        $this->assertFalse($admin->hasTwoFactorEnabled());
    }

    public function test_setup_page_shows_qr_and_manual_key_while_pending(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD]);

        $secret = $admin->fresh()->two_factor_secret;

        $this->as($admin)->get(route('platform.account.security'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('Scan this QR code')
            ->assertSee(trim(chunk_split($secret, 4, ' ')))
            ->assertSee('Confirm and turn on');
    }

    public function test_wrong_code_does_not_enable(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD]);

        $this->as($admin)->post(route('platform.account.two-factor.confirm'), ['code' => '000000'])
            ->assertSessionHasErrorsIn('twoFactorCode', 'code');

        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirming_a_valid_code_enables_two_factor_issues_eight_codes_and_audits(): void
    {
        $admin = $this->admin();
        [$admin, , $codes] = $this->enable($admin);

        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertCount(8, $codes);
        $this->assertCount(8, $admin->recoveryCodeHashes());
        $this->assertNotContains($codes[0], $admin->recoveryCodeHashes(), 'recovery codes must be stored hashed, not in plain text');
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.two_factor_enabled', 'actor_user_id' => $admin->id]);

        // The first page load after enabling shows the codes; any later load must not.
        $this->as($admin)->get(route('platform.account.security'))->assertOk()->assertSee($codes[0])->assertSee('Save your recovery codes');
        $this->as($admin)->get(route('platform.account.security'))->assertOk()->assertDontSee($codes[0]);
    }

    public function test_secret_is_encrypted_at_rest(): void
    {
        $admin = $this->admin();
        [$admin, $secret] = $this->enable($admin);

        $raw = \DB::table('users')->where('id', $admin->id)->value('two_factor_secret');

        $this->assertNotSame($secret, $raw);
        $this->assertStringNotContainsString($secret, (string) $raw);
    }

    public function test_login_with_two_factor_on_requires_the_code(): void
    {
        $admin = $this->admin();
        [$admin, $secret] = $this->enable($admin);
        $this->flushSession();
        auth()->logout();

        // Right password: NOT signed in yet, sent to the challenge.
        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $admin->id]);

        $this->get(route('two-factor.challenge'))->assertOk()->assertSee('Two-factor authentication');

        // Wrong code
        $this->post(route('two-factor.challenge.store'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.two_factor_failed', 'actor_user_id' => $admin->id]);

        // Right code
        $this->post(route('two-factor.challenge.store'), ['code' => $this->nextCode($secret)])
            ->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_user_id' => $admin->id]);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_challenge_page_is_unreachable_without_passing_the_password_step(): void
    {
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
        $this->post(route('two-factor.challenge.store'), ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_password_never_reaches_the_challenge(): void
    {
        $admin = $this->admin();
        [$admin] = $this->enable($admin);
        $this->flushSession();
        auth()->logout();

        $this->post('/login', ['email' => $admin->email, 'password' => 'not-it'])->assertSessionHasErrors('email');
        $this->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $admin = $this->admin();
        [$admin, $secret] = $this->enable($admin);
        $code = (new Google2FA)->getCurrentOtp($secret);

        // confirming already consumed this time-step, so the same code is rejected now
        $this->assertFalse(app(TwoFactorAuthenticator::class)->verify($admin->fresh(), $code));
    }

    public function test_recovery_code_signs_in_once_and_is_burned(): void
    {
        $admin = $this->admin();
        [$admin, , $codes] = $this->enable($admin);
        $this->flushSession();
        auth()->logout();

        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post(route('two-factor.challenge.store'), ['recovery_code' => $codes[0]])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
        $this->assertCount(7, $admin->fresh()->recoveryCodeHashes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.recovery_code_used', 'actor_user_id' => $admin->id]);

        // same code again (new sign-in) is refused
        $this->post('/logout');
        $this->flushSession();
        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->post(route('two-factor.challenge.store'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_is_rate_limited(): void
    {
        $this->withMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $admin = $this->admin();
        [$admin] = $this->enable($admin);
        $this->flushSession();
        auth()->logout();
        RateLimiter::clear('two-factor|'.$admin->id.'|127.0.0.1');

        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD]);

        foreach (range(1, 5) as $i) {
            $this->post(route('two-factor.challenge.store'), ['code' => '111111'])->assertSessionHasErrors('code');
        }

        // 6th attempt is locked out even with a correct code
        $secret = $admin->fresh()->two_factor_secret;
        $response = $this->post(route('two-factor.challenge.store'), ['code' => $this->nextCode($secret)]);
        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_regenerating_recovery_codes_needs_password_replaces_codes_and_audits(): void
    {
        $admin = $this->admin();
        [$admin, , $old] = $this->enable($admin);
        $before = $admin->recoveryCodeHashes();

        $this->as($admin)->post(route('platform.account.two-factor.recovery'), ['current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('twoFactor', 'current_password');
        $this->assertSame($before, $admin->fresh()->recoveryCodeHashes());

        $this->as($admin)->post(route('platform.account.two-factor.recovery'), ['current_password' => self::PASSWORD])
            ->assertSessionHas('recovery_codes');

        $this->assertNotSame($before, $admin->fresh()->recoveryCodeHashes());
        $this->assertCount(8, $admin->fresh()->recoveryCodeHashes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.recovery_codes_regenerated', 'actor_user_id' => $admin->id]);
        $this->assertFalse(app(TwoFactorAuthenticator::class)->consumeRecoveryCode($admin->fresh(), $old[0]), 'old codes must stop working');
    }

    public function test_disabling_needs_password_clears_everything_and_audits(): void
    {
        $admin = $this->admin();
        [$admin] = $this->enable($admin);

        $this->as($admin)->post(route('platform.account.two-factor.disable'), ['current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('twoFactor', 'current_password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->as($admin)->post(route('platform.account.two-factor.disable'), ['current_password' => self::PASSWORD]);

        $admin->refresh();
        $this->assertFalse($admin->hasTwoFactorEnabled());
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_recovery_codes);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.two_factor_disabled', 'actor_user_id' => $admin->id]);

        // login no longer asks for a code
        $this->flushSession();
        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_cancelling_setup_clears_the_pending_secret(): void
    {
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD]);
        $this->assertTrue($admin->fresh()->hasPendingTwoFactor());

        $this->as($admin)->post(route('platform.account.two-factor.cancel'));

        $this->assertNull($admin->fresh()->two_factor_secret);
    }

    public function test_two_factor_actions_only_touch_the_signed_in_admin(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Second Admin');
        [$other] = $this->enable($other);
        $this->flushSession();

        $this->as($admin)->post(route('platform.account.two-factor.disable'), ['current_password' => self::PASSWORD, 'user_id' => $other->id]);

        $this->assertTrue($other->fresh()->hasTwoFactorEnabled(), 'another admin must keep their 2FA');
    }

    public function test_non_platform_admin_cannot_use_two_factor_endpoints(): void
    {
        $user = $this->admin();
        $session = ['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student'];

        $this->actingAs($user)->withSession($session)
            ->post(route('platform.account.two-factor.begin'), ['current_password' => self::PASSWORD])->assertForbidden();
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
