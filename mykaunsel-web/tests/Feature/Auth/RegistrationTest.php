<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Register#Secret99',
            'password_confirmation' => 'Register#Secret99',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $this->assertNotNull(auth()->user()->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), auth()->user()->terms_version);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_the_terms_must_be_agreed_to(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Register#Secret99',
            'password_confirmation' => 'Register#Secret99',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
        $this->assertNull(\App\Models\User::where('email', 'test@example.com')->first());
        $this->get('/register')->assertSee('Terms of Use')->assertSee('name="terms"', false);
    }
}
