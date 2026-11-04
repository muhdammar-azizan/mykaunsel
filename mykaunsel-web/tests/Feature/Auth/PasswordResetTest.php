<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_answer_is_the_same_whether_or_not_the_e_mail_address_has_an_account(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $answer = 'If an account uses that e-mail address, we have sent a link to reset the password. Check your inbox and your spam folder.';

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $answer)->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHas('status', $answer)->assertSessionHasNoErrors();
        // Asking again at once (the link was sent a moment ago) says the same: it must not tell that the address is known.
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $answer)->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertNotSentTo(User::factory()->make(['email' => 'nobody@example.test']), ResetPassword::class);
    }

    public function test_asking_for_a_reset_link_and_resetting_are_limited_per_address(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertSessionHasNoErrors();
        }

        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertStatus(429);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Reset#Secret99',
                'password_confirmation' => 'Reset#Secret99',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
