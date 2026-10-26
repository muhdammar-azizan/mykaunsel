<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\CounselorReviewNote;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\CounselorApproved;
use App\Notifications\NewCounselorApplication;
use App\Services\CounselorTimeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class CounselorReviewToolsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function doc(string $kb, string $type): CounselorDocument
    {
        return $this->profile($kb)->documents()->where('type', $type)->firstOrFail();
    }

    private function mark(string $kb, string $type, array $data)
    {
        $profile = $this->profile($kb);

        return $this->as()->patch(route('platform.counselors.documents.mark', [$profile, $this->doc($kb, $type)]), $data);
    }

    private function secondAdmin(string $name = 'Admin Dua'): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->session['current_org_id'], 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    // ---- document marks ----------------------------------------------------------------------

    public function test_a_document_can_be_marked_needs_replacement_with_a_note_and_the_mark_is_audited(): void
    {
        $profile = $this->profile('KB11023');

        $this->mark('KB11023', 'pa', ['status' => 'needs_replacement', 'note' => '  The bottom of the page is cut off.  '])
            ->assertRedirect(route('platform.counselors.show', $profile).'#documents')->assertSessionHas('status');

        $doc = $this->doc('KB11023', 'pa');
        $this->assertSame(CounselorDocument::STATUS_NEEDS_REPLACEMENT, $doc->status);
        $this->assertSame('The bottom of the page is cut off.', $doc->note);

        $log = AuditLog::where('action', 'counselor.document_flagged')->where('target_id', $profile->id)->firstOrFail();
        $this->assertSame($this->admin->id, $log->actor_user_id);
        $this->assertSame('PA certificate', $log->metadata['document']);
        $this->assertSame($doc->id, $log->metadata['document_id']);
        $this->assertSame(['unreviewed', 'needs_replacement'], array_values($log->metadata['changes']['document_status']));
        $this->assertStringContainsString('The bottom of the page is cut off.', $log->metadata['summary']);
    }

    public function test_marking_it_ok_again_clears_the_replacement_mark(): void
    {
        $this->mark('KB11256', 'ic', ['status' => 'ok'])->assertSessionHas('status');

        $doc = $this->doc('KB11256', 'ic');
        $this->assertSame(CounselorDocument::STATUS_OK, $doc->status);
        $this->assertNull($doc->note, 'the old note is replaced by whatever was typed (nothing)');
        $this->assertFalse($doc->needsReplacement());
    }

    public function test_marks_are_validated(): void
    {
        $profile = $this->profile('KB11023');
        $back = fn () => $this->from(route('platform.counselors.show', $profile));

        $this->mark('KB11023', 'ic', ['status' => 'unreviewed'])->assertSessionHasErrors('status', null, 'document');
        $this->mark('KB11023', 'ic', ['status' => 'banana'])->assertSessionHasErrors('status', null, 'document');
        $this->mark('KB11023', 'ic', [])->assertSessionHasErrors('status', null, 'document');
        $this->mark('KB11023', 'ic', ['status' => 'ok', 'note' => str_repeat('a', 256)])->assertSessionHasErrors('note', null, 'document');

        $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $this->doc('KB11023', 'ic')->status);
        $this->assertSame(0, AuditLog::where('action', 'counselor.document_flagged')->count());
        $back();
    }

    public function test_only_pending_applications_with_a_file_can_be_marked(): void
    {
        $rejected = $this->profile('KB12391');
        $doc = $rejected->documents()->where('type', 'pa')->firstOrFail();
        $this->as()->patch(route('platform.counselors.documents.mark', [$rejected, $doc]), ['status' => 'needs_replacement'])
            ->assertSessionHas('error');
        $this->assertStringContainsString('no longer pending', session('error'));
        $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $doc->fresh()->status);

        $this->doc('KB11023', 'ic')->update(['path' => null, 'purged_at' => now()]);
        $this->mark('KB11023', 'ic', ['status' => 'ok'])->assertSessionHas('error');
        $this->assertStringContainsString('has no file to mark', session('error'));
    }

    public function test_a_document_must_belong_to_the_counselor_in_the_url(): void
    {
        $other = $this->doc('KB11256', 'pa');

        $this->as()->patch(route('platform.counselors.documents.mark', [$this->profile('KB11023'), $other]), ['status' => 'ok'])->assertNotFound();
    }

    public function test_marks_show_on_the_page_and_in_the_activity(): void
    {
        $this->mark('KB11023', 'pa', ['status' => 'needs_replacement', 'note' => 'Blurry scan']);

        $this->as()->get(route('platform.counselors.show', $this->profile('KB11023')))
            ->assertSee('Note: Blurry scan')->assertSee('Needs replacement')->assertSee('Document marked')->assertSee('PA certificate: needs replacement');
    }

    // ---- re-run the LKM check ----------------------------------------------------------------

    public function test_rerunning_the_check_refreshes_the_pa_date_from_the_record_and_audits_old_to_new(): void
    {
        $profile = $this->profile('KB11702'); // the LKM date changed after she applied
        $record = LkmDirectorySnapshot::where('kb_number', 'KB11702')->first();
        $old = $profile->pa_valid_until->toDateString();

        $this->as()->post(route('platform.counselors.lkm-recheck', $profile))
            ->assertRedirect(route('platform.counselors.show', $profile).'#lkm')->assertSessionHas('status');

        $this->assertStringContainsString('LKM check re-run: the record matches.', session('status'));
        $this->assertStringContainsString('updated from LKM', session('status'));
        $this->assertSame($record->pa_valid_until->toDateString(), $profile->fresh()->pa_valid_until->toDateString());

        $log = AuditLog::where('action', 'counselor.lkm_rechecked')->where('target_id', $profile->id)->firstOrFail();
        $this->assertSame(['old' => $old, 'new' => $record->pa_valid_until->toDateString()], $log->metadata['changes']['pa_valid_until']);
        $this->assertSame('match', $log->metadata['outcome']);
        $this->assertSame($this->admin->id, $log->actor_user_id);

        // The card now shows a clean match.
        $this->as()->get(route('platform.counselors.show', $profile))->assertSee('Matches LKM');
    }

    public function test_an_unverifiable_record_gives_a_clear_warning_and_is_never_written_over(): void
    {
        $profile = $this->profile('KB11023');
        $date = $profile->pa_valid_until->toDateString();

        LkmDirectorySnapshot::where('kb_number', 'KB11023')->delete();
        $this->as()->post(route('platform.counselors.lkm-recheck', $profile))->assertSessionHas('error');
        $this->assertStringContainsString('The LKM number could not be verified.', session('error'));
        $this->assertStringContainsString('No record for this KB number', session('error'));

        LkmDirectorySnapshot::create(['kb_number' => 'KB11023', 'pa_number' => 'PA21034', 'full_name' => 'Kavitha a/p Muniandy', 'status' => 'Tidak Aktif', 'pa_valid_from' => '2024-01-01', 'pa_valid_until' => now()->addYears(4)->toDateString()]);
        $this->as()->post(route('platform.counselors.lkm-recheck', $profile))->assertSessionHas('error');
        $this->assertStringContainsString('not active', session('error'));

        $this->assertSame($date, $profile->fresh()->pa_valid_until->toDateString(), 'an inactive record does not change the profile');
        $this->assertSame(2, AuditLog::where('action', 'counselor.lkm_rechecked')->where('target_id', $profile->id)->count(), 'every re-run is recorded');
        $this->assertSame('not_found', AuditLog::where('action', 'counselor.lkm_rechecked')->oldest('id')->first()->metadata['outcome']);
        $this->assertTrue(CounselorProfile::query()->lkmUnverified()->whereKey($profile->id)->exists(), 'it now counts for the Dashboard');
    }

    public function test_a_name_mismatch_is_reported_as_a_partial_match(): void
    {
        LkmDirectorySnapshot::where('kb_number', 'KB11023')->update(['full_name' => 'Kavitha Muniandy']);

        $this->as()->post(route('platform.counselors.lkm-recheck', $this->profile('KB11023')))->assertSessionHas('error');

        $this->assertStringContainsString('does not fully match', session('error'));
        $this->assertStringContainsString('The name does not match', session('error'));
    }

    public function test_the_check_can_only_be_run_while_the_application_is_pending(): void
    {
        $this->as()->post(route('platform.counselors.lkm-recheck', $this->profile('KB12391')))->assertSessionHas('error');

        $this->assertStringContainsString('no longer pending', session('error'));
        $this->assertSame(0, AuditLog::where('action', 'counselor.lkm_rechecked')->count());
    }

    // ---- checklist ---------------------------------------------------------------------------

    private function tick(string $kb, string $key, bool $checked, ?User $as = null)
    {
        return $this->as($as)->patchJson(route('platform.counselors.checklist', $this->profile($kb)), ['key' => $key, 'checked' => $checked]);
    }

    public function test_ticking_a_box_saves_it_with_who_and_when_and_untick_clears_it(): void
    {
        $this->tick('KB11023', 'documents_legible', true)->assertOk()->assertJson(['checked' => true, 'by' => 'Platform Administrator']);

        $saved = $this->profile('KB11023')->review_checklist;
        $this->assertSame($this->admin->id, $saved['documents_legible']['by']);

        $this->tick('KB11023', 'documents_legible', false)->assertOk();
        $this->assertNull($this->profile('KB11023')->review_checklist);
    }

    public function test_other_admins_see_the_ticks_and_two_admins_keep_both_boxes(): void
    {
        $other = $this->secondAdmin();

        $this->tick('KB11023', 'name_matches_lkm', true);
        $this->flushSession();
        $this->tick('KB11023', 'pa_not_expired', true, $other);

        $saved = $this->profile('KB11023')->review_checklist;
        $this->assertSame([$this->admin->id, $other->id], [$saved['name_matches_lkm']['by'], $saved['pa_not_expired']['by']]);

        $this->flushSession();
        $this->as($other)->get(route('platform.counselors.show', $this->profile('KB11023')))
            ->assertSee('Review checklist')->assertSee('Name matches LKM')->assertSee('Documents legible')->assertSee('No duplicate KB')->assertSee('PA not expired')
            ->assertSee('Checked by', false)->assertSee('Platform Administrator');
    }

    public function test_unknown_keys_are_refused_and_a_decided_application_has_a_closed_checklist(): void
    {
        $this->as()->patchJson(route('platform.counselors.checklist', $this->profile('KB11023')), ['key' => 'everything_ok', 'checked' => true])->assertStatus(422);
        $this->as()->patchJson(route('platform.counselors.checklist', $this->profile('KB11023')), ['key' => 'pa_not_expired'])->assertStatus(422);

        $this->tick('KB12391', 'pa_not_expired', true)->assertStatus(409);
        $this->assertNull($this->profile('KB12391')->review_checklist);

        $this->as()->get(route('platform.counselors.show', $this->profile('KB12391')))->assertSee('Closed — this application has been decided.');
    }

    // ---- notes -------------------------------------------------------------------------------

    public function test_a_note_is_saved_audited_by_reference_and_shown_with_author_and_time(): void
    {
        $profile = $this->profile('KB11023');

        $this->as()->post(route('platform.counselors.notes.store', $profile), ['body' => '  Called her — the PA copy will be re-sent tomorrow.  '])
            ->assertRedirect(route('platform.counselors.show', $profile).'#notes')->assertSessionHas('status', 'Note added.');

        $note = CounselorReviewNote::where('counselor_profile_id', $profile->id)->firstOrFail();
        $this->assertSame('Called her — the PA copy will be re-sent tomorrow.', $note->body);
        $this->assertSame($this->admin->id, $note->user_id);

        $log = AuditLog::where('action', 'counselor.note_added')->where('target_id', $profile->id)->firstOrFail();
        $this->assertSame(['note_id' => $note->id], $log->metadata, 'the note text is not copied into the audit log');
        $this->assertStringNotContainsString('re-sent', json_encode($log->toArray()));

        $this->as()->get(route('platform.counselors.show', $profile))
            ->assertSee('Called her')->assertSee('Platform Administrator')->assertSee('Only Platform Admins can see these')->assertSee('Internal note added');
    }

    public function test_an_empty_or_oversized_note_is_refused(): void
    {
        $profile = $this->profile('KB11023');

        foreach (['', '   ', str_repeat('a', 2001)] as $bad) {
            $this->as()->post(route('platform.counselors.notes.store', $profile), ['body' => $bad])->assertSessionHasErrors('body', null, 'note');
        }

        $this->assertSame(0, CounselorReviewNote::count());
    }

    public function test_notes_are_never_visible_to_the_applicant(): void
    {
        $profile = $this->profile('KB11023');
        $this->as()->post(route('platform.counselors.notes.store', $profile), ['body' => 'RAHSIA-DALAMAN-789']);
        Notification::fake();
        $this->as()->post(route('platform.counselors.reject', $profile), ['reason' => 'Dokumen perlu dihantar semula.', 'documents' => ['ic']]);

        $this->flushSession();
        $this->actingAs($profile->user)
            ->get(route('counselors.signup.waiting-approval'))->assertOk()
            ->assertDontSee('RAHSIA-DALAMAN-789')->assertDontSee('Internal notes');
    }

    // ---- the activity ------------------------------------------------------------------------

    public function test_the_activity_lists_every_review_step_newest_first_with_who_did_it(): void
    {
        Notification::fake();
        $profile = $this->profile('KB11023');

        $this->as()->post(route('platform.counselors.notes.store', $profile), ['body' => 'Satu nota']);
        $this->mark('KB11023', 'ic', ['status' => 'ok']);
        $this->as()->post(route('platform.counselors.lkm-recheck', $profile));
        $this->as()->post(route('platform.counselors.approve', $profile));

        $events = app(CounselorTimeline::class)->for($profile->fresh());
        $titles = $events->pluck('title')->all();

        $this->assertSame(['Approved', 'LKM check re-run', 'Document marked', 'Internal note added'], array_slice($titles, 0, 4));
        $this->assertSame('Application submitted', end($titles));
        $this->assertSame('Platform Administrator', $events[0]['actor']);
        Notification::assertSentTimes(CounselorApproved::class, 1);
    }

    // ---- the applicant's side ----------------------------------------------------------------

    private function newApplicant(string $kb = 'KB12624'): User
    {
        $platform = $this->session['current_org_id'];
        $record = LkmDirectorySnapshot::where('kb_number', $kb)->firstOrFail();
        $user = User::factory()->create(['name' => $record->full_name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $platform, 'role' => MembershipRole::Counselor, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => $kb, 'pa_number' => $record->pa_number, 'pa_valid_until' => $record->pa_valid_until,
            'verification_type' => 'platform_verified', 'verification_status' => 'pending',
        ]);

        return $user;
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 50, 'application/pdf');
    }

    public function test_admins_hear_about_a_new_application_once_when_the_third_document_arrives(): void
    {
        Notification::fake();
        $applicant = $this->newApplicant();
        $profile = $applicant->counselorProfile;

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('a.pdf')]);
        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['pa' => $this->pdf('b.pdf')]);
        Notification::assertNothingSent();
        $this->assertNull($profile->fresh()->submitted_at);

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['ic' => $this->pdf('c.pdf')]);
        Notification::assertSentTo($this->admin, NewCounselorApplication::class, function ($n) use ($profile) {
            $mail = $n->toMail($this->admin);

            return str_contains($mail->subject, $profile->user->name)
                && $mail->actionUrl === route('platform.counselors.show', $profile);
        });
        Notification::assertSentTimes(NewCounselorApplication::class, 1);
        $this->assertNotNull($profile->fresh()->submitted_at);
        $this->assertSame(1, $profile->fresh()->submission_count);

        // Replacing a file while still pending does not send another.
        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['ic' => $this->pdf('c2.pdf')]);
        Notification::assertSentTimes(NewCounselorApplication::class, 1);
    }

    public function test_the_admin_notification_respects_each_admins_setting(): void
    {
        Notification::fake();
        $off = $this->secondAdmin('Admin Off');
        $off->update(['notification_preferences' => ['new_counselor_application' => false]]);
        $other = $this->secondAdmin('Admin On');
        $applicant = $this->newApplicant();

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('a.pdf'), 'pa' => $this->pdf('b.pdf'), 'ic' => $this->pdf('c.pdf')]);

        Notification::assertSentTo([$this->admin, $other], NewCounselorApplication::class);
        Notification::assertNotSentTo($off, NewCounselorApplication::class);
    }

    public function test_resubmitting_after_a_rejection_counts_an_attempt_is_audited_and_notifies_admins(): void
    {
        Notification::fake();
        $profile = $this->profile('KB12391'); // rejected, its KB certificate marked, attempt 1
        $applicant = $profile->user;
        $this->assertSame(1, $profile->submission_count);

        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['cert' => $this->pdf('right-certificate.pdf')])
            ->assertRedirect(route('counselors.signup.waiting-approval'));

        $profile->refresh();
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertSame(2, $profile->submission_count);
        $this->assertNull($profile->rejection_reason);
        $this->assertTrue($profile->submitted_at->isToday());
        $this->assertSame(CounselorDocument::STATUS_UNREVIEWED, $profile->documents()->where('type', 'cert')->first()->status, 'the replaced document starts un-reviewed');

        $log = AuditLog::where('action', 'counselor.resubmitted')->where('target_id', $profile->id)->firstOrFail();
        $this->assertSame($applicant->id, $log->actor_user_id);
        $this->assertSame(2, $log->metadata['attempt']);
        $this->assertSame(['old' => 'rejected', 'new' => 'pending'], $log->metadata['changes']['status']);

        Notification::assertSentTo($this->admin, NewCounselorApplication::class, fn ($n) => str_contains($n->toMail($this->admin)->subject, 'resubmitted'));
        Notification::assertSentTimes(NewCounselorApplication::class, 1);

        // Back in the queue as "Resubmitted", with the history in the activity.
        $this->flushSession(); // the admin signs in
        $this->as()->get(route('platform.counselors.show', $profile))->assertSee('Resubmitted (attempt 2)')->assertSee('Resubmitted documents');
        $this->flushSession();

        // A second resubmission counts again, from the stored number.
        $profile->transitionTo(VerificationStatus::Rejected, ['rejection_reason' => 'Lagi sekali tidak jelas.']);
        $this->actingAs($applicant)->post(route('counselors.signup.documents.store'), ['pa' => $this->pdf('pa-again.pdf')]);
        $this->assertSame(3, $profile->fresh()->submission_count);
    }

    public function test_an_applicant_who_uploads_nothing_changes_nothing(): void
    {
        Notification::fake();
        $profile = $this->profile('KB12391');

        $this->actingAs($profile->user)->post(route('counselors.signup.documents.store'), []);

        $this->assertSame(VerificationStatus::Rejected, $profile->fresh()->verification_status);
        $this->assertSame(1, $profile->fresh()->submission_count);
        Notification::assertNothingSent();
        $this->assertSame(0, AuditLog::where('action', 'counselor.resubmitted')->count());
    }
}
