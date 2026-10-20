<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const NEW = 'New#Secret99';

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')
            ->put('/password', ['current_password' => 'password', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check(self::NEW, $user->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'password_changed', 'actor_user_id' => $user->id]);
    }

    public function test_updating_the_password_signs_out_other_devices_only(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();

        foreach (['phone' => $user, 'someone-else' => $other] as $id => $owner) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => now()->timestamp]);
        }

        $this->actingAs($user)->put('/password', ['current_password' => 'password', 'password' => self::NEW, 'password_confirmation' => self::NEW]);

        $this->assertDatabaseMissing('sessions', ['id' => 'phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
    }

    public function test_weak_passwords_are_still_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')
            ->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/profile')
            ->put('/password', ['current_password' => 'wrong-password', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
