<?php

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\WaitlistEntry;
use App\Models\WaitlistSuppression;
use App\Services\PlatformSettings;
use App\Services\WaitlistHasher;
use App\Services\WaitlistService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * The public side of the waitlist (docs/WAITLIST.md): the form on a closed sign-up page, what it stores, the one answer it
 * gives to everybody, its limits, and the opt-out link.
 */
class WaitlistPublicTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function visitor(): void
    {
        $this->flushSession();
        auth()->logout();
    }

    private function close(string ...$types): void
    {
        foreach ($types as $type) {
            $this->as()->post(route('platform.registration.toggle', $type), ['state' => 'closed'])->assertSessionHas('status');
        }

        $this->visitor();
    }

    private function join(string $type, string $email, string $ip = '203.0.113.1', array $extra = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('waitlist.store', $type), ['email' => $email] + $extra);
    }

    private function hasher(): WaitlistHasher
    {
        return app(WaitlistHasher::class);
    }

    private function entry(string $type, string $email): ?WaitlistEntry
    {
        return WaitlistEntry::where('type', $type)->where('email', $email)->first();
    }

    private function queries(callable $act): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $act();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function unsubscribeUrl(string $type, string $email): string
    {
        return $this->hasher()->unsubscribeUrl($type, $email);
    }

    // ---- the form on the closed page ---------------------------------------------------------------------

    public function test_a_closed_organization_page_offers_the_waitlist_with_the_consent_line_and_is_never_cached(): void
    {
        $this->close('organization');

        $response = $this->get(route('organizations.signup.create'))->assertForbidden()
            ->assertSee('Email me when organization sign-up reopens')
            ->assertSee('We will email you once, only when organization sign-up reopens. We do not share your email, and you can opt out in that email.')
            ->assertSee('href="/privacy"', false)->assertSee('name="company_website"', false)->assertSee(route('waitlist.store', 'organization'), false);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->get('/privacy')->assertOk()->assertSee('sign-up reminder list');
    }

    public function test_a_closed_counselor_page_offers_it_too_and_an_open_one_does_not(): void
    {
        $this->close('counselor');

        $this->get(route('counselors.signup.create'))->assertForbidden()->assertSee('Email me when counselor sign-up reopens');
        $this->get(route('organizations.signup.create'))->assertOk()->assertDontSee('company_website', false)->assertDontSee('Email me when');
    }

    public function test_individuals_have_no_waitlist_anywhere(): void
    {
        $this->close('individual');

        // The web form is on in the tests; closed, it shows the closed page, but no waitlist.
        $this->get(route('register'))->assertForbidden()->assertDontSee('Email me when')->assertDontSee('company_website', false);
        $this->post(route('waitlist.store', 'individual'), ['email' => 'a@example.com'])->assertNotFound();
        $this->assertSame(['organization', 'counselor'], array_keys(app(WaitlistService::class)->counts()));
        $this->assertSame(['organization', 'counselor'], array_keys(app(WaitlistService::class)->waitingCounts()));
        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_the_account_type_page_offers_a_form_for_each_closed_kind(): void
    {
        $this->close('organization');
        $this->get(route('register.type'))->assertOk()->assertSee('Email me when organization sign-up reopens')->assertDontSee('Email me when counselor sign-up reopens');

        $this->close('counselor');
        $this->get(route('register.type'))->assertForbidden()->assertSee('Email me when organization sign-up reopens')->assertSee('Email me when counselor sign-up reopens');
    }

    public function test_the_account_type_page_has_no_form_while_everything_is_open_and_is_not_cached(): void
    {
        $response = $this->get(route('register.type'))->assertOk()->assertDontSee('Email me when');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_without_its_secret_key_the_waitlist_is_off_and_invisible(): void
    {
        $this->close('organization');
        config(['waitlist.hmac_key' => '']);

        $this->get(route('organizations.signup.create'))->assertForbidden()->assertDontSee('Email me when')->assertDontSee('company_website', false);
        $this->post(route('waitlist.store', 'organization'), ['email' => 'a@example.com'])->assertNotFound();
        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_during_maintenance_there_is_no_form_and_no_sign_up_to_the_list(): void
    {
        $this->close('organization');
        PlatformSetting::findOrFail('maintenance')->update(['value' => true]);
        $this->app->forgetInstance(PlatformSettings::class);

        $this->get(route('organizations.signup.create'))->assertStatus(503)->assertDontSee('Email me when');
        $this->post(route('waitlist.store', 'organization'), ['email' => 'a@example.com'])->assertStatus(503);
        $this->assertSame(0, WaitlistEntry::count());
    }

    // ---- signing up --------------------------------------------------------------------------------------------

    public function test_an_address_is_stored_tidied_with_its_kind_and_the_page_says_thanks(): void
    {
        $this->close('organization');

        $this->join('organization', '  Ana.Tan@Example.COM ')->assertRedirect(route('organizations.signup.create'))
            ->assertSessionHas('waitlist_status', 'Thank you. If this address can be added, we will email you once when organization sign-up reopens.');

        $entry = $this->entry('organization', 'ana.tan@example.com');
        $this->assertNotNull($entry);
        $this->assertSame([WaitlistEntry::WAITING, null, null], [$entry->status, $entry->notified_at, $entry->emailed_at]);
        $this->assertSame($this->hasher()->emailHash('organization', 'ana.tan@example.com'), $entry->email_hash);
        $this->assertSame(1, WaitlistEntry::count());

        $this->get(route('organizations.signup.create'))->assertForbidden()->assertSee('Thank you. If this address can be added');
    }

    public function test_the_same_address_in_any_letter_case_is_one_row_and_the_two_kinds_are_separate(): void
    {
        $this->close('organization', 'counselor');

        $this->join('organization', 'Ana@Example.com');
        $this->join('organization', 'ana@example.com ');
        $this->join('organization', 'ANA@EXAMPLE.COM');
        $this->join('counselor', 'ana@example.com');

        $this->assertSame(1, WaitlistEntry::where('type', 'organization')->count());
        $this->assertSame(1, WaitlistEntry::where('type', 'counselor')->count());
    }

    public function test_the_database_itself_refuses_a_second_row_for_the_same_address_and_kind(): void
    {
        $this->close('organization');
        $this->join('organization', 'ana@example.com');
        $row = WaitlistEntry::firstOrFail()->only(['type', 'email', 'email_hash', 'status']);

        $this->expectException(UniqueConstraintViolationException::class);
        WaitlistEntry::query()->insert($row + ['created_at' => now(), 'updated_at' => now()]);
    }

    public function test_two_requests_at_the_same_instant_make_one_row_and_both_get_the_same_answer(): void
    {
        $this->close('organization');
        $waitlist = app(WaitlistService::class);
        $racer = false;

        // The competing request gets its row in right after this one has looked at the opt-out list and before it inserts.
        DB::listen(function ($query) use (&$racer) {
            if (! $racer && str_contains($query->sql, 'waitlist_suppressions')) {
                $racer = true;
                DB::table('waitlist_entries')->insert([
                    'type' => 'organization', 'email' => 'race@example.com', 'email_hash' => $this->hasher()->emailHash('organization', 'race@example.com'),
                    'status' => 'waiting', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $waitlist->signUp('organization', 'race@example.com'); // must not throw

        $this->assertTrue($racer, 'the competing insert happened');
        $this->assertSame(1, WaitlistEntry::where('email', 'race@example.com')->count());
    }

    public function test_the_answer_is_the_same_for_a_new_listed_notified_and_opted_out_address_and_does_the_same_work(): void
    {
        $this->close('organization');
        $hash = fn (string $email) => $this->hasher()->emailHash('organization', $email);

        WaitlistEntry::query()->insert(['type' => 'organization', 'email' => 'notified@example.com', 'email_hash' => $hash('notified@example.com'), 'status' => 'notified', 'notified_at' => now()->subDay(), 'emailed_at' => now()->subDay(), 'created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()]);
        WaitlistEntry::query()->insert(['type' => 'organization', 'email' => 'listed@example.com', 'email_hash' => $hash('listed@example.com'), 'status' => 'waiting', 'created_at' => now()->subWeek(), 'updated_at' => now()->subWeek()]);
        WaitlistSuppression::query()->insert(['type' => 'organization', 'email_hash' => $hash('gone@example.com'), 'created_at' => now()]);

        $this->join('organization', 'warmup@example.com', '203.0.113.2'); // (the first request of a test also loads the settings)

        $answers = [];
        $work = [];
        foreach (['new@example.com', 'listed@example.com', 'notified@example.com', 'gone@example.com'] as $i => $email) {
            $work[$email] = $this->queries(function () use ($email, $i, &$response) {
                $response = $this->join('organization', $email, '203.0.113.'.(10 + $i));
            });
            $answers[$email] = [$response->getStatusCode(), $response->headers->get('Location'), $response->getSession()->get('waitlist_status'), $response->getSession()->get('waitlist_error')];
        }

        $this->assertCount(1, array_unique($answers, SORT_REGULAR), 'one answer for everybody');
        $this->assertSame(200 + 102, $answers['new@example.com'][0]);
        $this->assertCount(1, array_unique($work), 'the same number of database queries on every path: '.json_encode($work));
    }

    public function test_an_address_that_was_notified_and_signs_up_again_goes_back_to_waiting(): void
    {
        $this->close('organization');
        WaitlistEntry::query()->insert(['type' => 'organization', 'email' => 'back@example.com', 'email_hash' => $this->hasher()->emailHash('organization', 'back@example.com'), 'status' => 'notified', 'notified_at' => now()->subDays(5), 'emailed_at' => now()->subDays(5), 'created_at' => now()->subMonth(), 'updated_at' => now()->subMonth()]);

        $this->join('organization', 'back@example.com')->assertSessionHas('waitlist_status');

        $entry = $this->entry('organization', 'back@example.com');
        $this->assertSame([WaitlistEntry::WAITING, null, null], [$entry->status, $entry->notified_at, $entry->emailed_at]);
        $this->assertTrue($entry->created_at->greaterThan(now()->subMinute()), 'signed up again, now');
    }

    public function test_someone_who_opted_out_is_not_stored_again_but_is_told_the_same_thing(): void
    {
        $this->close('organization');
        WaitlistSuppression::query()->insert(['type' => 'organization', 'email_hash' => $this->hasher()->emailHash('organization', 'gone@example.com'), 'created_at' => now()]);

        $this->join('organization', 'Gone@Example.com')->assertSessionHas('waitlist_status', fn ($m) => str_starts_with($m, 'Thank you. If this address can be added'));

        $this->assertNull($this->entry('organization', 'gone@example.com'));
        $this->assertSame(0, WaitlistEntry::count());

        // ...but opting out of one list says nothing about the other.
        $this->close('counselor');
        $this->join('counselor', 'gone@example.com');
        $this->assertNotNull($this->entry('counselor', 'gone@example.com'));
    }

    public function test_a_bad_address_is_refused_with_a_message_and_nothing_is_stored(): void
    {
        $this->close('organization');

        foreach (['', 'not-an-email', 'a@', str_repeat('a', 250).'@example.com'] as $bad) {
            $this->join('organization', $bad)->assertRedirect(route('organizations.signup.create'))->assertSessionHasErrorsIn('waitlist', 'email');
        }

        $this->assertSame(0, WaitlistEntry::count());
        $this->join('organization', 'not-an-email');
        $this->get(route('organizations.signup.create'))->assertSee('Please enter a valid email address.');
    }

    public function test_a_bot_that_fills_the_hidden_field_gets_the_same_thank_you_and_nothing_is_stored(): void
    {
        $this->close('organization');

        $this->join('organization', 'bot@example.com', extra: ['company_website' => 'http://spam.example'])
            ->assertRedirect(route('organizations.signup.create'))->assertSessionHas('waitlist_status', fn ($m) => str_starts_with($m, 'Thank you. If this address can be added'));

        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_when_the_sign_up_is_already_open_nothing_is_stored_and_the_person_is_sent_to_the_real_form(): void
    {
        // organization is open (not closed here)
        $response = $this->join('organization', 'late@example.com')->assertOk()->assertSee('sign-up has just opened')->assertSee('Your email was not saved')->assertSee(route('organizations.signup.create'), false);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(0, WaitlistEntry::count());
    }

    // ---- the limits ----------------------------------------------------------------------------------------------

    public function test_five_attempts_an_hour_from_one_address_of_the_network_then_a_polite_no(): void
    {
        $this->close('organization');

        foreach (range(1, 5) as $i) {
            $this->join('organization', "person{$i}@example.com", '198.51.100.7')->assertSessionHas('waitlist_status');
        }

        $this->join('organization', 'person6@example.com', '198.51.100.7')->assertRedirect(route('organizations.signup.create'))
            ->assertSessionMissing('waitlist_status')->assertSessionHas('waitlist_error', 'Too many attempts. Please try again later.');
        $this->assertNull($this->entry('organization', 'person6@example.com'));
        $this->get(route('organizations.signup.create'))->assertSee('Too many attempts.');

        // Another network is not affected; an hour later the first one is fine again.
        $this->join('organization', 'person7@example.com', '198.51.100.8')->assertSessionHas('waitlist_status');
        Carbon::setTestNow(now()->addHour()->addMinute());
        $this->join('organization', 'person8@example.com', '198.51.100.7')->assertSessionHas('waitlist_status');
    }

    public function test_three_attempts_a_day_for_the_same_address_whoever_asks(): void
    {
        $this->close('organization');

        foreach (range(1, 3) as $i) {
            $this->join('organization', 'same@example.com', '203.0.113.'.$i)->assertSessionHas('waitlist_status');
        }

        $this->join('organization', 'Same@Example.com', '203.0.113.99')->assertSessionHas('waitlist_error')->assertSessionMissing('waitlist_status');

        // The limit is the same for an address nobody has listed: it tells nothing about the list.
        foreach (range(1, 3) as $i) {
            $this->join('organization', 'fresh@example.com', '203.0.114.'.$i);
        }
        $this->join('organization', 'fresh@example.com', '203.0.114.99')->assertSessionHas('waitlist_error');

        Carbon::setTestNow(now()->addDay()->addMinute());
        $this->join('organization', 'same@example.com', '203.0.113.200')->assertSessionHas('waitlist_status');
    }

    public function test_a_limit_for_each_kind_of_sign_up_against_an_attack_from_many_addresses(): void
    {
        config(['waitlist.limits.global_per_hour' => 3]);
        $this->close('organization', 'counselor');

        foreach (range(1, 3) as $i) {
            $this->join('organization', "p{$i}@example.com", '203.0.113.'.$i)->assertSessionHas('waitlist_status');
        }

        $this->join('organization', 'p4@example.com', '203.0.113.40')->assertSessionHas('waitlist_error');
        $this->assertSame(3, WaitlistEntry::where('type', 'organization')->count());

        // Counselors have their own count.
        $this->join('counselor', 'c1@example.com', '203.0.113.50')->assertSessionHas('waitlist_status');
    }

    public function test_the_default_limits_are_the_agreed_ones(): void
    {
        $this->assertSame([5, 3, 100], [config('waitlist.limits.per_ip_per_hour'), config('waitlist.limits.per_email_per_day'), config('waitlist.limits.global_per_hour')]);
    }

    // ---- the page expired ----------------------------------------------------------------------------------------

    public function test_a_form_left_open_until_its_session_expired_goes_back_with_a_friendly_line_not_an_error_page(): void
    {
        $request = Request::create(route('waitlist.store', 'counselor'), 'POST');
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession(app('session.store'));

        $response = app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertTrue($response->isRedirect(route('counselors.signup.create')), 'status '.$response->getStatusCode());
        $this->assertSame('That page was open for too long. Please try again.', $request->session()->get('waitlist_error'));
    }

    // ---- opting out ------------------------------------------------------------------------------------------------

    public function test_the_opt_out_link_asks_first_and_only_the_button_opts_out(): void
    {
        $this->close('organization');
        $this->join('organization', 'ana@example.com');
        $url = $this->unsubscribeUrl('organization', 'ana@example.com');

        // A mail scanner opening the link changes nothing.
        $this->get($url)->assertOk()->assertSee('Stop these emails?')->assertSee('Yes, stop these emails');
        $this->assertNotNull($this->entry('organization', 'ana@example.com'));
        $this->assertSame(0, WaitlistSuppression::count());

        $this->post($url)->assertOk()->assertSee('You are opted out');
        $this->assertNull($this->entry('organization', 'ana@example.com'));
        $this->assertSame(1, WaitlistSuppression::count());
    }

    public function test_opting_out_keeps_only_a_keyed_hash_never_the_address(): void
    {
        $this->close('organization');
        $this->join('organization', 'ana@example.com');

        $this->post($this->unsubscribeUrl('organization', 'ana@example.com'))->assertOk();

        $row = WaitlistSuppression::firstOrFail();
        $this->assertSame(['organization', $this->hasher()->emailHash('organization', 'ana@example.com')], [$row->type, $row->email_hash]);
        $this->assertSame(64, strlen($row->email_hash));
        $this->assertStringNotContainsString('ana', json_encode(DB::table('waitlist_suppressions')->get()));
        $this->assertStringNotContainsString('@', json_encode(DB::table('waitlist_suppressions')->get()));
        $this->assertSame(0, DB::table('waitlist_entries')->where('email', 'like', '%ana@example.com%')->count());
    }

    public function test_opting_out_twice_or_after_the_address_was_deleted_still_says_you_are_opted_out(): void
    {
        $this->close('organization');
        $url = $this->unsubscribeUrl('organization', 'long.gone@example.com'); // never on the list, or removed by the retention rule

        $this->post($url)->assertOk()->assertSee('You are opted out');
        $this->post($url)->assertOk()->assertSee('You are opted out');

        $this->assertSame(1, WaitlistSuppression::count(), 'remembered once, and the choice outlives the address');
        $this->join('organization', 'long.gone@example.com');
        $this->assertNull($this->entry('organization', 'long.gone@example.com'));
    }

    public function test_the_opt_out_link_never_expires(): void
    {
        $url = $this->unsubscribeUrl('counselor', 'ana@example.com');

        Carbon::setTestNow(now()->addYears(5));

        $this->get($url)->assertOk();
        $this->post($url)->assertOk()->assertSee('You are opted out');
    }

    public function test_a_made_up_or_changed_link_is_a_404_and_does_nothing(): void
    {
        $hash = $this->hasher()->emailHash('organization', 'ana@example.com');
        $good = $this->hasher()->signature('organization', $hash);
        $other = $this->hasher()->emailHash('organization', 'bob@example.com');

        foreach ([
            route('waitlist.unsubscribe', ['type' => 'organization', 'hash' => $hash, 'signature' => str_repeat('0', 64)]),
            route('waitlist.unsubscribe', ['type' => 'organization', 'hash' => $other, 'signature' => $good]),   // someone else's address
            route('waitlist.unsubscribe', ['type' => 'counselor', 'hash' => $hash, 'signature' => $good]),        // another list
        ] as $url) {
            $this->get($url)->assertNotFound();
            $this->post($url)->assertNotFound();
        }

        $this->get('/waitlist/unsubscribe/organization/short/short')->assertNotFound();
        $this->get('/waitlist/unsubscribe/individual/'.$hash.'/'.$good)->assertNotFound();
        $this->assertSame(0, WaitlistSuppression::count());
    }

    public function test_opting_out_works_during_maintenance_and_whatever_the_state_of_the_sign_up(): void
    {
        $url = $this->unsubscribeUrl('organization', 'ana@example.com');
        PlatformSetting::findOrFail('maintenance')->update(['value' => true]);
        $this->app->forgetInstance(PlatformSettings::class);
        $this->visitor();

        $this->get('/')->assertStatus(503);
        $this->get($url)->assertOk();
        $this->post($url)->assertOk()->assertSee('You are opted out');
    }

    public function test_the_one_click_opt_out_from_a_mail_program_needs_no_csrf_token(): void
    {
        $never = (new ReflectionClass(ValidateCsrfToken::class))->getProperty('neverVerify')->getValue();

        $this->assertContains('waitlist/unsubscribe/*', $never);
        $this->assertNotContains('waitlist/*', $never, 'the sign-up form itself still needs its token');
        $this->assertNotContains('platform/*', $never);
    }

    // ---- the secret --------------------------------------------------------------------------------------------------

    public function test_the_hashes_come_from_their_own_key_not_from_app_key(): void
    {
        $before = $this->hasher()->emailHash('organization', 'ana@example.com');
        $linkBefore = $this->unsubscribeUrl('organization', 'ana@example.com');

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertSame($before, $this->hasher()->emailHash('organization', 'ana@example.com'), 'rotating APP_KEY changes nothing');
        $this->assertSame($linkBefore, $this->unsubscribeUrl('organization', 'ana@example.com'));

        config(['waitlist.hmac_key' => str_repeat('b', 64)]);
        $this->assertNotSame($before, $this->hasher()->emailHash('organization', 'ana@example.com'));
        $this->assertNotSame($before, $this->hasher()->emailHash('counselor', 'ana@example.com') ?: 'x');
    }

    public function test_a_short_or_missing_key_means_the_waitlist_is_off(): void
    {
        foreach ([null, '', 'too-short'] as $key) {
            config(['waitlist.hmac_key' => $key]);
            $this->assertFalse($this->hasher()->available());
            $this->assertFalse(app(WaitlistService::class)->enabled());
        }
    }

    // ---- what is not logged -------------------------------------------------------------------------------------------

    public function test_signing_up_and_opting_out_write_nothing_to_the_audit_log(): void
    {
        $this->close('organization');
        $entries = AuditLog::count();

        $this->join('organization', 'ana@example.com');
        $this->post($this->unsubscribeUrl('organization', 'ana@example.com'));

        $this->assertSame($entries, AuditLog::count());
    }
}
