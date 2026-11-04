<?php

namespace Tests\Feature\Account;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * A counselor's own account (profile, e-mail choices, password, other devices): what is audited, that the app's tokens end with a password
 * change or "sign out other devices", and that a changed name which no longer matches the LKM record is told to the platform admins.
 */
class CounselorAccountTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private const OLD = 'Secret#123';

    private const NEW = 'Brand#New9pass';

    private CounselorProfile $aina;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina Ahmad', [$this->platform]);
        $this->aina->user->forceFill(['password' => self::OLD])->save();

        $this->admin = User::factory()->create(['name' => 'Platform Admin']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Carbon::setTestNow();

        parent::tearDown();
    }

    /** One request as the counselor (a session keeps the password hash of before, so each request starts a clean one). */
    private function as()
    {
        $this->flushSession();

        return $this->actingAs($this->aina->user->fresh())->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->where('actor_user_id', $this->aina->user_id)->get();
    }

    private function lkmRecord(string $name): void
    {
        LkmDirectorySnapshot::create([
            'kb_number' => $this->aina->kb_number, 'pa_number' => $this->aina->pa_number ?? 'PA12345', 'full_name' => $name, 'status' => 'Aktif',
            'pa_valid_from' => '2025-01-01', 'pa_valid_until' => '2030-01-01',
        ]);
    }

    // ---- profile and e-mail choices ---------------------------------------------------------------------------------------

    public function test_changing_the_name_or_zone_is_audited_with_the_fields_only(): void
    {
        $this->as()->patch(route('portal.account.update'), ['name' => 'Aina Renamed Privately', 'timezone' => 'Asia/Singapore'])->assertRedirect(route('portal.account'));

        $entry = $this->logs('account.profile_updated')->sole();
        $this->assertEqualsCanonicalizing(['name', 'timezone'], $entry->metadata['fields']);
        $this->assertStringNotContainsString('Renamed Privately', json_encode($entry->getAttributes()));
        $this->assertStringNotContainsString('Singapore', json_encode($entry->getAttributes()));

        // Saving the same again changes nothing and writes nothing.
        $this->as()->patch(route('portal.account.update'), ['name' => 'Aina Renamed Privately', 'timezone' => 'Asia/Singapore'])->assertRedirect();
        $this->assertCount(1, $this->logs('account.profile_updated'));
    }

    public function test_an_email_choice_is_audited_only_when_it_really_changes(): void
    {
        $this->as()->patchJson(route('portal.account.notifications.update'), ['key' => 'counselor_session_emails', 'enabled' => false])->assertOk();
        $this->as()->patchJson(route('portal.account.notifications.update'), ['key' => 'counselor_session_emails', 'enabled' => false])->assertOk();

        $entry = $this->logs('account.notification_preference_changed')->sole();
        $this->assertSame('counselor_session_emails', $entry->metadata['key']);
        $this->assertFalse($entry->metadata['enabled']);

        $this->as()->patchJson(route('portal.account.notifications.update'), ['key' => 'counselor_session_emails', 'enabled' => true])->assertOk();
        $this->assertCount(2, $this->logs('account.notification_preference_changed'));
    }

    // ---- the name and the LKM record -------------------------------------------------------------------------------------

    public function test_a_name_that_no_longer_matches_the_lkm_record_is_told_to_the_platform_admins_and_not_blocked(): void
    {
        $this->lkmRecord('Aina Ahmad');

        $this->as()->patch(route('portal.account.update'), ['name' => 'Someone Else Entirely', 'timezone' => 'Asia/Kuala_Lumpur'])->assertRedirect(route('portal.account'));

        $this->assertSame('Someone Else Entirely', $this->aina->user->fresh()->name, 'allowed');

        $row = UserNotification::where('recipient_user_id', $this->admin->id)->where('type', 'counselor.name_changed')->sole();
        $this->assertSame($this->aina->id, $row->target_id);
        $this->assertSame(0, UserNotification::where('recipient_user_id', $this->aina->user_id)->where('type', 'counselor.name_changed')->count(), 'not to the counselor');
    }

    public function test_a_spelling_change_that_still_matches_or_a_counselor_with_no_lkm_record_tells_nobody(): void
    {
        $this->lkmRecord('Aina Ahmad');
        $this->as()->patch(route('portal.account.update'), ['name' => 'AINA  AHMAD', 'timezone' => 'Asia/Kuala_Lumpur'])->assertRedirect();
        $this->assertSame(0, UserNotification::where('type', 'counselor.name_changed')->count(), 'capitals and spacing do not matter');

        LkmDirectorySnapshot::query()->delete();
        $this->as()->patch(route('portal.account.update'), ['name' => 'Another Name', 'timezone' => 'Asia/Kuala_Lumpur'])->assertRedirect();
        $this->assertSame(0, UserNotification::where('type', 'counselor.name_changed')->count(), 'nothing to compare with');
    }

    public function test_changing_only_the_zone_does_not_look_at_the_name(): void
    {
        $this->lkmRecord('A Completely Different Person');

        $this->as()->patch(route('portal.account.update'), ['name' => $this->aina->user->name, 'timezone' => 'Asia/Singapore'])->assertRedirect();

        $this->assertSame(0, UserNotification::where('type', 'counselor.name_changed')->count());
    }

    // ---- password and other devices ----------------------------------------------------------------------------------------

    public function test_changing_the_password_ends_the_apps_tokens_and_signs_out_other_sessions_in_one_audited_step(): void
    {
        config(['session.driver' => 'database']);
        $this->aina->user->createToken('phone');
        $this->aina->user->createToken('tablet');
        $other = User::factory()->create();
        $other->createToken('theirs');
        DB::table('sessions')->insert(['id' => 'old-laptop', 'user_id' => $this->aina->user_id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Chrome/1', 'payload' => 'x', 'last_activity' => now()->timestamp]);

        $this->as()->put(route('portal.account.security.password'), ['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertRedirect(route('portal.account.security'));

        $this->assertTrue(Hash::check(self::NEW, $this->aina->user->fresh()->password));
        $this->assertSame(0, $this->aina->user->tokens()->count(), 'the app is signed out too');
        $this->assertSame(1, $other->tokens()->count(), 'nobody else is touched');
        $this->assertDatabaseMissing('sessions', ['id' => 'old-laptop']);

        $entry = $this->logs('account.password_changed')->sole();
        $this->assertSame(2, $entry->metadata['api_tokens_ended']);
        $this->assertStringNotContainsString(self::NEW, json_encode($entry->getAttributes()));
        $this->assertStringNotContainsString(self::OLD, json_encode($entry->getAttributes()));
    }

    public function test_a_wrong_current_password_changes_nothing_and_ends_no_token(): void
    {
        $this->aina->user->createToken('phone');

        $this->as()->put(route('portal.account.security.password'), ['current_password' => 'nope', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check(self::OLD, $this->aina->user->fresh()->password));
        $this->assertSame(1, $this->aina->user->tokens()->count());
        $this->assertCount(0, $this->logs('account.password_changed'));
    }

    public function test_signing_out_other_devices_needs_the_password_and_also_ends_the_apps_tokens(): void
    {
        config(['session.driver' => 'database']);
        $this->aina->user->createToken('phone');
        DB::table('sessions')->insert(['id' => 'old-laptop', 'user_id' => $this->aina->user_id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Chrome/1', 'payload' => 'x', 'last_activity' => now()->timestamp]);

        $this->as()->post(route('portal.account.security.sessions.revoke'), ['current_password' => 'nope'])->assertSessionHasErrorsIn('revokeSessions', 'current_password');
        $this->assertSame(1, $this->aina->user->tokens()->count());
        $this->assertDatabaseHas('sessions', ['id' => 'old-laptop']);

        $this->as()->post(route('portal.account.security.sessions.revoke'), ['current_password' => self::OLD])
            ->assertRedirect(route('portal.account.security'))->assertSessionHas('status', 'Signed out 1 other session and the app.');

        $this->assertSame(0, $this->aina->user->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'old-laptop']);

        $entry = $this->logs('account.other_sessions_revoked')->sole();
        $this->assertSame([1, 1], [$entry->metadata['sessions'], $entry->metadata['api_tokens_ended']]);
    }

    public function test_a_suspended_counselor_can_still_reach_their_account_and_change_the_password(): void
    {
        $this->aina->forceFill(['verification_status' => \App\Enums\VerificationStatus::Suspended])->save();

        $this->as()->get(route('portal.account'))->assertOk();
        $this->as()->put(route('portal.account.security.password'), ['current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertRedirect(route('portal.account.security'));
    }
}
