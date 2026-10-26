<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Individuals sign up in the mobile app. The web sign-up form is kept (views, tests) but switched off by
 * `registration.web_individual_enabled`: 404 for GET and POST while it is off, and no public page links to it.
 * When it is on, the Individuals switch in Registration Control still applies. phpunit.xml turns it on for the other
 * tests; the ones here set it explicitly.
 */
class WebIndividualRegistrationTest extends CounselorTestCase
{
    private const NEW_USER = ['name' => 'Aina Syazwani', 'email' => 'aina.web@example.com', 'password' => 'Str0ng-Pass-123', 'password_confirmation' => 'Str0ng-Pass-123'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function off(): void
    {
        config(['registration.web_individual_enabled' => false]);
    }

    private function on(): void
    {
        config(['registration.web_individual_enabled' => true]);
    }

    private function visitor(): void
    {
        $this->flushSession();
        auth()->logout();
    }

    private function closeIndividual(): void
    {
        $this->as()->post(route('platform.registration.toggle', 'individual'), ['state' => 'closed'])->assertSessionHas('status');
        $this->visitor();
    }

    // ---- switched off (the real setting) -----------------------------------------------------------------

    public function test_the_setting_is_off_by_default_and_on_in_the_tests(): void
    {
        // A server whose .env does not mention it gets the file's default, and the example .env says false too.
        $this->assertStringContainsString("env('REGISTRATION_WEB_INDIVIDUAL_ENABLED', false)", file_get_contents(base_path('config/registration.php')));
        $this->assertStringContainsString('REGISTRATION_WEB_INDIVIDUAL_ENABLED=false', file_get_contents(base_path('.env.example')));
        $this->assertTrue(config('registration.web_individual_enabled'), 'phpunit.xml switches it on for the tests');
        $this->assertStringContainsString('REGISTRATION_WEB_INDIVIDUAL_ENABLED" value="true"', file_get_contents(base_path('phpunit.xml')));
    }

    public function test_while_it_is_off_the_web_form_is_a_404_for_get_and_post_not_a_redirect(): void
    {
        $this->off();
        $this->visitor();

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register'), self::NEW_USER)->assertNotFound();
        $this->assertNull(User::where('email', self::NEW_USER['email'])->first());

        // Signed in, too: not the "already signed in" redirect either.
        $this->as()->get(route('register'))->assertNotFound();
        $this->as()->post(route('register'), self::NEW_USER)->assertNotFound();
    }

    public function test_it_is_a_404_even_when_the_individuals_switch_is_open_and_when_it_is_closed(): void
    {
        $this->off();
        $this->visitor();
        $this->assertTrue(app(PlatformSettings::class)->registrationOpen('individual'));
        $this->get(route('register'))->assertNotFound();

        $this->closeIndividual();
        $this->get(route('register'))->assertNotFound()->assertDontSee('not accepting new individual sign-ups');
    }

    public function test_the_404_is_the_normal_not_found_page_and_the_form_is_not_in_it(): void
    {
        $this->off();
        $this->visitor();

        $this->get(route('register'))->assertNotFound()->assertDontSee('name="password_confirmation"', false);
    }

    public function test_the_mobile_apps_own_sign_up_is_not_touched_by_the_setting(): void
    {
        $this->off();

        $this->postJson('/api/v1/auth/register', ['name' => 'Aina Syazwani', 'email' => 'aina.app@example.com', 'password' => 'Str0ng-Pass-123', 'password_confirmation' => 'Str0ng-Pass-123'])
            ->assertSuccessful();
        $this->assertNotNull(User::where('email', 'aina.app@example.com')->first());
    }

    public function test_organization_and_counselor_sign_up_on_the_web_are_not_touched_by_the_setting(): void
    {
        $this->off();
        $this->visitor();

        $this->get(route('organizations.signup.create'))->assertOk();
        $this->get(route('counselors.signup.create'))->assertOk();
        $this->get(route('register.type'))->assertOk();

        // They still obey their own switches.
        $this->as()->post(route('platform.registration.toggle', 'organization'), ['state' => 'closed']);
        $this->visitor();
        $this->get(route('organizations.signup.create'))->assertForbidden();
        $this->get(route('counselors.signup.create'))->assertOk();
    }

    // ---- no public link to it ---------------------------------------------------------------------------------

    public function test_no_public_page_links_to_the_individual_form_and_each_points_to_the_mobile_app(): void
    {
        $this->off();
        $this->visitor();
        $register = route('register', absolute: false);
        $app = config('registration.mobile_app_url');

        // The pages individuals might land on: none links to the form, and each points to the app instead.
        foreach (['/' => 'landing page', route('login', absolute: false) => 'log in page', route('register.type', absolute: false) => 'account type page'] as $path => $page) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression('#href="[^"]*'.preg_quote($register, '#').'"#', $html, "{$page} must not link to the individual sign-up form");
            $this->assertStringContainsString('Get the mobile app', $html, "{$page} should point individuals to the app");
            $this->assertStringContainsString('href="'.$app.'"', $html, $page);
        }

        // The organization and counselor sign-up pages do not link to it either.
        foreach ([route('organizations.signup.create', absolute: false), route('counselors.signup.create', absolute: false)] as $path) {
            $this->assertDoesNotMatchRegularExpression('#href="[^"]*'.preg_quote($register, '#').'"#', $this->get($path)->assertOk()->getContent(), $path);
        }
    }

