<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    private function configured(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    }

    private function googleReturns(string $email, bool $verified = true): void
    {
        $google = (new GoogleUser)->map(['id' => '1', 'name' => 'Someone', 'email' => $email]);
        $google->user = ['email_verified' => $verified];

        Socialite::shouldReceive('driver->user')->andReturn($google);
    }

    public function test_an_existing_account_with_the_same_verified_email_is_signed_in(): void
    {
        $this->configured();
        $user = User::factory()->create(['email' => 'me@example.com']);
        $this->googleReturns('me@example.com');

        $this->get(route('google.callback'))->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_an_email_with_no_account_is_refused_and_no_account_is_created(): void
    {
        $this->configured();
        $this->googleReturns('stranger@example.com');
        $users = User::count();

        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame($users, User::count());
    }

    public function test_an_email_google_has_not_verified_is_refused(): void
    {
        $this->configured();
        User::factory()->create(['email' => 'me@example.com']);
        $this->googleReturns('me@example.com', verified: false);

        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_account_with_two_factor_goes_to_the_code_step_not_straight_in(): void
    {
        $this->configured();
        $user = User::factory()->create(['email' => 'me@example.com']);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
        $this->googleReturns('me@example.com');

        $this->get(route('google.callback'))->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
    }

    public function test_the_button_does_nothing_harmful_when_google_is_not_set_up(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->get(route('google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('email');
    }

    public function test_the_redirect_goes_to_google_when_it_is_set_up(): void
    {
        $this->configured();
        Socialite::shouldReceive('driver->redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        $this->get(route('google.redirect'))->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
