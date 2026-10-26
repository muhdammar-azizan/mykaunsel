<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CounselorApproved;
use App\Notifications\CounselorRejected;
use App\Services\CounselorReviewService;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class CounselorDecisionTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function approve(string $kb, array $data = [])
    {
        return $this->as()->post(route('platform.counselors.approve', $this->profile($kb)), $data);
    }

    private function reject(string $kb, array $data)
    {
        return $this->as()->post(route('platform.counselors.reject', $this->profile($kb)), $data);
    }

    private function decisionLogs(CounselorProfile $profile)
    {
        return AuditLog::where('target_type', 'counselor')->where('target_id', $profile->id)->whereIn('action', ['counselor.approved', 'counselor.rejected'])->get();
    }

    private function secondAdmin(string $name = 'Admin Dua'): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    // ---- who may do this ---------------------------------------------------------------------

    public function test_only_platform_admins_can_use_the_review_routes(): void
    {
        $profile = $this->profile('KB11023');
        $doc = $profile->documents()->first();
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();

        $calls = [
            fn ($t) => $t->post(route('platform.counselors.approve', $profile)),
            fn ($t) => $t->post(route('platform.counselors.reject', $profile), ['reason' => 'Tidak lengkap sama sekali.']),
            fn ($t) => $t->post(route('platform.counselors.lkm-recheck', $profile)),
            fn ($t) => $t->patch(route('platform.counselors.documents.mark', [$profile, $doc]), ['status' => 'ok']),
            fn ($t) => $t->patchJson(route('platform.counselors.checklist', $profile), ['key' => 'documents_legible', 'checked' => true]),
            fn ($t) => $t->post(route('platform.counselors.notes.store', $profile), ['body' => 'Nota dari orang luar']),
        ];

        foreach ($calls as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->post(route('platform.counselors.approve', $profile))->assertRedirect();

        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status);
        $this->assertSame(0, $this->decisionLogs($profile)->count());
    }

    public function test_organization_counselors_have_no_approval_flow(): void
    {
        $ahmad = $this->profile('KB10234');
        $doc = CounselorDocument::create(['counselor_profile_id' => $ahmad->id, 'type' => 'cert', 'path' => 'x.pdf', 'uploaded_at' => now()]);

        $this->as()->post(route('platform.counselors.approve', $ahmad))->assertNotFound();
        $this->as()->post(route('platform.counselors.reject', $ahmad), ['reason' => 'Cuba tolak kaunselor organisasi.'])->assertNotFound();
        $this->as()->post(route('platform.counselors.lkm-recheck', $ahmad))->assertNotFound();
        $this->as()->patch(route('platform.counselors.documents.mark', [$ahmad, $doc]), ['status' => 'ok'])->assertNotFound();
        $this->as()->patchJson(route('platform.counselors.checklist', $ahmad), ['key' => 'documents_legible', 'checked' => true])->assertNotFound();
        $this->as()->post(route('platform.counselors.notes.store', $ahmad), ['body' => 'Nota'])->assertNotFound();

        $this->assertSame(VerificationStatus::Approved, $ahmad->fresh()->verification_status);
    }

    // ---- approve -----------------------------------------------------------------------------

    public function test_approving_lists_the_counselor_audits_and_emails_them(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');
        $this->assertFalse($profile->isListable());

        $this->approve('KB11023')->assertRedirect(route('platform.counselors.show', $profile))->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertSame($this->admin->id, $profile->reviewed_by);
        $this->assertNotNull($profile->reviewed_at);
        $this->assertTrue($profile->isListable(), 'approved + valid PA + active membership');

        $log = $this->decisionLogs($profile)->first();
        $this->assertSame('counselor.approved', $log->action);
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame('Kavitha a/p Muniandy', $log->target_name);
        $this->assertSame(['status' => ['old' => 'pending', 'new' => 'approved']], $log->metadata['changes']);
        $this->assertArrayNotHasKey('acknowledged_discrepancies', $log->metadata);

        Notification::assertSentTo($profile->user, CounselorApproved::class);
        Notification::assertSentTimes(CounselorApproved::class, 1);
    }

    public function test_the_approval_email_renders_and_sends(): void
    {
        $this->approve('KB11023')->assertSessionHas('status');

        $mail = (new CounselorApproved)->toMail($this->profile('KB11023')->user);
        $this->assertStringContainsString('approved', $mail->subject);
        $this->assertSame(route('login'), $mail->actionUrl);
    }

    public function test_approval_refreshes_the_pa_date_from_the_lkm_record_and_audits_old_to_new(): void
    {
        // Seed case: the LKM date changed after the applicant applied.
        $profile = $this->profile('KB11702');
        $record = LkmDirectorySnapshot::where('kb_number', 'KB11702')->first();
        $old = $profile->pa_valid_until->toDateString();
        $this->assertNotSame($old, $record->pa_valid_until->toDateString());

        Notification::fake();
        $this->approve('KB11702')->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame($record->pa_valid_until->toDateString(), $profile->pa_valid_until->toDateString());
        $log = $this->decisionLogs($profile)->first();
        $this->assertSame(['old' => $old, 'new' => $record->pa_valid_until->toDateString()], $log->metadata['changes']['pa_valid_until']);
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
    }

    public function test_the_last_day_of_the_pa_still_counts_at_approval(): void
    {
        Notification::fake();
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['pa_valid_until' => now()->toDateString()]);

        $this->approve('KB11023')->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Approved, $this->profile('KB11023')->verification_status);
    }

    public function test_approval_is_blocked_when_the_lkm_record_is_not_active(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');
        $date = $profile->pa_valid_until->toDateString();
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['status' => 'Digantung', 'pa_valid_until' => now()->addYears(5)->toDateString()]);

        $this->approve('KB11023')->assertSessionHas('error');

        $this->assertStringContainsString('Cannot approve yet', session('error'));
        $this->assertStringContainsString('not active', session('error'));
        $profile->refresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertSame($date, $profile->pa_valid_until->toDateString(), 'an inactive record never overwrites the profile');
        $this->assertSame(0, $this->decisionLogs($profile)->count());
        Notification::assertNothingSent();
    }

    public function test_approval_is_blocked_when_the_lkm_record_is_gone(): void
    {
        Notification::fake();
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->delete();

        $this->approve('KB11023')->assertSessionHas('error');

        $this->assertStringContainsString('No record for this KB number', session('error'));
        $this->assertSame(VerificationStatus::Pending, $this->profile('KB11023')->verification_status);
        Notification::assertNothingSent();
    }

    public function test_approval_is_blocked_when_the_pa_has_expired_and_nothing_is_changed_at_all(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');
        $date = $profile->pa_valid_until->toDateString();
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['pa_valid_until' => now()->subDay()->toDateString()]);

        $this->approve('KB11023')->assertSessionHas('error');

        $this->assertStringContainsString('expired', session('error'));
        $profile->refresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertSame($date, $profile->pa_valid_until->toDateString(), 'the date refresh was rolled back with the blocked approval');
        $this->assertSame(0, $this->decisionLogs($profile)->count());
        Notification::assertNothingSent();
    }

    public function test_a_name_or_pa_number_mismatch_needs_the_admin_to_acknowledge_it(): void
    {
        Notification::fake();
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['full_name' => 'Kavitha Muniandy']);
        $profile = $this->profile('KB11023');

        $this->approve('KB11023')->assertSessionHas('error');
        $this->assertStringContainsString('I have reviewed this discrepancy', session('error'));
        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status);

        $this->approve('KB11023', ['acknowledge' => 1])->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $log = $this->decisionLogs($profile)->first();
        $this->assertContains('The name does not match the LKM record.', $log->metadata['acknowledged_discrepancies']);
        Notification::assertSentTo($profile->user, CounselorApproved::class);
    }

    public function test_a_pa_number_mismatch_is_also_a_warning_not_a_block_and_the_number_is_never_overwritten(): void
    {
        Notification::fake();
        LkmDirectorySnapshot::where('kb_number', 'KB11489')->update(['pa_number' => 'PA99999']);

        $this->approve('KB11489')->assertSessionHas('error');
        $this->approve('KB11489', ['acknowledge' => 1])->assertSessionHas('status');

        $profile = $this->profile('KB11489');
        $this->assertSame('PA21490', $profile->pa_number, 'only the valid-until date is refreshed');
        $approved = $this->decisionLogs($profile)->firstWhere('action', 'counselor.approved');
        $this->assertContains('The PA number does not match the LKM record.', $approved->metadata['acknowledged_discrepancies']);
    }

    public function test_all_three_documents_must_be_on_file_and_none_marked_for_replacement(): void
    {
        Notification::fake();

        // KB11256 has its ID marked "needs replacement".
        $this->approve('KB11256')->assertSessionHas('error');
        $this->assertStringContainsString('ID / passport is marked', session('error'));
        $this->assertSame(VerificationStatus::Pending, $this->profile('KB11256')->verification_status);

        // A missing document.
        $this->profile('KB11023')->documents()->where('type', 'pa')->delete();
        $this->approve('KB11023')->assertSessionHas('error');
        $this->assertStringContainsString('PA certificate has not been uploaded', session('error'));

        // A document the retention rules already removed.
        $this->profile('KB11489')->documents()->where('type', 'ic')->update(['path' => null, 'purged_at' => now()]);
        $this->approve('KB11489')->assertSessionHas('error');
        $this->assertStringContainsString('ID / passport has not been uploaded', session('error'));

        Notification::assertNothingSent();
    }

    public function test_not_every_document_has_to_be_ticked_ok(): void
    {
        Notification::fake();
        $this->assertSame(0, $this->profile('KB11023')->documents()->where('status', 'ok')->count());

        $this->approve('KB11023')->assertSessionHas('status');
    }

    public function test_the_checklist_never_blocks_a_decision(): void
    {
        Notification::fake();
        $this->assertNull($this->profile('KB11023')->review_checklist);

        $this->approve('KB11023')->assertSessionHas('status');
    }

    // ---- reject ------------------------------------------------------------------------------

    public function test_rejecting_saves_the_reason_flags_the_documents_audits_and_emails(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');

        $this->reject('KB11023', ['reason' => 'The ID photo is too dark to read.', 'documents' => ['ic', 'cert']])
            ->assertRedirect(route('platform.counselors.show', $profile))->assertSessionHas('status');

        $profile->refresh();
        $this->assertSame(VerificationStatus::Rejected, $profile->verification_status);
        $this->assertSame('The ID photo is too dark to read.', $profile->rejection_reason);
        $this->assertSame($this->admin->id, $profile->reviewed_by);

        $docs = $profile->documents->keyBy('type');
        $this->assertTrue($docs['ic']->needsReplacement());
        $this->assertTrue($docs['cert']->needsReplacement());
        $this->assertFalse($docs['pa']->needsReplacement(), 'only the chosen documents');

        $log = AuditLog::where('action', 'counselor.rejected')->where('target_id', $profile->id)->firstOrFail();
        $this->assertSame('The ID photo is too dark to read.', $log->metadata['reason']);
        $this->assertEqualsCanonicalizing(['KB certificate', 'ID / passport'], $log->metadata['documents_to_replace']);
        $this->assertSame(2, AuditLog::where('action', 'counselor.document_flagged')->where('target_id', $profile->id)->count());

        Notification::assertSentTo($profile->user, CounselorRejected::class, function ($n) use ($profile) {
            $mail = $n->toMail($profile->user);
            $text = implode(' ', $mail->introLines);

            return str_contains($text, 'The ID photo is too dark to read.')
                && str_contains($text, 'ID / passport')
                && str_contains($text, 'KB certificate')
                && ! str_contains($text, 'PA certificate')
                && $mail->actionUrl === route('counselors.signup.documents');
        });
    }

    public function test_rejecting_without_choosing_documents_is_allowed(): void
    {
        Notification::fake();

        $this->reject('KB11023', ['reason' => 'Your application is incomplete.'])->assertSessionHas('status');

        $this->assertSame(0, $this->profile('KB11023')->documents()->where('status', 'needs_replacement')->count());
        Notification::assertSentTo($this->profile('KB11023')->user, CounselorRejected::class, fn ($n) => ! str_contains(implode(' ', $n->toMail($this->profile('KB11023')->user)->introLines), 'upload these documents'));
    }

    public function test_a_document_already_marked_is_not_logged_twice_and_missing_files_are_skipped(): void
    {
        Notification::fake();
        // KB11256's ID is already marked. Its PA certificate is removed.
        $this->profile('KB11256')->documents()->where('type', 'pa')->update(['path' => null, 'purged_at' => now()]);

        $this->reject('KB11256', ['reason' => 'Please send the documents again.', 'documents' => ['ic', 'pa']])->assertSessionHas('status');

        $log = AuditLog::where('action', 'counselor.rejected')->where('target_id', $this->profile('KB11256')->id)->firstOrFail();
        $this->assertSame(['ID / passport'], $log->metadata['documents_to_replace']);
        $this->assertSame(0, AuditLog::where('action', 'counselor.document_flagged')->where('target_id', $this->profile('KB11256')->id)->count(), 'the ID was already marked; nothing new to log');
    }

    public function test_a_rejection_reason_is_required_and_the_document_list_is_checked(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');

        foreach (['', '   ', 'short', str_repeat('a', 1001)] as $bad) {
            $this->from(route('platform.counselors.show', $profile))->as()->post(route('platform.counselors.reject', $profile), ['reason' => $bad])->assertSessionHasErrors('reason');
        }
        $this->as()->post(route('platform.counselors.reject', $profile), ['reason' => 'A proper long reason.', 'documents' => ['passport']])->assertSessionHasErrors('documents.0');
        $this->as()->post(route('platform.counselors.reject', $profile), ['reason' => 'A proper long reason.', 'documents' => 'ic'])->assertSessionHasErrors('documents');

        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status);
        $this->assertSame(0, $this->decisionLogs($profile)->count());
        Notification::assertNothingSent();
    }

    public function test_a_rejected_application_appears_on_the_rejected_tab_with_its_documents(): void
    {
        Notification::fake();
        $this->reject('KB11023', ['reason' => 'The KB certificate is cut off.', 'documents' => ['cert']]);

        $this->listPage(['tab' => 'rejected'])->assertSee('The KB certificate is cut off.')->assertSee('Documents to replace')->assertSee('KB certificate');
        $this->assertSame(['pending' => 3, 'active' => 5, 'suspended' => 2, 'rejected' => 2], $this->listPage()->viewData('counts'));
    }

    // ---- one wins ----------------------------------------------------------------------------

    public function test_two_admins_cannot_approve_and_reject_the_same_application(): void
    {
        Notification::fake();
        $other = $this->secondAdmin();
        $profile = $this->profile('KB11023');

        // Admin Dua approves first; the first admin still has the old page open and rejects.
        $this->as($other)->post(route('platform.counselors.approve', $profile))->assertSessionHas('status');
        $this->flushSession(); // a different person signs in (their password hash differs)
        $this->as()->post(route('platform.counselors.reject', $profile), ['reason' => 'Terlewat menolak permohonan ini.', 'documents' => ['ic']])
            ->assertRedirect(route('platform.counselors.show', $profile))->assertSessionHas('error');

        $this->assertStringContainsString('already approved', session('error'));
        $this->assertStringContainsString('Admin Dua', session('error'));
        $profile->refresh();
        $this->assertSame(VerificationStatus::Approved, $profile->verification_status);
        $this->assertSame($other->id, $profile->reviewed_by);
        $this->assertNull($profile->rejection_reason);
        $this->assertFalse($profile->documents()->where('status', 'needs_replacement')->exists(), 'the losing rejection flagged nothing');
        $this->assertSame(1, $this->decisionLogs($profile)->count());
        Notification::assertSentTimes(CounselorApproved::class, 1);
        Notification::assertNotSentTo($profile->user, CounselorRejected::class);
    }

    public function test_and_the_other_way_round_reject_first_then_approve(): void
    {
        Notification::fake();
        $other = $this->secondAdmin();
        $profile = $this->profile('KB11489');

        $this->as($other)->post(route('platform.counselors.reject', $profile), ['reason' => 'Dokumen tidak mencukupi.'])->assertSessionHas('status');
        $this->flushSession();
        $this->as()->post(route('platform.counselors.approve', $profile))->assertSessionHas('error');

        $this->assertStringContainsString('already rejected', session('error'));
        $this->assertSame(VerificationStatus::Rejected, $profile->fresh()->verification_status);
        $this->assertSame(2, $this->decisionLogs($profile)->count(), 'the seeded first rejection + this one; no approval');
        $this->assertSame(0, $this->decisionLogs($profile)->where('action', 'counselor.approved')->count());
        Notification::assertNotSentTo($profile->user, CounselorApproved::class);
    }

    public function test_a_decision_on_something_that_is_no_longer_pending_changes_nothing(): void
    {
        Notification::fake();
        $this->approve('KB11023')->assertSessionHas('status');

        $this->approve('KB11023')->assertSessionHas('error');
        $this->reject('KB11023', ['reason' => 'Cuba tolak yang sudah lulus.'])->assertSessionHas('error');

        $this->assertSame(1, $this->decisionLogs($this->profile('KB11023'))->count());
        Notification::assertSentTimes(CounselorApproved::class, 1);
    }

    // ---- all or nothing ----------------------------------------------------------------------

    public function test_approval_is_rolled_back_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11702'); // also has a date to refresh
        $date = $profile->pa_valid_until->toDateString();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            app(CounselorReviewService::class)->approve($profile, $this->admin);
            $this->fail('approve() should have failed');
        } catch (RuntimeException) {
        }

        $profile->refresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status, 'no log, no approval');
        $this->assertNull($profile->reviewed_by);
        $this->assertSame($date, $profile->pa_valid_until->toDateString(), 'the date refresh was rolled back too');
        Notification::assertNothingSent();
    }

    public function test_rejection_is_rolled_back_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            app(CounselorReviewService::class)->reject($profile, $this->admin, 'Dokumen tidak jelas sama sekali.', ['ic']);
            $this->fail('reject() should have failed');
        } catch (RuntimeException) {
        }

        $profile->refresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertNull($profile->rejection_reason);
        $this->assertFalse($profile->documents()->where('status', 'needs_replacement')->exists(), 'document marks rolled back too');
        Notification::assertNothingSent();
    }

    public function test_a_mail_failure_never_undoes_a_decision(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('mail server down'));

        $this->approve('KB11023')->assertSessionHas('status');

        $this->assertSame(VerificationStatus::Approved, $this->profile('KB11023')->verification_status);
        $this->assertSame(1, $this->decisionLogs($this->profile('KB11023'))->count());
    }

    // ---- the page ----------------------------------------------------------------------------

    public function test_the_decision_panel_and_modals_show_for_pending_applications_only(): void
    {
        $this->as()->get(route('platform.counselors.show', $this->profile('KB11023')))->assertOk()
            ->assertSee('Decision')->assertSee('Approve counselor')->assertSee('Reject application')
            ->assertSee('sent to the applicant')->assertSee('Documents unclear')->assertSee('LKM record mismatch')->assertSee('PA expired')
            ->assertSee('Which documents must they upload again')
            ->assertSee(route('platform.counselors.approve', $this->profile('KB11023')), false)
            ->assertSee(route('platform.counselors.reject', $this->profile('KB11023')), false)
            ->assertSee('Re-run LKM check')->assertSee('Review checklist')->assertSee('Internal notes');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertOk()
            ->assertDontSee('Approve counselor')->assertDontSee('Reject application')->assertDontSee('Re-run LKM check')
            ->assertSee('this application is closed');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB10234')))->assertOk()
            ->assertDontSee('Approve counselor')->assertDontSee('Review checklist')->assertDontSee('Internal notes')->assertDontSee('this application is closed');
    }

    public function test_the_page_lists_what_stands_in_the_way_and_asks_for_acknowledgement(): void
    {
        $this->as()->get(route('platform.counselors.show', $this->profile('KB11256')))
            ->assertSee('Cannot be approved yet')->assertSee('ID / passport is marked')->assertSee('Approval will be blocked until this is fixed');

        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['full_name' => 'Kavitha Muniandy']);
        $this->as()->get(route('platform.counselors.show', $this->profile('KB11023')))
            ->assertSee('I have reviewed this discrepancy')->assertSee('name="acknowledge"', false);

        $this->as()->get(route('platform.counselors.show', $this->profile('KB11489')))
            ->assertDontSee('I have reviewed this discrepancy')->assertDontSee('Cannot be approved yet');
    }

    public function test_the_activity_and_the_dashboard_show_the_approval(): void
    {
        Notification::fake();
        $this->approve('KB11023');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB11023')))->assertSee('Approved')->assertSee('Platform Administrator');
        $this->as()->get(route('platform.dashboard'))->assertSee('Kavitha a/p Muniandy');
    }

    public function test_an_approved_counselor_is_listed_in_the_active_tab(): void
    {
        Notification::fake();
        $this->approve('KB11023');

        $this->assertContains('KB11023', $this->kbs($this->listPage(['tab' => 'active'])));
        $this->assertSame(['pending' => 3, 'active' => 6, 'suspended' => 2, 'rejected' => 1], $this->listPage()->viewData('counts'));
    }
}
