<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** The signed-in person's own account in the app: profile, password and phones. */
class ApiAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng-Pass-123';

    private const NEW_PASSWORD = 'An0ther-Str0ng-Pass!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSeeder::class);
    }

    private function person(string $email = 'siti@example.com'): User
    {
        return User::factory()->create(['email' => $email, 'name' => 'Siti Aminah', 'password' => self::PASSWORD, 'timezone' => 'Asia/Kuala_Lumpur']);
    }

    /** A real token (so "this phone" can be told from the others), sent as a Bearer header. */
    private function phone(User $user, string $name): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    private function as(string $token): static
    {
        // Each request starts clean, like a real server: no remembered person from the previous one.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    // ---- who can use it ----------------------------------------------------------------------

    public function test_every_account_route_needs_a_signed_in_person_with_a_confirmed_email(): void
    {
        $this->patchJson('/api/v1/account/profile', ['name' => 'X Y', 'timezone' => 'UTC'])->assertUnauthorized();
        $this->putJson('/api/v1/account/password')->assertUnauthorized();
        $this->getJson('/api/v1/account/devices')->assertUnauthorized();
        $this->postJson('/api/v1/account/devices/1/revoke')->assertUnauthorized();

        $unconfirmed = User::factory()->unverified()->create();
        $token = $this->phone($unconfirmed, 'Phone');
        $this->as($token)->getJson('/api/v1/account/devices')->assertForbidden();
    }

    // ---- profile -----------------------------------------------------------------------------

    public function test_name_and_time_zone_can_be_changed_and_the_email_cannot(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');

        $this->as($token)->patchJson('/api/v1/account/profile', [
            'name' => '  Siti Nur Aminah ', 'timezone' => 'Asia/Singapore', 'email' => 'hacker@example.com', 'password' => 'x', 'is_admin' => true,
        ])->assertOk()->assertJsonPath('user.name', 'Siti Nur Aminah')->assertJsonPath('user.timezone', 'Asia/Singapore')->assertJsonPath('user.email', 'siti@example.com');

        $user->refresh();
        $this->assertSame('Siti Nur Aminah', $user->name);
        $this->assertSame('Asia/Singapore', $user->timezone);
        $this->assertSame('siti@example.com', $user->email);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
    }

    public function test_the_profile_is_validated(): void
    {
        $token = $this->phone($this->person(), 'Phone');

        $this->as($token)->patchJson('/api/v1/account/profile', ['name' => '', 'timezone' => 'Mars/Olympus'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'timezone']);
        $this->as($token)->patchJson('/api/v1/account/profile', ['name' => 'A', 'timezone' => 'UTC'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->as($token)->patchJson('/api/v1/account/profile', ['name' => str_repeat('x', 101), 'timezone' => 'UTC'])->assertUnprocessable();
        $this->as($token)->patchJson('/api/v1/account/profile', ['name' => 'Valid Name', 'timezone' => 'Europe/London'])->assertUnprocessable()->assertJsonValidationErrors('timezone');
    }

    public function test_it_only_ever_changes_the_person_holding_the_token(): void
    {
        $siti = $this->person();
        $other = $this->person('other@example.com');
        $other->update(['name' => 'Other Person']);
        $token = $this->phone($siti, 'Phone');

        $this->as($token)->patchJson('/api/v1/account/profile', ['name' => 'Changed Name', 'timezone' => 'UTC', 'id' => $other->id, 'user_id' => $other->id])->assertOk();

        $this->assertSame('Other Person', $other->fresh()->name);
        $this->assertSame('Changed Name', $siti->fresh()->name);
    }

    // ---- password ----------------------------------------------------------------------------

    public function test_changing_the_password_keeps_this_phone_and_signs_every_other_out(): void
    {
        $user = $this->person();
        $thisPhone = $this->phone($user, 'This phone');
        $tablet = $this->phone($user, 'Tablet');
        $old = $this->phone($user, 'Old phone');

        $this->as($thisPhone)->putJson('/api/v1/account/password', [
            'current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->as($thisPhone)->getJson('/api/v1/auth/me')->assertOk();
        $this->as($tablet)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($old)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_password_change_is_audited_without_the_password(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertOk();

        $log = AuditLog::where('action', 'account.password_changed')->where('target_id', $user->id)->sole();
        $this->assertSame($user->id, (int) $log->actor_user_id);
        $this->assertSame('app', $log->metadata['source']);
        $this->assertStringNotContainsString('Pass', json_encode($log->metadata));
    }

    public function test_the_current_password_must_be_right_and_nothing_changes_without_it(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');
        $other = $this->phone($user, 'Tablet');

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => 'wrong-wrong-1', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->as($token)->putJson('/api/v1/account/password', ['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        $this->assertSame(2, $user->tokens()->count());
        $this->as($other)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertCount(0, AuditLog::where('action', 'account.password_changed')->get());
    }

    public function test_the_new_password_must_be_strong_confirmed_and_different(): void
    {
        $token = $this->phone($this->person(), 'Phone');

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => 'weak', 'password_confirmation' => 'weak'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => 'different'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_a_web_session_of_the_same_person_ends_too_when_the_password_changes_in_the_app(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');

        // A real sign-in on the web, with the old password.
        $this->post('/login', ['email' => 'siti@example.com', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get('/select-context')->assertOk(); // a page that needs a signed-in person

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertOk();

        // The web checks the password stored in the session on every request: the old session is no longer accepted.
        // (A real server starts every request fresh; the test app remembers that the last request used the app's guard.)
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->get('/select-context')->assertRedirect(route('login'));
    }

    // ---- devices -----------------------------------------------------------------------------

    public function test_the_list_shows_only_my_phones_with_this_one_first(): void
    {
        $siti = $this->person();
        $other = $this->person('other@example.com');
        $this->phone($siti, 'Old Tablet');
        $mine = $this->phone($siti, 'Google Pixel 8');
        $this->phone($other, 'Someone Elses Phone');

        $data = $this->as($mine)->getJson('/api/v1/account/devices')->assertOk()->json('data');

        $this->assertSame(['Google Pixel 8', 'Old Tablet'], array_column($data, 'name'));
        $this->assertSame([true, false], array_column($data, 'current'));
        $this->assertEqualsCanonicalizing(['id', 'name', 'current', 'last_used_at', 'created_at'], array_keys($data[0]));
        $this->assertStringNotContainsString('Someone Elses', json_encode($data));
        $this->assertStringNotContainsString('mkp_', json_encode($data)); // never the token itself
    }

    public function test_another_phone_can_be_signed_out_with_the_password(): void
    {
        $user = $this->person();
        $mine = $this->phone($user, 'This phone');
        $lost = $this->phone($user, 'Lost phone');
        $lostId = $user->tokens()->where('name', 'Lost phone')->value('id');

        $this->as($mine)->postJson("/api/v1/account/devices/{$lostId}/revoke", ['current_password' => self::PASSWORD])->assertOk();

        $this->as($lost)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->as($mine)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertTrue(AuditLog::where('action', 'account.other_sessions_revoked')->where('target_id', $user->id)->exists());
    }

    public function test_a_wrong_password_signs_nothing_out(): void
    {
        $user = $this->person();
        $mine = $this->phone($user, 'This phone');
        $this->phone($user, 'Other');
        $otherId = $user->tokens()->where('name', 'Other')->value('id');

        $this->as($mine)->postJson("/api/v1/account/devices/{$otherId}/revoke", ['current_password' => 'wrong-wrong-1'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->as($mine)->postJson("/api/v1/account/devices/{$otherId}/revoke")->assertUnprocessable();

        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_somebody_elses_phone_cannot_be_signed_out_it_is_simply_not_found(): void
    {
        $siti = $this->person();
        $other = $this->person('other@example.com');
        $mine = $this->phone($siti, 'Mine');
        $this->phone($other, 'Theirs');
        $theirId = $other->tokens()->value('id');

        $this->as($mine)->postJson("/api/v1/account/devices/{$theirId}/revoke", ['current_password' => self::PASSWORD])->assertNotFound();
        $this->as($mine)->postJson('/api/v1/account/devices/999999/revoke', ['current_password' => self::PASSWORD])->assertNotFound();

        $this->assertSame(1, $other->tokens()->count());
    }

    public function test_the_phone_in_use_is_signed_out_with_sign_out_not_here(): void
    {
        $user = $this->person();
        $mine = $this->phone($user, 'This phone');
        $myId = $user->tokens()->value('id');

        $this->as($mine)->postJson("/api/v1/account/devices/{$myId}/revoke", ['current_password' => self::PASSWORD])
            ->assertUnprocessable()->assertJsonPath('code', 'current_device');

        $this->as($mine)->getJson('/api/v1/auth/me')->assertOk();
    }

    // ---- guessing the password with a stolen token -------------------------------------------

    public function test_a_stolen_token_cannot_be_used_to_guess_the_password(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');
        RateLimiter::clear('sensitive|'.$user->id);

        $codes = collect(range(1, 8))->map(fn ($i) => $this->as($token)->putJson('/api/v1/account/password', [
            'current_password' => "guess-number-{$i}!", 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD,
        ])->getStatusCode());

        $this->assertSame([422, 422, 422, 422, 422, 429, 429, 429], $codes->all());
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_the_limit_is_shared_between_changing_the_password_and_signing_a_phone_out(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');
        $this->phone($user, 'Other');
        $id = $user->tokens()->where('name', 'Other')->value('id');
        RateLimiter::clear('sensitive|'.$user->id);

        foreach (range(1, 5) as $i) {
            $this->as($token)->postJson("/api/v1/account/devices/{$id}/revoke", ['current_password' => "wrong-guess-{$i}"]);
        }

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])->assertStatus(429);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_maintenance_and_forced_updates_apply_here_too(): void
    {
        $token = $this->phone($this->person(), 'Phone');
        config(['api.min_app_version' => '2.0.0']);

        $this->as($token)->withHeaders(['X-App-Version' => '1.0.0'])->getJson('/api/v1/account/devices')->assertStatus(426);
    }

    public function test_the_table_of_devices_is_never_written_to_by_a_failed_attempt(): void
    {
        $user = $this->person();
        $token = $this->phone($user, 'Phone');
        $before = DB::table('personal_access_tokens')->count();

        $this->as($token)->putJson('/api/v1/account/password', ['current_password' => 'nope-nope-1', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD]);

        $this->assertSame($before, DB::table('personal_access_tokens')->count());
    }
}
