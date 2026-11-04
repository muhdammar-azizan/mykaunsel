<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Models\AuditLog;
use App\Models\CounselorKbAttempt;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\User;
use App\Services\LkmVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** Signing up as a counselor on the web (docs/COUNSELORS.md). Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorSignupTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        LkmDirectorySnapshot::create(['kb_number' => 'KB12345', 'pa_number' => 'PA12345', 'full_name' => 'Aminah binti Ahmad', 'status' => 'Aktif', 'pa_valid_from' => '2026-01-01', 'pa_valid_until' => '2027-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function signUp(array $over = [], string $ip = '203.0.113.10')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->post(route('counselors.signup.store'), $over + [
            'name' => 'Aminah binti Ahmad', 'email' => 'aminah@example.test', 'kb_number' => 'KB12345', 'pa_number' => 'PA12345',
            'password' => 'Secret#Pass99', 'password_confirmation' => 'Secret#Pass99', 'confirm_accurate' => 1, 'confirm_terms' => 1,
        ]);
    }

    // ---- a sign-up that works ------------------------------------------------------------------------------

    public function test_a_valid_sign_up_makes_the_account_profile_and_membership_and_signs_the_person_in_with_a_new_session(): void
    {
        $this->startSession();
        $before = session()->getId();

        $this->signUp()->assertRedirect(route('counselors.signup.verified'));

        $user = User::where('email', 'aminah@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId(), 'a new sign-in must not keep the session id it had before');
        $this->assertSame('pending', $user->counselorProfile->verification_status->value);
        $this->assertSame('KB12345', $user->counselorProfile->kb_number);
        $this->assertTrue(Membership::where('user_id', $user->id)->where('org_id', $this->platform->id)->where('role', MembershipRole::Counselor->value)->exists());
    }

    public function test_a_sign_up_leaves_one_audit_entry_with_no_number_and_no_name_in_it(): void
    {
        $this->signUp()->assertRedirect();

        $profile = CounselorProfile::where('kb_number', 'KB12345')->firstOrFail();
        $entry = AuditLog::where('action', 'counselor.signed_up')->sole();

        $this->assertSame(['counselor', $profile->id, $profile->user_id], [$entry->target_type, $entry->target_id, $entry->actor_user_id]);
        $this->assertSame(['source' => 'web'], $entry->metadata);
        foreach (['KB12345', 'PA12345', 'Aminah', 'aminah@example.test'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, json_encode($entry->metadata), 'ids and facts only');
        }
    }

    public function test_a_refused_sign_up_leaves_no_audit_entry(): void
    {
        $this->signUp(['kb_number' => 'KB99999'])->assertSessionHasErrors('lkm');

        $this->assertSame(0, AuditLog::where('action', 'counselor.signed_up')->count());
    }

    // ---- the directory can not be searched through the form ------------------------------------------------

    public function test_an_unknown_kb_number_a_wrong_name_and_a_wrong_pa_number_all_get_the_same_answer(): void
    {
        $same = LkmVerificationService::NOT_VERIFIED_MESSAGE;

        $this->signUp(['kb_number' => 'KB99999'])->assertSessionHasErrors(['lkm' => $same]);
        $this->signUp(['name' => 'Someone Else'])->assertSessionHasErrors(['lkm' => $same]);
        $this->signUp(['pa_number' => 'PA00000'])->assertSessionHasErrors(['lkm' => $same]);

        $this->assertSame(0, User::where('email', 'aminah@example.test')->count());
        $this->assertGuest();
    }

    public function test_an_already_registered_kb_number_looks_the_same_until_name_and_pa_number_match_it(): void
    {
        $this->signUp()->assertRedirect();
        auth()->logout();

        // Someone who only knows the KB number learns nothing: no "already registered", but the attempt is still recorded on the account.
        $this->signUp(['name' => 'Not Her', 'email' => 'other@example.test'], '203.0.113.20')->assertSessionHasErrors(['lkm' => LkmVerificationService::NOT_VERIFIED_MESSAGE]);
        $this->assertSame(1, CounselorKbAttempt::where('kb_number', 'KB12345')->count());

        // Someone who knows the name and the PA number too is told, and the attempt is recorded.
        $this->signUp(['email' => 'other@example.test'], '203.0.113.21')->assertSessionHasErrors(['lkm' => LkmVerificationService::ALREADY_REGISTERED_MESSAGE]);
        $this->assertSame(2, CounselorKbAttempt::where('kb_number', 'KB12345')->count());
        $this->assertSame(1, CounselorProfile::where('kb_number', 'KB12345')->count());
    }

    public function test_an_expired_pa_is_only_said_to_someone_who_matches_the_record(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['pa_valid_until' => '2026-10-01']);

        $this->signUp(['name' => 'Someone Else'])->assertSessionHasErrors(['lkm' => LkmVerificationService::NOT_VERIFIED_MESSAGE]);
        $this->signUp()->assertSessionHasErrors(['lkm' => 'Your practicing certificate (PA) has expired.']);
    }

    // ---- what the form itself asks for ----------------------------------------------------------------------

    public function test_the_form_rejects_a_wrong_kb_or_pa_format_a_weak_password_and_missing_confirmations_before_any_check(): void
    {
        // Twelve tries in a minute: each from its own address, so the limit on sign ups (10 a minute) is not what refuses them.
        $n = 0;
        $try = function (array $over) use (&$n) {
            return $this->signUp($over, '203.0.113.'.(++$n));
        };

        $try(['kb_number' => 'KB1234'])->assertSessionHasErrors('kb_number');
        $try(['kb_number' => 'AB12345'])->assertSessionHasErrors('kb_number');
        $try(['kb_number' => 'KB123456'])->assertSessionHasErrors('kb_number');
        $try(['pa_number' => 'PA1234'])->assertSessionHasErrors('pa_number');
        $try(['pa_number' => 'KB12345'])->assertSessionHasErrors('pa_number');
        $try(['name' => 'A'])->assertSessionHasErrors('name');
        $try(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $try(['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $try(['password' => 'alllowercase1!', 'password_confirmation' => 'alllowercase1!'])->assertSessionHasErrors('password');
        $try(['password_confirmation' => 'Different#Pass99'])->assertSessionHasErrors('password');
        $try(['confirm_accurate' => 0])->assertSessionHasErrors('confirm_accurate');
        $try(['confirm_terms' => 0])->assertSessionHasErrors('confirm_terms');

        // None of these reached the LKM check (no "could not verify" answer, no attempt, no account).
        $this->assertSame(0, CounselorKbAttempt::count());
        $this->assertSame(0, User::where('email', 'aminah@example.test')->count());
    }

    public function test_the_format_is_read_in_any_case_and_the_numbers_are_stored_in_capitals(): void
    {
        $this->signUp(['kb_number' => 'kb12345', 'pa_number' => 'pa12345'])->assertRedirect(route('counselors.signup.verified'));

        $profile = CounselorProfile::where('kb_number', 'KB12345')->firstOrFail();
        $this->assertSame(['KB12345', 'PA12345'], [$profile->kb_number, $profile->pa_number]);
    }

    public function test_an_e_mail_address_that_is_already_used_is_said_so_on_the_form(): void
    {
        User::factory()->create(['email' => 'aminah@example.test']);

        $this->signUp()->assertSessionHasErrors('email');
        $this->assertSame(0, CounselorProfile::where('kb_number', 'KB12345')->count());
    }

    public function test_an_lkm_record_that_is_not_active_is_not_called_an_expired_certificate(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['status' => 'Digantung']);

        $this->signUp(['name' => 'Someone Else'])->assertSessionHasErrors(['lkm' => LkmVerificationService::NOT_VERIFIED_MESSAGE]);
        $this->signUp()->assertSessionHasErrors(['lkm' => LkmVerificationService::NOT_ACTIVE_MESSAGE]);

        // An active record with a PA that ran out is the "expired" message, as before.
        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['status' => 'Aktif', 'pa_valid_until' => '2026-10-01']);
        $this->signUp()->assertSessionHasErrors(['lkm' => 'Your practicing certificate (PA) has expired.']);
    }

    // ---- how names are matched --------------------------------------------------------------------------------

    public function test_capitals_spacing_punctuation_and_accents_do_not_make_a_real_name_fail(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['full_name' => "Nur Sa'adah binti Mohd. Zaki"]);

        foreach (['NUR SA\'ADAH BINTI MOHD. ZAKI', '  nur   saadah  binti  mohd zaki ', 'Nur Saadah binti Mohd, Zaki', 'Núr Sa’adah binti Mohd Zakí'] as $i => $typed) {
            $this->assertNull(app(LkmVerificationService::class)->verificationFailureReason('KB12345', 'PA12345', $typed), "typed: {$typed}");
        }
    }

    public function test_a_different_name_never_matches_however_close(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB12345')->update(['full_name' => 'Aina binti Ali']);
        $same = LkmVerificationService::NOT_VERIFIED_MESSAGE;

        foreach (['Aina bt Ali', 'Aina b. Ali', 'Aina Ali', 'Aina binti', 'Ali binti Aina', 'Aina binti Ali Ahmad', 'Aini binti Ali', 'Zz'] as $typed) {
            $this->signUp(['name' => $typed])->assertSessionHasErrors(['lkm' => $same]);
        }

        $this->assertSame(0, User::where('email', 'aminah@example.test')->count());
    }

    public function test_the_names_in_other_scripts_are_compared_as_they_are_not_turned_into_letters(): void
    {
        $this->assertSame('陈 伟明', LkmVerificationService::normalizeName(' 陈  伟明 '));
        $this->assertNotSame(LkmVerificationService::normalizeName('陈伟明'), LkmVerificationService::normalizeName('沉伟明'));
        $this->assertSame('a p', LkmVerificationService::normalizeName('A/P'));
        $this->assertSame('', LkmVerificationService::normalizeName(' .,- '));
    }

    public function test_sign_up_is_limited_per_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->signUp(['kb_number' => 'KB99999'])->assertSessionHasErrors('lkm');
        }

        $this->signUp(['kb_number' => 'KB99999'])->assertStatus(429);
        $this->signUp(['kb_number' => 'KB99999'], '203.0.113.77')->assertSessionHasErrors('lkm');   // another address is not affected
    }

    // ---- two sign-ups at the same moment -------------------------------------------------------------------

    public function test_two_sign_ups_with_the_same_kb_number_at_once_leave_one_account_and_a_clear_message_not_an_error_page(): void
    {
        $first = $this->counselor('First One', [$this->platform], ['kb' => 'KB12345']);

        // The check before saving saw nothing yet (the other sign-up was not saved at that moment); the database then refuses the second one.
        $this->partialMock(LkmVerificationService::class, fn ($mock) => $mock->shouldReceive('verificationFailureReason')->once()->andReturnNull());

        $this->signUp()->assertSessionHasErrors(['lkm' => LkmVerificationService::ALREADY_REGISTERED_MESSAGE]);

        $this->assertSame(1, CounselorProfile::where('kb_number', 'KB12345')->count());
        $this->assertSame(0, User::where('email', 'aminah@example.test')->count(), 'nothing was left behind by the refused sign-up');
        $this->assertGuest();
        $this->assertNotNull($first);
    }
}
