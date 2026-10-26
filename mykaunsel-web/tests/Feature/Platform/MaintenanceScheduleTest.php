<?php

namespace Tests\Feature\Platform;

use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Services\PlatformSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Maintenance mode with a reopen time: setting it (in the admin's own time zone), what visitors are told, the scheduler that
 * switches maintenance off when the time arrives, and that every change of Registration Control is all-or-nothing with its audit entry.
 *
 * The clock is fixed at 2026-10-27 10:00 UTC, which is 18:00 in Kuala Lumpur (UTC+8) and 06:00 in New York (UTC-4).
 */
class MaintenanceScheduleTest extends CounselorTestCase
{
    private const KL_EVENING = '2026-10-27T20:00'; // 12:00 UTC, two hours from "now"

    private const KL_EVENING_UTC = '2026-10-27T12:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
    }

    private function settings(): PlatformSettings
    {
        return app(PlatformSettings::class);
    }

    private function stored(): string
    {
        return (string) PlatformSetting::findOrFail('maintenance.reopen_at')->value;
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->orderBy('id')->get();
    }

    private function maintenanceOn(?string $reopenAt = null)
    {
        return $this->as()->post(route('platform.registration.maintenance'), ['state' => 'on'] + ($reopenAt !== null ? ['reopen_at' => $reopenAt] : []));
    }

    private function setTime(string $value)
    {
        return $this->as()->put(route('platform.registration.maintenance.reopen'), ['reopen_at' => $value]);
    }

    private function visitor(): void
    {
        $this->flushSession();
        auth()->logout();
    }

    // ---- setting the time -----------------------------------------------------------------------

    public function test_turning_maintenance_on_with_a_time_keeps_it_as_an_instant_read_in_the_admins_time_zone(): void
    {
        $this->maintenanceOn(self::KL_EVENING)->assertSessionHas('status', fn ($m) => str_contains($m, 'switches off by itself at 27 Oct 2026, 8:00 PM'));

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(self::KL_EVENING_UTC, $this->stored(), '20:00 in Kuala Lumpur is 12:00 UTC');
        $this->assertTrue($this->settings()->maintenanceReopenAt()->equalTo(Carbon::parse('2026-10-27 12:00:00', 'UTC')));

        $log = $this->logs('settings.maintenance_toggled')->sole();
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame(['old' => null, 'new' => self::KL_EVENING_UTC], $log->metadata['changes']['reopen_at']);
        $this->assertSame(['old' => false, 'new' => true], $log->metadata['changes']['maintenance']);
    }

    public function test_the_same_wall_clock_time_means_a_different_instant_in_another_time_zone(): void
    {
        $this->admin->update(['timezone' => 'America/New_York']);

        $this->maintenanceOn('2026-10-27T10:00')->assertSessionHas('status', fn ($m) => str_contains($m, '10:00 AM'));

        $this->assertSame('2026-10-27T14:00:00Z', $this->stored(), '10:00 in New York (UTC-4) is 14:00 UTC');
        $this->as()->get(route('platform.registration'))->assertSee('America/New_York, UTC-04:00')->assertSee('27 Oct 2026, 10:00 AM');
    }

    public function test_maintenance_can_be_turned_on_without_a_time_and_then_stays_until_turned_off(): void
    {
        $this->maintenanceOn()->assertSessionHas('status');
        $this->maintenanceOn('')->assertSessionHas('error', fn ($m) => str_contains($m, 'already on'));

        $this->assertSame('', $this->stored());
        $this->assertNull($this->settings()->maintenanceReopenAt());
        $this->assertArrayNotHasKey('reopen_at', $this->logs('settings.maintenance_toggled')->sole()->metadata['changes']);

        $this->as()->get(route('platform.registration'))->assertSee('No reopen time set');
    }

    public function test_a_bad_time_is_refused_and_does_not_turn_maintenance_on(): void
    {
        foreach ([
            'yesterday' => ['2026-10-26T20:00', 'at least a minute from now'],
            'now' => ['2026-10-27T18:00', 'at least a minute from now'],
            'half a minute away' => ['2026-10-27T18:00', 'at least a minute'],
            'too far ahead' => ['2026-12-31T20:00', 'no more than 30 days'],
            'not a time' => ['tomorrow evening', 'date and time picker'],
            'a date only' => ['2026-10-28', 'date and time picker'],
        ] as [$value, $message]) {
            $this->maintenanceOn($value)->assertSessionHasErrorsIn('maintenance', 'reopen_at');
            $this->assertStringContainsString($message, session('errors')->getBag('maintenance')->first('reopen_at'));
        }

        $this->assertFalse($this->settings()->maintenanceOn());
        $this->assertSame(0, $this->logs('settings.maintenance_toggled')->count());
        $this->assertSame('', $this->stored());
    }

    public function test_the_limits_are_a_minute_and_thirty_days(): void
    {
        $this->maintenanceOn('2026-10-27T18:02')->assertSessionHasNoErrors(); // two minutes from now
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off']);

        $this->maintenanceOn('2026-11-26T18:00')->assertSessionHasNoErrors(); // exactly 30 days
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off']);

        $this->maintenanceOn('2026-11-26T18:01')->assertSessionHasErrorsIn('maintenance', 'reopen_at');
    }

    public function test_the_time_can_be_set_changed_and_removed_while_maintenance_is_on(): void
    {
        $this->maintenanceOn();

        $this->setTime(self::KL_EVENING)->assertSessionHas('status', fn ($m) => str_contains($m, 'switches off by itself at 27 Oct 2026, 8:00 PM'));
        $this->assertSame(self::KL_EVENING_UTC, $this->stored());

        $this->setTime('2026-10-27T22:30')->assertSessionHas('status');
        $this->assertSame('2026-10-27T14:30:00Z', $this->stored());

        $this->setTime('')->assertSessionHas('status', fn ($m) => str_contains($m, 'Reopen time removed') && str_contains($m, 'stays on'));
        $this->assertSame('', $this->stored());
        $this->assertTrue($this->settings()->maintenanceOn(), 'removing the time does not turn maintenance off');

        $entries = $this->logs('settings.maintenance_reopen_scheduled');
        $this->assertSame(['Reopen time set', 'Reopen time changed', 'Reopen time removed'], $entries->pluck('metadata.summary')->all());
        $this->assertSame(['old' => null, 'new' => self::KL_EVENING_UTC], $entries[0]->metadata['changes']['reopen_at']);
        $this->assertSame(['old' => self::KL_EVENING_UTC, 'new' => '2026-10-27T14:30:00Z'], $entries[1]->metadata['changes']['reopen_at']);
        $this->assertSame(['old' => '2026-10-27T14:30:00Z', 'new' => null], $entries[2]->metadata['changes']['reopen_at']);
        $this->assertSame([$this->admin->id], $entries->pluck('actor_user_id')->unique()->all());
    }

    public function test_the_same_time_again_changes_and_logs_nothing_and_it_needs_maintenance_to_be_on(): void
    {
        $this->setTime(self::KL_EVENING)->assertSessionHas('error', fn ($m) => str_contains($m, 'is off'));
        $this->assertSame('', $this->stored());

        $this->maintenanceOn(self::KL_EVENING);
        $this->setTime(self::KL_EVENING)->assertSessionHas('error', fn ($m) => str_contains($m, 'Nothing was changed'));

        $this->assertSame(0, $this->logs('settings.maintenance_reopen_scheduled')->count());
    }

    public function test_a_bad_time_when_changing_it_leaves_the_old_one(): void
    {
        $this->maintenanceOn(self::KL_EVENING);

        $this->setTime('2026-10-27T17:00')->assertSessionHasErrorsIn('maintenance', 'reopen_at');
        $this->assertSame(self::KL_EVENING_UTC, $this->stored());
    }

    public function test_turning_maintenance_off_by_hand_forgets_the_time(): void
    {
        $this->maintenanceOn(self::KL_EVENING);

        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertSessionHas('status');

        $this->assertSame('', $this->stored());
        $off = $this->logs('settings.maintenance_toggled')->last();
        $this->assertSame(['old' => self::KL_EVENING_UTC, 'new' => null], $off->metadata['changes']['reopen_at']);

        // Turned on again later, with no time: the old time does not come back.
        $this->maintenanceOn();
        $this->assertNull($this->settings()->maintenanceReopenAt());
    }

    public function test_the_emergency_command_forgets_the_time_too(): void
    {
        $this->maintenanceOn(self::KL_EVENING);

        $this->artisan('platform:reopen')->assertSuccessful();

        $this->assertSame('', $this->stored());
        $this->assertSame(['old' => self::KL_EVENING_UTC, 'new' => null], $this->logs('settings.maintenance_toggled')->last()->metadata['changes']['reopen_at']);
    }

    // ---- what is shown ------------------------------------------------------------------------------

    public function test_the_page_shows_the_time_with_its_zone_and_a_bar_on_every_platform_page(): void
    {
        $this->maintenanceOn(self::KL_EVENING);

        $this->as()->get(route('platform.registration'))->assertOk()
            ->assertSee('Switches off by itself at 27 Oct 2026, 8:00 PM')->assertSee('Asia/Kuala_Lumpur, UTC+08:00')->assertSee('2 hours from now')
            ->assertSee('Remove the reopen time')->assertDontSee('may not be running');

        $this->as()->get(route('platform.dashboard'))->assertSee('Maintenance mode is ON')->assertSee('Switches off by itself 27 Oct, 8:00 PM');
    }

    public function test_a_time_that_has_passed_without_the_scheduler_is_flagged_to_the_admin(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:30:00', 'UTC'));

        $this->assertTrue($this->settings()->reopenOverdue());
        $this->as()->get(route('platform.registration'))->assertSee('that time has passed')->assertSee('may not be running')->assertSee('schedule:run');
    }

    public function test_visitors_are_told_when_to_come_back_and_search_engines_are_told_to_stay_away(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->visitor();

        $response = $this->get('/')->assertStatus(503);
        $this->assertSame('7200', $response->headers->get('Retry-After'), 'two hours, in seconds');
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $response->assertSee('We expect to be back around 27 Oct 2026, 8:00 PM (Asia/Kuala_Lumpur, UTC+08:00).')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        // Later, the number gets smaller.
        Carbon::setTestNow(Carbon::parse('2026-10-27 11:30:00', 'UTC'));
        $this->assertSame('1800', $this->get('/')->headers->get('Retry-After'));
    }

    public function test_without_a_time_visitors_get_the_default_and_no_promise(): void
    {
        $this->maintenanceOn();
        $this->visitor();

        $response = $this->get('/')->assertStatus(503)->assertDontSee('We expect to be back');
        $this->assertSame('600', $response->headers->get('Retry-After'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_a_time_that_has_passed_is_not_promised_and_asks_for_a_retry_in_a_minute(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->visitor();
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:30:00', 'UTC'));

        $response = $this->get('/')->assertStatus(503)->assertDontSee('We expect to be back');
        $this->assertSame('60', $response->headers->get('Retry-After'));
    }

    public function test_the_json_answer_has_the_message_the_time_and_the_same_headers(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->visitor();

        $response = $this->getJson('/')->assertStatus(503)->assertJsonPath('reopens_at', self::KL_EVENING_UTC);
        $this->assertSame('7200', $response->headers->get('Retry-After'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x', 'device_name' => 'phone'])
            ->assertStatus(503)->assertJsonPath('reopens_at', self::KL_EVENING_UTC)->assertHeader('Retry-After', '7200');
    }

    public function test_the_apps_status_call_includes_the_time(): void
    {
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('maintenance.on', false)->assertJsonPath('maintenance.reopens_at', null);

        $this->maintenanceOn(self::KL_EVENING);
        $this->visitor();
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('maintenance.on', true)->assertJsonPath('maintenance.reopens_at', self::KL_EVENING_UTC);

        Carbon::setTestNow(Carbon::parse('2026-10-27 12:30:00', 'UTC'));
        $this->getJson('/api/v1/status')->assertJsonPath('maintenance.reopens_at', null);
    }

    public function test_the_maintenance_message_is_shown_as_plain_text_never_as_html(): void
    {
        $this->as()->put(route('platform.registration.messages'), ['registration_closed' => 'Closed for now, sorry.', 'maintenance' => '<script>alert(1)</script> <b>Back soon</b>'])->assertSessionHas('status');
        $this->maintenanceOn();
        $this->visitor();

        $this->get('/')->assertStatus(503)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; &lt;b&gt;Back soon&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>Back soon</b>', false);
    }

    public function test_the_closed_sign_up_page_is_not_indexed_either(): void
    {
        $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'closed']);
        $this->visitor();

        $this->get(route('organizations.signup.create'))->assertForbidden()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // ---- the scheduler ------------------------------------------------------------------------------

    public function test_the_task_is_registered_to_run_every_minute(): void
    {
        Artisan::call('schedule:list');

        $this->assertMatchesRegularExpression('/\*\s+\*\s+\*\s+\*\s+\*\s+php artisan maintenance:reopen-due/', Artisan::output());
    }

    public function test_it_does_nothing_before_the_time(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        Carbon::setTestNow(Carbon::parse('2026-10-27 11:59:59', 'UTC'));

        $this->artisan('maintenance:reopen-due')->doesntExpectOutputToContain('switched off')->assertSuccessful();

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(self::KL_EVENING_UTC, $this->stored());
        $this->assertSame(0, $this->logs('settings.maintenance_auto_reopened')->count());
    }

    public function test_it_switches_maintenance_off_at_the_time_as_the_system_and_visitors_are_served_again(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->visitor();
        $this->get('/')->assertStatus(503);

        Carbon::setTestNow(Carbon::parse(self::KL_EVENING_UTC)); // exactly the moment

        $this->artisan('maintenance:reopen-due')->expectsOutputToContain('switched off at its scheduled time')->assertSuccessful();

        $this->assertFalse($this->settings()->maintenanceOn());
        $this->assertSame('', $this->stored());
        $this->get('/')->assertOk();

        $log = $this->logs('settings.maintenance_auto_reopened')->sole();
        $this->assertSame(['system', null], [$log->actor_type, $log->actor_user_id]);
        $this->assertSame(['old' => true, 'new' => false], $log->metadata['changes']['maintenance']);
        $this->assertSame(['old' => self::KL_EVENING_UTC, 'new' => null], $log->metadata['changes']['reopen_at']);
        $this->assertNull(PlatformSetting::findOrFail('maintenance')->updated_by);
    }

    public function test_running_it_again_and_again_acts_only_once(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:00:30', 'UTC'));

        foreach (range(1, 4) as $_) {
            $this->artisan('maintenance:reopen-due')->assertSuccessful();
        }

        $this->assertSame(1, $this->logs('settings.maintenance_auto_reopened')->count());
        $this->assertFalse($this->settings()->maintenanceOn());
    }

    public function test_it_leaves_alone_maintenance_that_has_no_time_and_a_platform_that_is_not_in_maintenance(): void
    {
        $this->artisan('maintenance:reopen-due')->assertSuccessful(); // not in maintenance

        $this->maintenanceOn();
        Carbon::setTestNow(Carbon::parse('2027-01-01 00:00:00', 'UTC'));
        $this->artisan('maintenance:reopen-due')->assertSuccessful(); // on, but no time

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(0, $this->logs('settings.maintenance_auto_reopened')->count());
    }

    public function test_a_time_the_admin_removed_or_moved_is_respected(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->setTime('');
        Carbon::setTestNow(Carbon::parse('2026-10-27 13:00:00', 'UTC'));
        $this->artisan('maintenance:reopen-due')->assertSuccessful();
        $this->assertTrue($this->settings()->maintenanceOn(), 'the time was removed: it stays on');

        $this->setTime('2026-10-27T23:00'); // 15:00 UTC
        Carbon::setTestNow(Carbon::parse('2026-10-27 14:00:00', 'UTC'));
        $this->artisan('maintenance:reopen-due')->assertSuccessful();
        $this->assertTrue($this->settings()->maintenanceOn(), 'the time was moved later: not yet');

        Carbon::setTestNow(Carbon::parse('2026-10-27 15:00:00', 'UTC'));
        $this->artisan('maintenance:reopen-due')->assertSuccessful();
        $this->assertFalse($this->settings()->maintenanceOn());
    }

    public function test_turned_off_and_on_again_by_hand_the_old_time_does_not_fire(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off']);
        $this->maintenanceOn(); // a new maintenance, with no time
        Carbon::setTestNow(Carbon::parse('2026-10-27 13:00:00', 'UTC'));

        $this->artisan('maintenance:reopen-due')->assertSuccessful();

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(0, $this->logs('settings.maintenance_auto_reopened')->count());
    }

    public function test_a_damaged_time_never_closes_or_opens_anything_by_accident(): void
    {
        $this->maintenanceOn();
        PlatformSetting::findOrFail('maintenance.reopen_at')->update(['value' => 'not a time']);
        $this->app->forgetInstance(PlatformSettings::class);
        Carbon::setTestNow(Carbon::parse('2027-01-01 00:00:00', 'UTC'));

        $this->assertNull($this->settings()->maintenanceReopenAt());
        $this->artisan('maintenance:reopen-due')->assertSuccessful();

        $this->assertTrue($this->settings()->maintenanceOn());
        $this->visitor();
        $this->get('/')->assertStatus(503)->assertHeader('Retry-After', '600');
    }

    public function test_the_task_is_all_or_nothing_if_the_audit_entry_fails(): void
    {
        $this->maintenanceOn(self::KL_EVENING);
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:05:00', 'UTC'));

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->app->forgetInstance(PlatformSettings::class);

        try {
            $this->artisan('maintenance:reopen-due');
            $this->fail('the task should have failed');
        } catch (RuntimeException) {
        }

        $this->app->forgetInstance(PlatformSettings::class);
        $this->assertTrue($this->settings()->maintenanceOn(), 'still on, and the time is kept so the next minute tries again');
        $this->assertSame(self::KL_EVENING_UTC, $this->stored());
    }

    // ---- all-or-nothing, for every change on the page ------------------------------------------------------

    /** Runs $act with an audit log that cannot be written: nothing about the settings may change, and nothing may be logged. */
    private function assertNothingSavedWithoutItsAuditEntry(string $what, callable $act): void
    {
        $state = fn () => [$this->settings()->registrationStatus(), $this->settings()->maintenanceOn(), $this->settings()->message('registration_closed'), $this->settings()->message('maintenance'), $this->stored()];
        $before = $state();
        $entries = AuditLog::count();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $act();
            $this->fail("{$what} should have failed");
        } catch (RuntimeException) {
        }

        $this->app->forgetInstance(PlatformSettings::class);

        $this->assertSame($before, $state(), "{$what}: nothing may be saved without its audit entry");
        $this->assertSame($entries, AuditLog::count());
    }

    public function test_closing_one_sign_up_is_all_or_nothing_with_its_audit_entry(): void
    {
        $this->assertNothingSavedWithoutItsAuditEntry('closing one sign-up', fn () => $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'closed']));
    }

    public function test_closing_every_sign_up_is_all_or_nothing_with_its_audit_entries(): void
    {
        $this->assertNothingSavedWithoutItsAuditEntry('closing every sign-up', fn () => $this->as()->post(route('platform.registration.all'), ['state' => 'closed']));
    }

    public function test_turning_maintenance_on_with_a_time_is_all_or_nothing_with_its_audit_entry(): void
    {
        $this->assertNothingSavedWithoutItsAuditEntry('turning maintenance on', fn () => $this->maintenanceOn(self::KL_EVENING));
    }

    public function test_turning_maintenance_off_is_all_or_nothing_with_its_audit_entry(): void
    {
        $this->maintenanceOn(self::KL_EVENING);

        $this->assertNothingSavedWithoutItsAuditEntry('turning maintenance off', fn () => $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off']));
        $this->assertTrue($this->settings()->maintenanceOn());
        $this->assertSame(self::KL_EVENING_UTC, $this->stored());
    }

    public function test_saving_the_wording_is_all_or_nothing_with_its_audit_entry(): void
    {
        $this->assertNothingSavedWithoutItsAuditEntry('saving the wording', fn () => $this->as()->put(route('platform.registration.messages'), ['registration_closed' => 'Closed until further notice.', 'maintenance' => 'Back in a little while.']));
    }

    public function test_the_reopen_time_is_all_or_nothing_with_its_audit_entry(): void
    {
        $this->maintenanceOn();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->setTime(self::KL_EVENING);
            $this->fail('should have failed');
        } catch (RuntimeException) {
        }

        $this->app->forgetInstance(PlatformSettings::class);
        $this->assertSame('', $this->stored());
        $this->assertNull($this->settings()->maintenanceReopenAt());
    }

    // ---- access ----------------------------------------------------------------------------------------------

    public function test_only_platform_admins_can_change_the_time(): void
    {
        $orgAdmin = \App\Models\User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = \App\Models\Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $session = ['current_org_id' => $umpsa->id, 'current_role' => 'org_admin'];

        // While maintenance is on they are stopped by it (503); once it is off by the platform check (403). Either way the time is untouched.
        $this->maintenanceOn();
        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($session)->put(route('platform.registration.maintenance.reopen'), ['reopen_at' => self::KL_EVENING])->assertStatus(503);

        $this->flushSession();
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertSessionHas('status');

        $this->flushSession();
        $this->actingAs($orgAdmin)->withSession($session)->put(route('platform.registration.maintenance.reopen'), ['reopen_at' => self::KL_EVENING])->assertForbidden();
        $this->assertSame('', $this->stored());
    }
}
