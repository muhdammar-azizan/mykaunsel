<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\WebSignupLink;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** "Email me the link to sign up on a computer" for Organization and Counselor, from the app's Choose account type. */
class SignupLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private function ask(array $over = [])
    {
        return $this->postJson('/api/v1/auth/signup-link', $over + ['type' => 'organization', 'email' => 'admin@klinik.example']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('signup-link|'.sha1('organization|admin@klinik.example'));
        Notification::fake();
    }

    public function test_it_sends_the_public_sign_up_address_for_the_kind_asked_for_and_nothing_about_anyone(): void
    {
        $this->ask()->assertStatus(202)->assertJsonPath('message', 'If that address can receive email, the link is on its way.');
        $this->ask(['type' => 'counselor', 'email' => 'Aina@Example.com'])->assertStatus(202);

        Notification::assertSentOnDemand(WebSignupLink::class, function (WebSignupLink $n, array $channels, $notifiable) {
            $mail = $n->toMail($notifiable);

            if ($notifiable->routes['mail'] === 'admin@klinik.example') {
                $this->assertSame(route('organizations.signup.create'), $mail->actionUrl);
                $this->assertStringContainsString('organization', $mail->subject);
            }

            $this->assertStringNotContainsString('token', $mail->actionUrl);
            $this->assertStringNotContainsString('signature', $mail->actionUrl);

            return true;
        });
        Notification::assertSentOnDemandTimes(WebSignupLink::class, 2);
    }

    public function test_the_answer_is_the_same_whether_or_not_the_address_has_an_account(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $known = $this->ask(['email' => 'known@example.com']);
        $unknown = $this->ask(['email' => 'nobody@example.com']);

        $this->assertSame($known->status(), $unknown->status());
        $this->assertSame($known->json(), $unknown->json());
    }

    public function test_one_address_gets_only_a_few_links_an_hour_and_is_answered_the_same_after(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // the route's own burst limit has its own test below
        $answers = [];

        for ($i = 0; $i < 5; $i++) {
            $answers[] = $this->ask()->json();
        }

        $this->assertCount(1, array_unique(array_map('json_encode', $answers)), 'the fifth answer is the same as the first');
        Notification::assertSentOnDemandTimes(WebSignupLink::class, 3);
    }

    public function test_the_same_address_for_the_other_kind_has_its_own_allowance(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        for ($i = 0; $i < 3; $i++) {
            $this->ask();
        }
        $this->ask(['type' => 'counselor'])->assertStatus(202);

        Notification::assertSentOnDemandTimes(WebSignupLink::class, 4);
    }

    public function test_a_closed_kind_of_sign_up_is_refused_with_the_admins_words_and_sends_nothing(): void
    {
        $admin = User::factory()->create();
        app(PlatformSettings::class)->setRegistration($admin, ['counselor'], false);

        $this->ask(['type' => 'counselor'])->assertForbidden()->assertJsonStructure(['message']);
        $this->ask()->assertStatus(202);   // organizations are still open

        Notification::assertSentOnDemandTimes(WebSignupLink::class, 1);
    }

    public function test_only_organization_and_counselor_with_a_real_address(): void
    {
        $this->ask(['type' => 'individual'])->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->ask(['type' => 'admin'])->assertUnprocessable();
        $this->ask(['email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/signup-link', [])->assertUnprocessable()->assertJsonValidationErrors(['type', 'email']);

        Notification::assertNothingSent();
    }

    public function test_maintenance_still_applies(): void
    {
        app(PlatformSettings::class)->setMaintenance(null, true);

        $this->ask()->assertStatus(503);
        Notification::assertNothingSent();
    }

    public function test_it_is_limited_per_address_and_per_visitor(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->ask(['email' => "a{$i}@example.com"])->assertStatus(202);
        }

        // The route's own limit (3 in 10 minutes per address) answers 429 with Retry-After for the fourth try at the SAME address.
        $this->ask(['email' => 'a0@example.com']);
        $this->ask(['email' => 'a0@example.com']);
        $this->ask(['email' => 'a0@example.com'])->assertStatus(429)->assertHeader('Retry-After');
    }
}
