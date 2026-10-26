<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\PlatformSettings;
use Tests\Feature\Counselor\CounselorTestCase;

class RegistrationControlTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function settings(): PlatformSettings
    {
        // A fresh instance: the shared one remembers values for the rest of the request.
        return new PlatformSettings;
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    private function close(string $role): void
    {
        $this->as()->post(route('platform.registration.toggle', $role), ['state' => 'closed'])->assertSessionHas('status');
    }

    private function counselorSession(): array
    {
        $membership = Membership::where('user_id', $this->profile('KB10234')->user_id)->where('role', 'counselor')->firstOrFail();

        return [$membership->user, ['current_org_id' => $membership->org_id, 'current_role' => 'counselor']];
    }

    // ---- access ------------------------------------------------------------------------------

    public function test_only_platform_admins_can_use_registration_control(): void
    {
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        foreach ([
            fn ($t) => $t->get(route('platform.registration')),
            fn ($t) => $t->post(route('platform.registration.toggle', 'organization'), ['state' => 'closed']),
            fn ($t) => $t->post(route('platform.registration.all'), ['state' => 'closed']),
            fn ($t) => $t->post(route('platform.registration.maintenance'), ['state' => 'on']),
            fn ($t) => $t->put(route('platform.registration.messages'), ['registration_closed' => 'Closed for now, sorry.', 'maintenance' => 'Back soon, thank you.']),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->assertTrue($this->settings()->registrationOpen('organization'));
        $this->assertFalse($this->settings()->maintenanceOn());
    }

    public function test_the_page_shows_everything_open_by_default_and_is_in_the_menu(): void
    {
        $this->as()->get(route('platform.registration'))->assertOk()
            ->assertSee('Organizations')->assertSee('Counselors')->assertSee('Individuals')
            ->assertSee('Close all sign-ups')->assertDontSee('Open all sign-ups')
            ->assertSee('Turn maintenance on');

        $this->as()->get(route('platform.dashboard'))->assertSee(route('platform.registration'), false);
    }

    // ---- sign-ups ----------------------------------------------------------------------------

    public function test_closing_one_kind_blocks_only_that_kind_and_is_audited(): void
    {
        $this->close('organization');

        $this->assertFalse($this->settings()->registrationOpen('organization'));
        $this->assertTrue($this->settings()->registrationOpen('counselor'));

        $log = $this->logs('settings.registration_toggled')->sole();
        $this->assertSame('organization', $log->metadata['role']);
        $this->assertSame(['old' => true, 'new' => false], $log->metadata['changes']['open']);
        $this->assertSame($this->admin->id, (int) $log->actor_user_id);

        $this->flushSession();
        auth()->logout();
        $this->get(route('organizations.signup.create'))->assertForbidden()->assertSee('not accepting new organization sign-ups');
        $this->post(route('organizations.signup.store'), [])->assertForbidden();
        $this->get(route('counselors.signup.create'))->assertOk();
    }

    public function test_individual_sign_up_can_be_closed_too(): void
    {
        $this->close('individual');

        $this->flushSession();
        auth()->logout();
        $this->get(route('register'))->assertForbidden()->assertSee('not accepting new individual sign-ups');
        $this->post(route('register'), ['name' => 'A B', 'email' => 'ab@example.com', 'password' => 'Str0ng-Pass-123', 'password_confirmation' => 'Str0ng-Pass-123'])->assertForbidden();
        $this->assertNull(User::where('email', 'ab@example.com')->first());
    }

    public function test_closing_the_counselor_sign_up_blocks_it(): void
    {
        $this->close('counselor');

        $this->flushSession();
        auth()->logout();
        $this->get(route('counselors.signup.create'))->assertForbidden();
        $this->post(route('counselors.signup.store'), [])->assertForbidden();
    }

    public function test_a_sign_up_already_in_progress_is_not_blocked(): void
    {
        $this->as()->post(route('platform.registration.all'), ['state' => 'closed']);

        foreach (['counselors.signup.documents', 'counselors.signup.waiting-approval', 'organizations.signup.waiting-approval', 'organizations.signup.verify-domain'] as $name) {
            $this->assertNotContains('registration.open:counselor', \Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
            $this->assertNotContains('registration.open:organization', \Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_closing_something_already_closed_changes_nothing(): void
    {
        $this->close('organization');

        $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'closed'])->assertSessionHas('error');

        $this->assertCount(1, $this->logs('settings.registration_toggled'));
    }

    public function test_opening_again_works(): void
    {
        $this->close('organization');
        $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'open'])->assertSessionHas('status');

        $this->assertTrue($this->settings()->registrationOpen('organization'));
        $this->assertCount(2, $this->logs('settings.registration_toggled'));
    }

    public function test_close_all_logs_only_what_changed_and_open_all_restores(): void
    {
        $this->close('counselor');
        $this->as()->post(route('platform.registration.all'), ['state' => 'closed'])->assertSessionHas('status');

        // counselor was closed already: 1 (earlier) + organization + individual.
        $this->assertCount(3, $this->logs('settings.registration_toggled'));
        $this->assertFalse($this->settings()->anyRegistrationOpen());

        $this->as()->post(route('platform.registration.all'), ['state' => 'closed'])->assertSessionHas('error');
        $this->assertCount(3, $this->logs('settings.registration_toggled'));

        $this->as()->post(route('platform.registration.all'), ['state' => 'open'])->assertSessionHas('status');
        $this->assertSame(['organization' => true, 'counselor' => true, 'individual' => true], $this->settings()->registrationStatus());
        $this->assertCount(6, $this->logs('settings.registration_toggled'));
    }

    public function test_unknown_role_and_state_are_refused(): void
    {
        $this->as()->post(route('platform.registration.toggle', 'banana'), ['state' => 'closed'])->assertNotFound();
        $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'maybe'])->assertSessionHasErrors('state');
        $this->as()->post(route('platform.registration.toggle', 'organization'), [])->assertSessionHasErrors('state');

        $this->assertCount(0, $this->logs('settings.registration_toggled'));
    }

    public function test_the_account_type_page_shows_the_closed_message_and_disables_the_card(): void
    {
        $this->close('organization');

        $this->flushSession();
        auth()->logout();
        $this->get(route('register.type'))->assertOk()
            ->assertSee('not accepting new organization sign-ups')
            ->assertSee('disabled', false);

        $this->close('counselor');
        $this->flushSession();
        auth()->logout();
        $this->get(route('register.type'))->assertForbidden()->assertSee('not accepting new account sign-ups');
    }

    // ---- maintenance -------------------------------------------------------------------------

    private function maintenanceOn(): void
    {
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'on'])->assertSessionHas('status');
    }

    public function test_maintenance_blocks_everyone_except_signed_in_platform_admins(): void
    {
        $this->maintenanceOn();
        $this->assertCount(1, $this->logs('settings.maintenance_toggled'));

        // Visitors.
        $this->flushSession();
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('down for maintenance')->assertHeader('Retry-After');
        $this->get(route('register.type'))->assertStatus(503);
        $this->get(route('organizations.signup.create'))->assertStatus(503);

        // A signed-in client of the platform (a counselor).
        [$counselor, $session] = $this->counselorSession();
        $this->actingAs($counselor)->withSession($session)->get(route('counselor.dashboard'))->assertStatus(503);

        // Platform Admins carry on.
        $this->flushSession();
        $this->as()->get(route('platform.dashboard'))->assertOk()->assertSee('Maintenance mode is ON');
    }

    public function test_the_sign_in_pages_stay_open_during_maintenance(): void
    {
        $this->maintenanceOn();
        $this->flushSession();
        auth()->logout();

        foreach (['login', 'password.request'] as $name) {
            $this->get(route($name))->assertOk();
        }
        $this->get(route('password.reset', 'sometoken'))->assertOk();
    }

    public function test_a_platform_admin_can_switch_maintenance_off_again(): void
    {
        $this->maintenanceOn();

        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertSessionHas('status');

        $this->assertFalse($this->settings()->maintenanceOn());
        $this->assertCount(2, $this->logs('settings.maintenance_toggled'));

        $this->flushSession();
        auth()->logout();
        $this->get('/')->assertOk();
    }

    public function test_other_roles_cannot_switch_maintenance_off(): void
    {
        $this->maintenanceOn();
        [$counselor, $session] = $this->counselorSession();

        $this->flushSession();
        $this->actingAs($counselor)->withSession($session)->post(route('platform.registration.maintenance'), ['state' => 'off'])->assertStatus(503);

        $this->assertTrue($this->settings()->maintenanceOn());
    }

    public function test_maintenance_answers_api_style_requests_with_json(): void
    {
        $this->maintenanceOn();
        $this->flushSession();
        auth()->logout();

        $this->getJson('/')->assertStatus(503)->assertJsonStructure(['message']);
    }

    public function test_unknown_urls_are_still_a_normal_404_during_maintenance(): void
    {
        $this->maintenanceOn();
        $this->flushSession();
        auth()->logout();

        $this->get('/this/does/not/exist')->assertNotFound();
    }

    public function test_turning_maintenance_on_twice_changes_nothing(): void
    {
        $this->maintenanceOn();
        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'on'])->assertSessionHas('error');

        $this->assertCount(1, $this->logs('settings.maintenance_toggled'));
    }

    // ---- messages ----------------------------------------------------------------------------

    public function test_messages_are_saved_shown_and_audited_without_their_text(): void
    {
        $this->as()->put(route('platform.registration.messages'), [
            'registration_closed' => 'Sorry, {role} sign-ups are paused until January.',
            'maintenance' => 'Upgrading our servers. Back at 6 PM.',
        ])->assertSessionHas('status');

        $logs = $this->logs('settings.message_updated');
        $this->assertCount(2, $logs);
        $this->assertStringNotContainsString('January', json_encode($logs->pluck('metadata')));

        $this->close('counselor');
        $this->flushSession();
        auth()->logout();
        $this->get(route('counselors.signup.create'))->assertForbidden()->assertSee('Sorry, counselor sign-ups are paused until January.');

        $this->as()->post(route('platform.registration.maintenance'), ['state' => 'on']);
        $this->flushSession();
        auth()->logout();
        $this->get('/')->assertStatus(503)->assertSee('Upgrading our servers. Back at 6 PM.');
    }

    public function test_saving_unchanged_messages_logs_nothing(): void
    {
        $page = $this->as()->get(route('platform.registration'));
        $this->as()->put(route('platform.registration.messages'), [
            'registration_closed' => config('platform-settings.default_messages.registration_closed'),
            'maintenance' => config('platform-settings.default_messages.maintenance'),
        ])->assertSessionHas('status');

        $this->assertCount(0, $this->logs('settings.message_updated'));
    }

    public function test_messages_are_validated(): void
    {
        $this->as()->put(route('platform.registration.messages'), ['registration_closed' => '', 'maintenance' => 'short'])
            ->assertSessionHasErrorsIn('messages', ['registration_closed', 'maintenance']);
        $this->as()->put(route('platform.registration.messages'), ['registration_closed' => str_repeat('a', 501), 'maintenance' => 'Fine message here.'])
            ->assertSessionHasErrorsIn('messages', 'registration_closed');

        $this->assertCount(0, $this->logs('settings.message_updated'));
    }

    public function test_message_text_is_shown_as_plain_text(): void
    {
        $this->as()->put(route('platform.registration.messages'), ['registration_closed' => '<script>alert(1)</script> closed', 'maintenance' => 'Back soon, thank you.']);
        $this->close('organization');

        $this->flushSession();
        auth()->logout();
        $this->get(route('organizations.signup.create'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; closed', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_wording_form_warns_about_unsaved_changes_in_words_and_the_page_has_a_keyboard_focus_ring(): void
    {
        $html = $this->as()->get(route('platform.registration'))->assertOk()->getContent();

        $this->assertStringContainsString('Unsaved changes', $html);
        $this->assertStringContainsString('@beforeunload.window', $html);
        $this->assertStringContainsString('get dirty()', $html);
        $this->assertStringContainsString(':focus-visible', $html);
        $this->assertStringContainsString('reg-focus', $html);
    }

    public function test_the_individuals_hint_says_they_sign_up_in_the_app(): void
    {
        $this->as()->get(route('platform.registration'))->assertOk()->assertSee('They sign up in the mobile app')->assertSee('web sign-up form is switched off');
    }

    // ---- the role chosen for the session -----------------------------------------------------

    public function test_a_role_the_user_does_not_have_cannot_be_chosen(): void
    {
        [$counselor, $session] = $this->counselorSession();

        $this->actingAs($counselor)->post(route('context.store'), ['org_id' => $session['current_org_id'], 'role' => 'platform_admin'])->assertForbidden();
        $this->actingAs($counselor)->post(route('context.store'), ['org_id' => $session['current_org_id'], 'role' => 'org_admin'])->assertForbidden();
        $this->actingAs($counselor)->post(route('context.store'), ['org_id' => $this->session['current_org_id'], 'role' => 'platform_admin'])->assertForbidden();

        $this->actingAs($counselor)->post(route('context.store'), $session + ['org_id' => $session['current_org_id'], 'role' => 'counselor'])->assertRedirect(route('dashboard'));
    }
}
