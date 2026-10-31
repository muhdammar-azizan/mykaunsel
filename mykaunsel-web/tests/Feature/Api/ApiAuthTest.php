<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyEmailForApp;
use App\Services\PlatformSettings;
use App\Services\TwoFactorAuthenticator;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng-Pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
        RateLimiter::clear('x');
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['email' => 'siti@example.com', 'password' => self::PASSWORD]);
    }

    private function login(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/login', $overrides + ['email' => 'siti@example.com', 'password' => self::PASSWORD, 'device_name' => 'Pixel 8']);
    }

    private function withTwoFactor(User $user): string
    {
        $secret = app(TwoFactorAuthenticator::class)->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    private function setting(): PlatformSettings
    {
        return new PlatformSettings;
    }

    /** The test app lives across several requests; a real server starts each one fresh. */
    private function resetRequestState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance(PlatformSettings::class);
    }

    // ---- status ------------------------------------------------------------------------------

    public function test_status_is_public_and_tells_what_is_open(): void
    {
        $this->getJson('/api/v1/status')->assertOk()
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonPath('maintenance.on', false)
            ->assertJsonPath('registration.individual.open', true)
            ->assertJsonPath('registration.individual.message', null);
    }

    public function test_status_shows_closed_sign_ups_with_their_message(): void
    {
        $this->setting()->setRegistration(User::first(), ['individual'], false);

        $this->getJson('/api/v1/status')->assertOk()
            ->assertJsonPath('registration.individual.open', false)
            ->assertJsonPath('registration.individual.message', 'We are not accepting new individual sign-ups right now. Please check back soon.')
            ->assertJsonPath('registration.organization.open', true);
    }

    // ---- registration ------------------------------------------------------------------------

    public function test_registering_creates_the_account_without_a_token_and_sends_the_confirmation_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true, 'ai_consent' => true,
        ])->assertCreated()->assertJsonMissingPath('token');

        $user = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue($user->memberships()->doesntExist());
        Notification::assertSentTo($user, VerifyEmailForApp::class, fn ($n) => str_contains($n->toMail($user)->actionUrl, '/email/verify-app/'));
    }

    public function test_registering_needs_the_terms_and_records_which_version_was_agreed(): void
    {
        Notification::fake();
        $body = ['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];

        foreach ([[], ['terms' => false], ['terms' => 'no']] as $extra) {
            $this->postJson('/api/v1/auth/register', $body + $extra)->assertUnprocessable()->assertJsonValidationErrors('terms')
                ->assertJsonPath('errors.terms.0', 'You need to agree to the Terms of Use and the Privacy Policy to create an account.');
        }
        $this->assertNull(User::where('email', 'siti@example.com')->first(), 'nothing was created without it');

        $this->postJson('/api/v1/auth/register', $body + ['terms' => true])->assertCreated();

        $user = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
    }

    public function test_agreeing_to_ai_processing_at_sign_up_is_optional_and_recorded_when_given(): void
    {
        Notification::fake();
        $body = ['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true];

        // Without it (left out or unticked): the account is made, no consent, no record. Booking counselors does not need it.
        $this->postJson('/api/v1/auth/register', $body + ['ai_consent' => false])->assertCreated();
        $without = User::where('email', 'siti@example.com')->firstOrFail();
        $this->assertNull($without->ai_consent_at);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('ai_consent_events')->where('user_id', $without->id)->count());

        $this->postJson('/api/v1/auth/register', ['email' => 'aina@example.com', 'name' => 'Aina'] + $body)->assertCreated();
        $this->assertNull(User::where('email', 'aina@example.com')->firstOrFail()->ai_consent_at);

        $this->postJson('/api/v1/auth/register', ['email' => 'budi@example.com', 'name' => 'Budi', 'ai_consent' => true] + $body)->assertCreated();
        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertNotNull($user->ai_consent_at);
        $event = \Illuminate\Support\Facades\DB::table('ai_consent_events')->where('user_id', $user->id)->sole();
        $this->assertSame('signup', $event->source);
        $this->assertTrue((bool) $event->granted);
    }

    public function test_registration_is_validated(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/register', ['name' => '', 'email' => 'siti@example.com', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_is_blocked_while_individual_sign_up_is_closed(): void
    {
        $this->setting()->setRegistration(User::first(), ['individual'], false);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true, 'ai_consent' => true,
        ])->assertForbidden()->assertJsonPath('message', 'We are not accepting new individual sign-ups right now. Please check back soon.');

        $this->assertNull(User::where('email', 'siti@example.com')->first());
    }

    public function test_closing_other_kinds_of_sign_up_does_not_block_individuals(): void
    {
        $this->setting()->setRegistration(User::first(), ['organization', 'counselor'], false);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true, 'ai_consent' => true,
        ])->assertCreated();
    }

    public function test_the_emailed_link_confirms_the_address_without_a_web_sign_in(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Siti Aminah', 'email' => 'siti@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true, 'ai_consent' => true,
        ]);
        $user = User::where('email', 'siti@example.com')->firstOrFail();

        $url = null;
        Notification::assertSentTo($user, VerifyEmailForApp::class, function ($n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });

        $this->get($url)->assertOk()->assertSee('Email confirmed');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->login()->assertOk();
    }

    public function test_a_tampered_or_unsigned_confirmation_link_does_nothing(): void
    {
        $user = $this->user(['email_verified_at' => null]);

        $this->get('/email/verify-app/'.$user->id.'/'.sha1($user->email))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $signed = \URL::temporarySignedRoute('verification.app', now()->addHour(), ['id' => $user->id, 'hash' => sha1('someone-else@example.com')]);
        $this->get($signed)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resending_the_confirmation_gives_the_same_answer_for_everyone(): void
    {
        Notification::fake();
        $unverified = $this->user(['email_verified_at' => null]);

        $known = $this->postJson('/api/v1/auth/email/resend', ['email' => 'siti@example.com']);
        $unknown = $this->postJson('/api/v1/auth/email/resend', ['email' => 'nobody@example.com']);

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentToTimes($unverified, VerifyEmailForApp::class, 1);
    }

    // ---- login -------------------------------------------------------------------------------

    public function test_signing_in_gives_a_token_that_works_and_is_audited(): void
    {
        $user = $this->user();

        $response = $this->login()->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.email', 'siti@example.com');

        $this->assertMatchesRegularExpression('/^\d+\|mkp_/', $response->json('token'));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('target_id', $user->id)->exists());

        $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.name', $user->name);
    }

    public function test_the_response_never_contains_secrets(): void
    {
        $this->user();

        $body = json_encode($this->login()->json());

        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('two_factor', $body);
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_wrong_password_and_unknown_email_look_the_same(): void
    {
        $this->user();

        $wrong = $this->login(['password' => 'nope-nope-nope'])->assertUnprocessable();
        $unknown = $this->login(['email' => 'ghost@example.com'])->assertUnprocessable();

        $this->assertSame($wrong->json(), $unknown->json());
        $this->assertTrue(AuditLog::where('action', 'auth.login_failed')->exists());
    }

    public function test_too_many_wrong_passwords_lock_the_sign_in_for_a_while(): void
    {
        $this->user();

        foreach (range(1, 5) as $i) {
            $this->login(['password' => 'wrong-wrong-'.$i])->assertUnprocessable();
        }

        $this->login()->assertUnprocessable()->assertJsonValidationErrors('email'); // even the right password: locked
    }

    public function test_an_unconfirmed_email_gets_no_token(): void
    {
        $this->user(['email_verified_at' => null]);

        $this->login()->assertForbidden()->assertJsonPath('code', 'email_not_verified')->assertJsonMissingPath('token');
    }

    public function test_the_device_name_is_required(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', ['email' => 'siti@example.com', 'password' => self::PASSWORD])->assertUnprocessable()->assertJsonValidationErrors('device_name');
    }

    public function test_an_old_unconfirmed_token_is_not_enough_for_protected_routes(): void
    {
        $user = $this->user();
        $token = $user->createToken('x')->plainTextToken;
        $user->forceFill(['email_verified_at' => null])->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    // ---- protected routes --------------------------------------------------------------------

    public function test_protected_routes_need_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->withToken('mkp_not-a-real-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_errors_are_json_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/auth/me', ['Accept' => 'text/html'])->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
    }

    public function test_logging_out_removes_only_this_device(): void
    {
        $user = $this->user();
        $phone = $this->login(['device_name' => 'Phone'])->json('token');
        $tablet = $this->login(['device_name' => 'Tablet'])->json('token');

        $this->withToken($phone)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->resetRequestState();
        $this->withToken($tablet)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_logging_out_everywhere_removes_every_token(): void
    {
        $user = $this->user();
        $this->login(['device_name' => 'Phone']);
        $token = $this->login(['device_name' => 'Tablet'])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout-all')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_only_the_newest_ten_devices_keep_a_token(): void
    {
        $user = $this->user();

        // Straight through the service: 12 sign-ins of one email inside a minute would (rightly) hit the login rate limit.
        foreach (range(1, 12) as $i) {
            app(\App\Services\ApiAuthService::class)->issueToken(request(), $user, "Phone {$i}");
        }

        $this->assertSame(10, $user->tokens()->count());
        $this->assertSame(0, $user->tokens()->where('name', 'Phone 1')->count());
    }

    public function test_a_token_expires(): void
    {
        $user = $this->user();
        $token = $this->login()->json('token');
        $this->travel(31)->days();

        $this->resetRequestState();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(1, $user->tokens()->count());
    }

    // ---- two-factor --------------------------------------------------------------------------

    public function test_a_password_alone_never_gives_a_token_to_an_account_with_two_factor(): void
    {
        $this->withTwoFactor($this->user());

        $this->login()->assertStatus(202)->assertJsonPath('code', 'two_factor_required')->assertJsonStructure(['challenge'])->assertJsonMissingPath('token');
    }

    public function test_the_right_code_completes_the_sign_in(): void
    {
        $user = $this->user();
        $secret = $this->withTwoFactor($user);
        $challenge = $this->login()->json('challenge');

        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => (new Google2FA)->getCurrentOtp($secret), 'device_name' => 'Pixel 8'])
            ->assertOk()->assertJsonStructure(['token', 'user']);

        // The challenge cannot be used again.
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '123456', 'device_name' => 'Pixel 8'])->assertUnprocessable();
    }

    public function test_a_wrong_code_is_refused_audited_and_limited(): void
    {
        $user = $this->user();
        $this->withTwoFactor($user);
        $challenge = $this->login()->json('challenge');

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '000000', 'device_name' => 'Pixel 8'])->assertUnprocessable()->assertJsonValidationErrors('code');
        }

        $this->assertSame(5, AuditLog::where('action', 'account.two_factor_failed')->count());
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '000000', 'device_name' => 'Pixel 8'])->assertUnprocessable();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = $this->user();
        $this->withTwoFactor($user);
        $codes = app(TwoFactorAuthenticator::class)->issueRecoveryCodes($user);

        $first = $this->login()->json('challenge');
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $first, 'recovery_code' => $codes[0], 'device_name' => 'Pixel 8'])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'account.recovery_code_used')->exists());

        $second = $this->login()->json('challenge');
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $second, 'recovery_code' => $codes[0], 'device_name' => 'Pixel 8'])->assertUnprocessable();
    }

    public function test_an_unknown_or_expired_challenge_is_refused(): void
    {
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => 'made-up', 'code' => '123456', 'device_name' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('challenge');

        $user = $this->user();
        $this->withTwoFactor($user);
        $challenge = $this->login()->json('challenge');
        $this->travel(11)->minutes();
        $this->postJson('/api/v1/auth/two-factor', ['challenge' => $challenge, 'code' => '123456', 'device_name' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('challenge');
    }

    // ---- password reset ----------------------------------------------------------------------

    public function test_forgot_password_gives_the_same_answer_for_everyone(): void
    {
        Notification::fake();
        $user = $this->user();

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'siti@example.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com']);

        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_resetting_the_password_signs_out_every_device(): void
    {
        $user = $this->user();
        $this->login(['device_name' => 'Phone']);
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'A-brand-New-pass-456', 'password_confirmation' => 'A-brand-New-pass-456'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, $user->tokens()->count());
    }

    // ---- maintenance -------------------------------------------------------------------------

    public function test_maintenance_blocks_the_api_except_status(): void
    {
        $user = $this->user();
        $token = $this->login()->json('token');
        $this->setting()->setMaintenance(User::where('email', 'admin@mykaunsel.com')->firstOrFail(), true);

        $this->resetRequestState();
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('maintenance.on', true)->assertJsonPath('maintenance.message', fn ($m) => str_contains($m, 'maintenance'));
        $this->login()->assertStatus(503)->assertJsonStructure(['message']);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(503);
        $this->postJson('/api/v1/auth/register', ['name' => 'A B', 'email' => 'ab@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, 'terms' => true])->assertStatus(503);
    }

    // ---- housekeeping ------------------------------------------------------------------------

    public function test_the_api_does_not_start_a_web_session(): void
    {
        $this->user();

        $response = $this->login();

        $this->assertEmpty(collect($response->headers->getCookies())->pluck('name')->filter(fn ($n) => str_contains($n, 'session'))->all());
    }
}
