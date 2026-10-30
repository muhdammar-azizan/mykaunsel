<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The booking rules live in config/booking.php, and the API answers an unconfirmed email with the code the app knows on every signed-in route. */
class BookingRulesConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_defaults(): void
    {
        $this->assertSame(2, config('booking.min_lead_hours'));
        $this->assertSame(30, config('booking.horizon_days'));
        $this->assertSame(3, config('booking.max_upcoming_sessions'), 'kept for the confirm step; nothing enforces it while browsing');
        $this->assertSame(14, config('booking.availability_days_per_request'));
        $this->assertNull(config('counselors.directory.slot_horizon_days'), 'the old 60 day horizon is gone: one window for everything');
    }

    public function test_an_unconfirmed_email_gets_the_same_403_code_on_every_signed_in_route(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create());

        foreach (['/api/v1/auth/me', '/api/v1/account/devices', '/api/v1/counselors', '/api/v1/counselors/filters', '/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz', '/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz/availability'] as $path) {
            $this->getJson($path)->assertForbidden()->assertJson(['code' => 'email_not_verified', 'message' => 'Your email address is not verified.']);
        }
    }

    public function test_a_confirmed_email_is_not_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/auth/me')->assertOk();
    }
}
