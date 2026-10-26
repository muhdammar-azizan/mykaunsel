<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * `php artisan platform:reopen`: the way out of Registration Control that needs no browser and no sign-in.
 */
class ReopenPlatformCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        $this->admin = User::factory()->create(['name' => 'Ammar Azizan']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    private function settings(): PlatformSettings
    {
        return app(PlatformSettings::class);
    }

    /** The worst case: maintenance on and every sign-up closed. */
    private function lockEverything(): void
    {
        $this->settings()->setMaintenance($this->admin, true);
        $this->settings()->setRegistration($this->admin, $this->settings()->roles(), false, forAll: true);
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->orderBy('id')->get();
    }

    public function test_it_turns_maintenance_off_and_opens_every_sign_up_and_the_site_works_again(): void
    {
        $this->lockEverything();
        $this->get('/')->assertStatus(503);
        $this->get(route('register.type'))->assertStatus(503);

        $this->artisan('platform:reopen')
            ->expectsOutputToContain('Maintenance mode is now OFF')
            ->expectsOutputToContain('Organizations sign-up is now open')
            ->expectsOutputToContain('Counselors sign-up is now open')
            ->expectsOutputToContain('Individuals sign-up is now open')
            ->assertSuccessful();

        $settings = $this->settings();
        $this->assertFalse($settings->maintenanceOn());
        $this->assertSame(['organization' => true, 'counselor' => true, 'individual' => true], $settings->registrationStatus());

        // No browser, no sign-in, nothing to wait for: visitors are served on their very next request.
        $this->get('/')->assertOk();
        $this->get(route('register.type'))->assertOk();
    }

    public function test_every_change_is_a_system_action_in_the_audit_log_marked_as_done_by_the_command(): void
    {
        $this->lockEverything();

        $this->artisan('platform:reopen')->assertSuccessful();

        $maintenance = $this->logs('settings.maintenance_toggled');
        $this->assertCount(2, $maintenance, 'the admin turning it on, then the command turning it off');
        $off = $maintenance->last();
        $this->assertSame(['system', null, 'command'], [$off->actor_type, $off->actor_user_id, $off->metadata['via']]);
        $this->assertSame(['old' => true, 'new' => false], $off->metadata['changes']['maintenance']);

        $registration = $this->logs('settings.registration_toggled');
        $this->assertCount(6, $registration, 'three closed by the admin, three opened by the command');
        foreach ($registration->slice(3) as $entry) {
            $this->assertSame(['system', null, 'command', ['old' => false, 'new' => true]], [$entry->actor_type, $entry->actor_user_id, $entry->metadata['via'], $entry->metadata['changes']['open']]);
        }
        $this->assertSame(['organization', 'counselor', 'individual'], $registration->slice(3)->pluck('metadata.role')->values()->all());

        // The admin's own entries are still the admin's.
        $this->assertSame($this->admin->id, $maintenance->first()->actor_user_id);
        $this->assertNull(PlatformSetting::find('maintenance')->updated_by);
    }

    public function test_running_it_again_changes_and_logs_nothing(): void
    {
        $this->lockEverything();
        $this->artisan('platform:reopen')->assertSuccessful();
        $entries = AuditLog::count();

        $this->artisan('platform:reopen')->expectsOutputToContain('Nothing to change')->assertSuccessful();

        $this->assertSame($entries, AuditLog::count());
    }

    public function test_it_does_nothing_when_nothing_is_wrong(): void
    {
        $this->artisan('platform:reopen')->expectsOutputToContain('Nothing to change')->assertSuccessful();

        $this->assertSame(0, AuditLog::whereIn('action', ['settings.maintenance_toggled', 'settings.registration_toggled'])->count());
    }

    public function test_only_maintenance_leaves_the_closed_sign_ups_alone(): void
    {
        $this->lockEverything();

        $this->artisan('platform:reopen', ['--maintenance' => true])->expectsOutputToContain('Maintenance mode is now OFF')->assertSuccessful();

        $this->assertFalse($this->settings()->maintenanceOn());
        $this->assertFalse($this->settings()->anyRegistrationOpen());
    }

    public function test_only_registration_leaves_maintenance_on(): void
    {
        $this->lockEverything();

        $this->artisan('platform:reopen', ['--registration' => true])->expectsOutputToContain('Individuals sign-up is now open')->assertSuccessful();

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(['organization' => true, 'counselor' => true, 'individual' => true], $this->settings()->registrationStatus());
    }

    public function test_a_dry_run_shows_the_state_and_changes_nothing(): void
    {
        $this->lockEverything();
        $entries = AuditLog::count();

        $this->artisan('platform:reopen', ['--dry-run' => true])
            ->expectsOutputToContain('Maintenance mode: ON')
            ->expectsOutputToContain('closed -> will be opened')
            ->expectsOutputToContain('Dry run: nothing was changed')
            ->assertSuccessful();

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertFalse($this->settings()->anyRegistrationOpen());
        $this->assertSame($entries, AuditLog::count());
    }

    public function test_the_wording_of_the_messages_is_never_touched(): void
    {
        $this->settings()->updateMessages($this->admin, ['registration_closed' => 'We are full until January.', 'maintenance' => 'Upgrading the database tonight.']);
        $this->lockEverything();

        $this->artisan('platform:reopen')->assertSuccessful();

        $this->assertSame('We are full until January.', $this->settings()->message('registration_closed'));
        $this->assertSame('Upgrading the database tonight.', $this->settings()->message('maintenance'));
    }

    public function test_it_is_all_or_nothing_if_the_audit_entry_cannot_be_written(): void
    {
        $this->lockEverything();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->app->forgetInstance(PlatformSettings::class);

        $this->artisan('platform:reopen')->expectsOutputToContain('Nothing could be changed')->assertFailed();

        $this->app->forgetInstance(PlatformSettings::class);
        $this->assertTrue($this->settings()->maintenanceOn(), 'still on: not half-reopened');
        $this->assertFalse($this->settings()->anyRegistrationOpen());
    }

    public function test_it_is_a_console_command_only_and_is_not_scheduled(): void
    {
        $this->artisan('schedule:list')->doesntExpectOutputToContain('platform:reopen')->assertSuccessful();
        $this->assertArrayHasKey('platform:reopen', \Illuminate\Support\Facades\Artisan::all());
    }
}