    public function test_the_landing_page_has_the_app_section_the_link_leads_to(): void
    {
        $this->visitor();

        $this->get('/')->assertOk()->assertSee('id="get-the-app"', false)->assertSee('coming soon')->assertDontSee('Mula Sekarang');
        $this->assertSame('/#get-the-app', config('registration.mobile_app_url'));
    }

    public function test_the_log_in_page_still_offers_organization_and_counselor_sign_up_through_the_chooser(): void
    {
        $this->visitor();

        $this->get(route('login'))->assertOk()->assertSee(route('register.type'), false)->assertSee('Sign up');
        $this->get(route('register.type'))->assertOk()->assertSee('Organization')->assertSee('Registered Counselor')->assertDontSee('Individual')
            ->assertSee('Looking for support?')->assertSee(route('organizations.signup.create'), false)->assertSee(route('counselors.signup.create'), false);
    }

    public function test_the_account_type_page_has_no_individual_card(): void
    {
        $this->visitor();

        $html = $this->get(route('register.type'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-href='), 'cards for organization and counselor only');
        $this->assertStringContainsString(route('organizations.signup.create', absolute: false), $html);
        $this->assertStringContainsString(route('counselors.signup.create', absolute: false), $html);
        $this->assertStringNotContainsStringIgnoringCase('individual', strip_tags(preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $html)));
    }

    // ---- switched on (local / tests / a future web version) ------------------------------------------------------

    public function test_when_it_is_on_the_form_works_as_before(): void
    {
        $this->on();
        $this->visitor();

        $this->get(route('register'))->assertOk();
        $this->post(route('register'), self::NEW_USER)->assertRedirect();
        $this->assertNotNull(User::where('email', self::NEW_USER['email'])->first());
    }

    public function test_when_it_is_on_the_individuals_switch_still_closes_it(): void
    {
        $this->on();
        $this->closeIndividual();

        $this->get(route('register'))->assertForbidden()->assertSee('not accepting new individual sign-ups');
        $this->post(route('register'), self::NEW_USER)->assertForbidden();
        $this->assertNull(User::where('email', self::NEW_USER['email'])->first());

        // ...and opening it again opens it again.
        $this->as()->post(route('platform.registration.toggle', 'individual'), ['state' => 'open']);
        $this->visitor();
        $this->get(route('register'))->assertOk();
    }

    // ---- "Continue with Google" -----------------------------------------------------------------------------------

    public function test_a_new_account_on_the_web_is_allowed_only_when_the_door_and_the_switch_are_both_open(): void
    {
        $settings = fn () => app(PlatformSettings::class);

        $this->off();
        $this->assertFalse($settings()->canCreateIndividualOnWeb(), 'door shut, switch open');

        $this->on();
        $this->assertTrue($settings()->canCreateIndividualOnWeb());

        $this->as()->post(route('platform.registration.toggle', 'individual'), ['state' => 'closed']);
        $this->assertFalse($settings()->canCreateIndividualOnWeb(), 'door open, switch closed');

        $this->off();
        $this->assertFalse($settings()->canCreateIndividualOnWeb(), 'both shut');
    }

    public function test_there_is_no_working_google_sign_in_yet_and_adding_one_must_use_the_guard(): void
    {
        // Today "Continue with Google" is only a "coming soon" button: nothing to sign in or sign up with.
        $this->assertFalse(class_exists('Laravel\Socialite\Facades\Socialite'), 'Socialite is installed: new accounts made through Google on the web MUST be refused unless PlatformSettings::canCreateIndividualOnWeb() is true (an existing account signing in is fine). See docs/REGISTRATION_CONTROL.md, then update this test with a real one.');
        $this->assertSame([], collect(Route::getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'auth/google') || str_contains($r->uri(), 'oauth'))->map->uri()->all());

        $this->visitor();
        $this->get(route('login'))->assertOk()->assertSee('Coming soon')->assertSee('Continue with Google');
    }

    public function test_the_audit_log_is_not_written_by_a_blocked_web_sign_up(): void
    {
        $this->off();
        $this->visitor();
        $entries = AuditLog::count();

        $this->post(route('register'), self::NEW_USER)->assertNotFound();

        $this->assertSame($entries, AuditLog::count());
    }
}
