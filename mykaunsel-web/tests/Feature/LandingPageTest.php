<?php

namespace Tests\Feature;

use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The main page of the public website (docs/LANDING_PAGE.md): language, the placeholder switch, who is offered "sign up", and the pages that are not built yet. */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_is_in_malay_by_default_and_in_english_when_asked_and_the_choice_is_kept(): void
    {
        $this->get('/')->assertOk()->assertSee('lang="ms"', false)->assertSee('Sokongan kaunseling yang disahkan');

        $response = $this->get('/?lang=en')->assertOk()->assertSee('lang="en"', false)->assertSee('Verified counseling support')->assertDontSee('Sokongan kaunseling yang disahkan');
        $response->assertCookie('mk_lang', 'en');

        // The cookie is read on the next visit, and an unknown language is ignored.
        $this->withCookie('mk_lang', 'en')->get('/')->assertSee('Verified counseling support');
        $this->get('/?lang=xx')->assertOk()->assertSee('lang="ms"', false);
    }

    public function test_the_placeholder_blocks_are_shown_or_gone_with_the_switch_and_the_true_block_stays(): void
    {
        config(['landing.show_placeholder_content' => true]);
        $this->get('/')->assertSee('data-placeholder="proof_logos"', false)->assertSee('data-placeholder="proof_quotes"', false)->assertSee('data-placeholder="proof_figures"', false)->assertSee('data-placeholder="problem"', false);

        config(['landing.show_placeholder_content' => false]);
        $this->get('/')->assertOk()
            ->assertDontSee('data-placeholder', false)->assertDontSee('Mencari sokongan tidak sepatutnya sukar')->assertDontSee('Nama Contoh')
            ->assertSee('Kelayakan disemak')->assertSee('Sesi tidak dirakam');
    }

    public function test_individuals_are_sent_to_the_app_and_organizations_and_counselors_to_their_sign_up(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.config('registration.mobile_app_url').'"', $html);
        $this->assertStringContainsString('href="'.route('organizations.signup.create').'"', $html);
        $this->assertStringContainsString('href="'.route('counselors.signup.create').'"', $html);
        $this->assertDoesNotMatchRegularExpression('#href="[^"]*/register"#', $html);
    }

    public function test_when_a_door_is_closed_the_page_offers_the_waitlist_instead_of_sign_up(): void
    {
        $settings = app(PlatformSettings::class);
        $settings->setRegistration(null, ['organization', 'counselor'], false, true);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('organizations.signup.create').'"', $html);
        $this->assertStringNotContainsString('href="'.route('counselors.signup.create').'"', $html);
        $this->assertStringContainsString('href="'.route('register.type').'"', $html);
    }

    public function test_a_page_that_is_not_built_yet_opens_the_coming_soon_page_and_never_a_404(): void
    {
        $this->get('/coming-soon/pricing')->assertOk()->assertSee('Halaman ini akan datang tidak lama lagi')->assertSee('Pricing')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/coming-soon')->assertOk();
        $this->get('/coming-soon/Bad Slug')->assertNotFound();

        // Every link in the header and footer that is not a real page leads there, and the real ones lead to their own page.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('landing.soon', ['page' => 'glossary']).'"', $html);
        $this->assertStringContainsString('href="'.route('terms').'"', $html);
        $this->assertStringContainsString('href="'.route('privacy').'"', $html);
    }

    public function test_someone_who_is_signed_in_sees_a_dashboard_button_instead_of_log_in_and_sign_up(): void
    {
        $guest = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('login').'"', $guest);
        $this->assertStringContainsString('aria-haspopup="true" class="inline-flex items-center gap-1.5 rounded-md border', $guest);

        $user = \App\Models\User::factory()->create();
        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('href="'.route('dashboard').'"', $html);
        $this->assertStringNotContainsString('aria-haspopup="true" class="inline-flex items-center gap-1.5 rounded-md border', $html);
    }

    public function test_the_urgent_help_strip_the_contact_address_and_the_media_slots_are_there(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('15555')->assertSee('15999')->assertSee('hello@mykaunsel.example')
            ->assertSee('data-media-slot="app-screen"', false)->assertSee('data-media-slot="how-it-works"', false)->assertSee('public/landing/how-it-works.mp4', false);
    }

    public function test_the_page_makes_no_claim_it_can_not_keep(): void
    {
        $html = strtolower($this->get('/')->assertOk()->getContent().$this->get('/?lang=en')->getContent());

        $this->assertStringNotContainsString('connected live', $html);
        $this->assertStringNotContainsString('sambungan langsung', $html);
        $this->assertStringNotContainsString('percuma', $html);
    }
}
