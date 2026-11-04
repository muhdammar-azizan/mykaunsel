<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\AuditLog;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CounselorApplicationReceived;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The e-mails of a counselor's application that are sent to the applicant: the e-mail confirmation at sign up and "we received your application". */
class CounselorApplicationEmailsTest extends TestCase
{
    use RefreshDatabase;

    private int $kbSeed = 10000;

    private function platform(): Organization
    {
        return Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], ['org_type' => 'platform', 'access_model' => 'open', 'subscription_status' => 'active']);
    }

    private function applicant(VerificationStatus $status = VerificationStatus::Pending, bool $verified = true): User
    {
        $this->kbSeed++;
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => 'KB'.$this->kbSeed, 'pa_number' => 'PA'.$this->kbSeed, 'pa_valid_until' => now()->addYear(),
            'verification_type' => VerificationType::PlatformVerified, 'verification_status' => $status, 'rejection_reason' => $status === VerificationStatus::Rejected ? 'Unclear scan.' : null,
        ]);

        return $user;
    }

    private function files(array $types): array
    {
        return collect($types)->mapWithKeys(fn ($type) => [$type => UploadedFile::fake()->create("{$type}.pdf", 50, 'application/pdf')])->all();
    }

    private function signUp()
    {
        $this->platform();
        LkmDirectorySnapshot::create(['kb_number' => 'KB12345', 'pa_number' => 'PA12345', 'full_name' => 'Aminah binti Ahmad', 'status' => 'Aktif', 'pa_valid_from' => '2026-01-01', 'pa_valid_until' => now()->addYear()->toDateString()]);

        return $this->post(route('counselors.signup.store'), [
            'name' => 'Aminah binti Ahmad', 'email' => 'aminah@example.test', 'kb_number' => 'KB12345', 'pa_number' => 'PA12345',
            'password' => 'Secret#Pass99', 'password_confirmation' => 'Secret#Pass99', 'confirm_accurate' => 1, 'confirm_terms' => 1,
        ]);
    }

    private function brokenMailServer(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
    }

    // ---- the e-mail confirmation at sign up --------------------------------------------------------------------

    public function test_signing_up_sends_the_link_that_confirms_the_e_mail_address(): void
    {
        Notification::fake();

        $this->signUp()->assertRedirect(route('counselors.signup.verified'));

        $user = User::where('email', 'aminah@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_a_failing_e_mail_does_not_undo_the_sign_up(): void
    {
        Log::spy();
        $this->brokenMailServer();

        $this->signUp()->assertRedirect(route('counselors.signup.verified'));

        $this->assertNotNull(User::where('email', 'aminah@example.test')->first()?->counselorProfile);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'verification e-mail'))->once();
    }

    public function test_an_applicant_who_has_not_confirmed_the_e_mail_is_asked_to_on_the_status_page_and_can_ask_for_the_link_again(): void
    {
        $unconfirmed = $this->applicant(verified: false);
        $this->actingAs($unconfirmed)->get(route('counselors.signup.waiting-approval'))->assertOk()
            ->assertSee('Please also confirm your e-mail address')->assertSee(route('verification.send'), false);

        Notification::fake();
        $this->actingAs($unconfirmed)->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($unconfirmed, VerifyEmail::class);

        $confirmed = $this->applicant(verified: true);
        $this->actingAs($confirmed)->get(route('counselors.signup.waiting-approval'))->assertOk()->assertDontSee('Please also confirm your e-mail address');
    }

    // ---- "we received your application" ----------------------------------------------------------------------

    public function test_the_applicant_is_told_once_when_all_three_documents_are_in_and_not_before(): void
    {
        Storage::fake('counselor_documents');
        Notification::fake();
        $user = $this->applicant();

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['cert']))->assertRedirect(route('counselors.signup.waiting-approval'));
        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['pa']));
        Notification::assertNotSentTo($user, CounselorApplicationReceived::class);

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['ic']));
        Notification::assertSentToTimes($user, CounselorApplicationReceived::class, 1);

        // Replacing a file later is not a new application: no second e-mail.
        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['ic']));
        Notification::assertSentToTimes($user, CounselorApplicationReceived::class, 1);
    }

    public function test_the_first_submission_is_audited_once_and_a_resubmission_has_its_own_entry(): void
    {
        Storage::fake('counselor_documents');
        Notification::fake();
        $user = $this->applicant();
        $profileId = $user->counselorProfile->id;
        $count = fn (string $action) => AuditLog::where('action', $action)->where('target_id', $profileId)->count();

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['cert', 'pa']));
        $this->assertSame(0, $count('counselor.application_submitted'), 'two of three documents: not submitted yet');

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['ic']));
        $this->assertSame(1, $count('counselor.application_submitted'));
        $entry = AuditLog::where('action', 'counselor.application_submitted')->sole();
        $this->assertSame(['attempt' => 1], $entry->metadata);
        $this->assertSame($user->id, $entry->actor_user_id);

        // Replacing a file is not a new submission.
        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['ic']));
        $this->assertSame(1, $count('counselor.application_submitted'));

        // After a rejection the new attempt is "resubmitted", not "submitted" again.
        $user->counselorProfile->update(['verification_status' => VerificationStatus::Rejected]);
        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['pa']));
        $this->assertSame(1, $count('counselor.application_submitted'));
        $this->assertSame(1, $count('counselor.resubmitted'));
    }

    public function test_the_applicant_is_told_again_when_they_send_documents_after_a_rejection(): void
    {
        Storage::fake('counselor_documents');
        Notification::fake();
        $user = $this->applicant(VerificationStatus::Rejected);

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['pa']));

        Notification::assertSentTo($user, CounselorApplicationReceived::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->subject, 'again') && $mail->actionUrl === route('counselors.signup.waiting-approval');
        });
    }

    public function test_the_received_e_mail_carries_no_number_and_no_document(): void
    {
        $user = $this->applicant();
        $mail = (new CounselorApplicationReceived)->toMail($user);
        $text = implode(' ', array_merge([$mail->subject], $mail->introLines, $mail->outroLines));

        foreach ([$user->counselorProfile->kb_number, $user->counselorProfile->pa_number, 'pdf', 'passport'] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
        $this->assertStringContainsString('1-2 business days', $text);
    }

    public function test_a_failing_e_mail_does_not_undo_the_submission(): void
    {
        Storage::fake('counselor_documents');
        Log::spy();
        $this->brokenMailServer();
        $user = $this->applicant();

        $this->actingAs($user)->post(route('counselors.signup.documents.store'), $this->files(['cert', 'pa', 'ic']))->assertRedirect(route('counselors.signup.waiting-approval'));

        $this->assertNotNull($user->counselorProfile->fresh()->submitted_at);
        $this->assertSame(3, $user->counselorProfile->documents()->count());
    }

    // ---- the support address -------------------------------------------------------------------------------

    public function test_the_application_pages_use_the_one_support_address_from_the_settings(): void
    {
        config(['landing.contact_email' => 'help@real-domain.test']);

        // The sign up form shows the support address in the box that explains a failed check; then the status page (for the applicant).
        $this->platform();
        $this->post(route('counselors.signup.store'), [
            'name' => 'Nobody', 'email' => 'nobody@example.test', 'kb_number' => 'KB99999', 'pa_number' => 'PA99999',
            'password' => 'Secret#Pass99', 'password_confirmation' => 'Secret#Pass99', 'confirm_accurate' => 1, 'confirm_terms' => 1,
        ])->assertSessionHasErrors('lkm');
        $this->assertStringContainsString('mailto:help@real-domain.test', $this->get(route('counselors.signup.create'))->assertOk()->getContent());

        $page = $this->actingAs($this->applicant())->get(route('counselors.signup.waiting-approval'))->assertOk()->getContent();

        $this->assertStringContainsString('mailto:help@real-domain.test', $page);
        $this->assertStringNotContainsString('support@mykaunsel.com', $page);
    }

    public function test_the_decision_and_status_e_mails_to_a_counselor_are_queued_and_sent_after_the_commit(): void
    {
        $notifications = [
            new \App\Notifications\CounselorApproved,
            new \App\Notifications\CounselorRejected('Please upload a clearer copy.', ['ID / passport']),
            new \App\Notifications\CounselorSuspended('A reason.', false),
            new \App\Notifications\CounselorReactivated,
        ];

        foreach ($notifications as $notification) {
            $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification, $notification::class);
            $this->assertTrue($notification->afterCommit, $notification::class.' is sent only after the database commit');
        }
    }
}
