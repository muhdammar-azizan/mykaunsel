<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailForApp;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Things that only go wrong with real users: shared mobile IPs, old app versions, mail flooding. */
class ApiProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    // ---- shared mobile IPs -------------------------------------------------------------------

    public function test_many_different_people_behind_one_ip_can_all_sign_in(): void
    {
        foreach (range(1, 40) as $i) {
            User::factory()->create(['email' => "orang{$i}@example.com", 'password' => 'Str0ng-Pass-123']);
        }

        // 40 different people on the same mobile-network IP, one attempt each: nobody is blocked.
        foreach (range(1, 40) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
                ->postJson('/api/v1/auth/login', ['email' => "orang{$i}@example.com", 'password' => 'Str0ng-Pass-123', 'device_name' => 'Phone'])
                ->assertOk();
        }
    }

    public function test_guessing_one_account_is_limited_even_from_changing_ips(): void
    {
        User::factory()->create(['email' => 'siti@example.com', 'password' => 'Str0ng-Pass-123']);

        $statuses = [];
        foreach (range(1, 14) as $i) {
            $statuses[] = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$i])
                ->postJson('/api/v1/auth/login', ['email' => 'siti@example.com', 'password' => 'wrong-'.$i, 'device_name' => 'x'])
                ->getStatusCode();
        }

        $this->assertContains(429, $statuses);
    }

    public function test_one_persons_lockout_does_not_lock_out_their_neighbour_on_the_same_ip(): void
    {
        User::factory()->create(['email' => 'siti@example.com', 'password' => 'Str0ng-Pass-123']);
        User::factory()->create(['email' => 'amin@example.com', 'password' => 'Str0ng-Pass-123']);

        foreach (range(1, 12) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
                ->postJson('/api/v1/auth/login', ['email' => 'siti@example.com', 'password' => 'wrong-'.$i, 'device_name' => 'x']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson('/api/v1/auth/login', ['email' => 'amin@example.com', 'password' => 'Str0ng-Pass-123', 'device_name' => 'x'])
            ->assertOk();
    }

    public function test_the_status_route_survives_a_crowd(): void
    {
        foreach (range(1, 100) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/status')->assertOk();
        }
    }

    public function test_nobody_can_flood_one_inbox(): void
    {
        Notification::fake();
        User::factory()->unverified()->create(['email' => 'siti@example.com']);

        $statuses = [];
        foreach (range(1, 6) as $i) {
            $statuses[] = $this->postJson('/api/v1/auth/email/resend', ['email' => 'siti@example.com'])->getStatusCode();
        }

        $this->assertSame([202, 202, 202, 429, 429, 429], $statuses);
        Notification::assertSentTimes(VerifyEmailForApp::class, 3);
    }

    public function test_the_email_limit_ignores_upper_case_tricks(): void
    {
        Notification::fake();
        User::factory()->unverified()->create(['email' => 'siti@example.com']);

        $statuses = collect(['siti@example.com', 'SITI@example.com', 'Siti@Example.com', 'siti@EXAMPLE.com'])
            ->map(fn ($email) => $this->postJson('/api/v1/auth/email/resend', ['email' => $email])->getStatusCode());

        $this->assertSame(429, $statuses->last());
    }

    public function test_a_limited_answer_is_json_with_retry_after(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com']);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['message']);
    }

    // ---- old app versions --------------------------------------------------------------------

    public function test_an_app_older_than_the_minimum_is_told_to_update(): void
    {
        config(['api.min_app_version' => '1.2.0', 'api.store_urls.android' => 'https://play.example/app', 'api.store_urls.ios' => 'https://apps.example/app']);

        $this->withHeaders(['X-App-Version' => '1.1.9'])->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'x', 'device_name' => 'x'])
            ->assertStatus(426)->assertJsonPath('code', 'app_update_required')->assertJsonPath('min_version', '1.2.0')->assertJsonPath('store_url', 'https://play.example/app');

        $this->withHeaders(['X-App-Version' => '0.9.0', 'X-App-Platform' => 'ios'])->postJson('/api/v1/auth/register', [])
            ->assertStatus(426)->assertJsonPath('store_url', 'https://apps.example/app');
    }

    public function test_current_and_newer_apps_are_not_blocked(): void
    {
        config(['api.min_app_version' => '1.2.0']);

        foreach (['1.2.0', '1.2.1', '1.10.0', '2.0.0', '1.2.0+45'] as $version) {
            $this->withHeaders(['X-App-Version' => $version])->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'x', 'device_name' => 'x'])
                ->assertUnprocessable();
        }
    }

    public function test_a_request_without_a_version_header_is_not_blocked(): void
    {
        config(['api.min_app_version' => '9.0.0']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'x', 'device_name' => 'x'])->assertUnprocessable();
        $this->withHeaders(['X-App-Version' => 'garbage'])->postJson('/api/v1/auth/login', ['email' => 'a@b.com', 'password' => 'x', 'device_name' => 'x'])->assertUnprocessable();
    }

    public function test_by_default_nobody_is_forced_to_update(): void
    {
        $this->withHeaders(['X-App-Version' => '0.0.1'])->getJson('/api/v1/status')->assertOk()->assertJsonPath('app.update_required', false);
    }

    public function test_status_still_answers_an_old_app_and_says_it_must_update(): void
    {
        config(['api.min_app_version' => '1.2.0']);

        $this->withHeaders(['X-App-Version' => '1.0.0'])->getJson('/api/v1/status')->assertOk()
            ->assertJsonPath('app.update_required', true)->assertJsonPath('app.min_version', '1.2.0')->assertJsonStructure(['app' => ['message', 'store_url']]);

        $this->withHeaders(['X-App-Version' => '1.2.0'])->getJson('/api/v1/status')->assertJsonPath('app.update_required', false);
    }

    // ---- real visitor IP behind a proxy ------------------------------------------------------

    public function test_the_visitor_ip_comes_from_the_proxy_header_only_when_that_proxy_is_trusted(): void
    {
        $user = User::factory()->create(['email' => 'siti@example.com', 'password' => 'Str0ng-Pass-123']);
        $send = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])->withHeaders(['X-Forwarded-For' => '203.0.113.77'])
            ->postJson('/api/v1/auth/login', ['email' => 'siti@example.com', 'password' => 'Str0ng-Pass-123', 'device_name' => 'x']);

        // Not trusted (default): the proxy's own address is what we see.
        $send()->assertOk();
        $this->assertSame('10.0.0.5', $user->fresh()->last_login_ip);

        // Trusted: the real visitor.
        \Illuminate\Http\Middleware\TrustProxies::at('10.0.0.5');
        $send()->assertOk();
        $this->assertSame('203.0.113.77', $user->fresh()->last_login_ip);
    }

    protected function tearDown(): void
    {
        \Illuminate\Http\Middleware\TrustProxies::flushState();
        RateLimiter::clear('x');
        parent::tearDown();
    }
}
