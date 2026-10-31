<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\BookingConfirmedClient;
use App\Notifications\ClientSessionNotice;
use App\Notifications\TimeRequestAnsweredClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The e-mails a person can switch off in the app's Account > Notifications (e-mail only), and that the sending code obeys the choice. */
class AccountNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($this->me);
    }

    public function test_it_lists_the_choices_with_their_state_and_defaults_to_on(): void
    {
        $this->getJson('/api/v1/account/notification-preferences')->assertOk()
            ->assertJsonCount(1, 'preferences')
            ->assertJsonPath('preferences.0.key', 'booking_emails')
            ->assertJsonPath('preferences.0.label', 'Bookings')
            ->assertJsonPath('preferences.0.enabled', true);
    }

    public function test_saving_one_choice_returns_the_list_as_it_now_is(): void
    {
        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => false])
            ->assertOk()->assertJsonPath('preferences.0.enabled', false);

        $this->assertFalse($this->me->fresh()->wantsNotification('booking_emails'));
        $this->getJson('/api/v1/account/notification-preferences')->assertJsonPath('preferences.0.enabled', false);

        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => true])->assertOk()->assertJsonPath('preferences.0.enabled', true);
    }

    public function test_only_the_keys_a_person_may_set_and_a_real_yes_or_no(): void
    {
        foreach (['weekly_summary', 'escalated_complaint', 'made_up', ''] as $key) {
            $this->putJson('/api/v1/account/notification-preferences', ['key' => $key, 'enabled' => false])->assertUnprocessable()->assertJsonValidationErrors('key');
        }
        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('enabled');

        $this->assertNull($this->me->fresh()->notification_preferences, 'nothing was stored');
    }

    public function test_a_platform_admins_own_choices_are_left_alone(): void
    {
        $this->me->forceFill(['notification_preferences' => ['weekly_summary' => false]])->save();

        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => false])->assertOk();

        $this->assertSame(['weekly_summary' => false, 'booking_emails' => false], $this->me->fresh()->notification_preferences);
        $this->assertFalse($this->me->fresh()->wantsNotification('weekly_summary'));
    }

    public function test_signed_out_gets_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/account/notification-preferences')->assertUnauthorized();
        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => false])->assertUnauthorized();
    }

    public function test_the_booking_and_request_e_mails_obey_the_choice_and_nothing_else_does(): void
    {
        $session = ['counselor' => 'Aina', 'date' => 'x', 'time' => 'y', 'minutes' => 60, 'mode' => 'online', 'place' => null, 'deadline' => null];
        $answer = ['outcome' => 'approved', 'counselor' => 'Aina', 'date' => 'x', 'time' => 'y', 'minutes' => 60, 'mode' => 'online', 'reason' => null];

        $this->assertSame(['mail'], (new BookingConfirmedClient($session))->via($this->me));
        $this->assertSame(['mail'], (new TimeRequestAnsweredClient($answer))->via($this->me));

        $this->putJson('/api/v1/account/notification-preferences', ['key' => 'booking_emails', 'enabled' => false])->assertOk();
        $me = $this->me->fresh();

        $this->assertSame([], (new BookingConfirmedClient($session))->via($me));
        $this->assertSame([], (new TimeRequestAnsweredClient($answer))->via($me));
        // A session cancelled for the person by the platform must always reach them.
        $this->assertSame(['mail'], (new ClientSessionNotice('Aina', 'x', 'session'))->via($me));
    }

    public function test_the_status_carries_the_support_address_only_when_one_is_set(): void
    {
        $this->app['auth']->forgetGuards();

        config(['api.support_email' => null]);
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('support_email', null);

        config(['api.support_email' => 'help@mykaunsel.example']);
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('support_email', 'help@mykaunsel.example');
    }
}
