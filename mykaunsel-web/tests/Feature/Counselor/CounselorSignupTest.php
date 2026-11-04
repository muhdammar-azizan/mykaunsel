<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
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
