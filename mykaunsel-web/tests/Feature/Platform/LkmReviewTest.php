<?php

namespace Tests\Feature\Platform;

use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\LkmReviewRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\LkmReviewApproved;
use App\Notifications\LkmReviewRejected;
use App\Services\LkmReviewService;
use Database\Seeders\CounselorReviewSeeder;
use Database\Seeders\LkmReviewRequestSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Feature\Counselor\CounselorTestCase;

class LkmReviewTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('lkm_review_evidence');
        Notification::fake();
        $this->seedSamples();
        $this->seed(LkmReviewRequestSeeder::class);
    }

    private function record(string $kb): LkmDirectorySnapshot
    {
        return LkmDirectorySnapshot::where('kb_number', $kb)->firstOrFail();
    }

    private function pendingFor(string $kb): LkmReviewRequest
    {
        return LkmReviewRequest::pending()->where('counselor_profile_id', $this->profile($kb)->id)->firstOrFail();
    }

    private function disk()
    {
        return Storage::disk('lkm_review_evidence');
    }

    private function pdf(string $name = 'proof.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, CounselorReviewSeeder::pdf('Renewed certificate'));
    }

    private function log(string $action)
    {
        return AuditLog::where('action', $action)->latest('id')->first();
    }

    /** A fresh pending request for a counselor who has none, made the way the counselor portal will. */
    private function request(string $kb, array $claimed, ?UploadedFile $evidence = null): LkmReviewRequest
    {
        return app(LkmReviewService::class)->request($this->profile($kb), $claimed, $evidence ?? $this->pdf());
    }

    private function approve(LkmReviewRequest $review, array $values = [])
    {
        return $this->as()->post(route('platform.lkm.reviews.approve', $review), $values + ['pa_valid_until' => $review->claimed['pa_valid_until']]);
    }

    // ---- access ----------------------------------------------------------------------------

    public function test_only_platform_admins_can_see_or_decide_review_requests_or_open_the_evidence(): void
    {
        $review = $this->pendingFor('KB11935');
        $orgAdmin = User::where('email', 'afif@umpsa.edu.my')->firstOrFail();
        $umpsa = Organization::where('name', 'like', 'Universiti Malaysia Pahang%')->firstOrFail();
        $signed = URL::temporarySignedRoute('platform.lkm.reviews.evidence', now()->addMinutes(2), ['review' => $review->id, 'u' => $orgAdmin->id]);

        foreach ([
            fn ($t) => $t->get(route('platform.lkm.reviews')),
            fn ($t) => $t->get(route('platform.lkm.reviews', ['view' => 'resolved'])),
            fn ($t) => $t->post(route('platform.lkm.reviews.approve', $review), ['pa_valid_until' => '2030-01-01']),
            fn ($t) => $t->post(route('platform.lkm.reviews.reject', $review), ['reason' => 'Not clear enough to read.']),
            fn ($t) => $t->get(route('platform.lkm.reviews.evidence.open', $review)),
            fn ($t) => $t->get($signed),
        ] as $call) {
            $this->flushSession();
            $call($this->actingAs($orgAdmin)->withSession(['current_org_id' => $umpsa->id, 'current_role' => 'org_admin']))->assertForbidden();
        }

        $this->flushSession();
        auth()->logout();
        $this->get(route('platform.lkm.reviews'))->assertRedirect();
        $this->get($signed)->assertRedirect();

        $this->assertTrue($review->fresh()->isPending());
        $this->assertSame(0, AuditLog::where('action', 'lkm.evidence_viewed')->count());
    }

    // ---- the page ------------------------------------------------------------------------------

    public function test_the_directory_page_has_a_review_requests_tab_with_the_pending_count(): void
    {
        $this->assertSame(2, LkmReviewRequest::pendingCount());

        $this->as()->get(route('platform.lkm'))->assertOk()->assertSee('Review Requests')->assertSee(route('platform.lkm.reviews'), false);
        $this->as()->get(route('platform.lkm.reviews'))->assertOk()->assertSee('Review Requests')->assertSee('Simulated data.');
    }

    public function test_pending_cards_compare_the_current_value_with_the_claimed_one_longest_wait_first(): void
    {
        $response = $this->as()->get(route('platform.lkm.reviews'))->assertOk();

        $reviews = $response->viewData('reviews')->getCollection();
        $this->assertSame(['KB13090', 'KB11935'], $reviews->map(fn ($r) => $r->counselorProfile->kb_number)->all(), 'requested 4 days ago, then 1 day ago');

        $current = $this->record('KB11935')->pa_valid_until->format('j M Y');
        $claimed = Carbon::parse($this->pendingFor('KB11935')->claimed['pa_valid_until'])->format('j M Y');

        $response->assertSee('Current in system')->assertSee('Claimed by counselor')->assertSee('PA valid until')
            ->assertSee($current)->assertSee($claimed)
            ->assertSee('View evidence')->assertSee('renewed-practising-certificate.pdf')
            ->assertSee('waiting')->assertSee('Update record')->assertSee('Reject')
            ->assertSee(route('platform.counselors.show', $this->profile('KB11935')), false)
            ->assertSee('Suspended: PA expired');
    }

    public function test_the_evidence_link_is_the_authenticated_open_route_never_a_file_address(): void
    {
        $review = $this->pendingFor('KB11935');
        $html = $this->as()->get(route('platform.lkm.reviews'))->getContent();

        $this->assertStringContainsString(route('platform.lkm.reviews.evidence.open', $review), $html);
        $this->assertStringNotContainsString($review->evidence_path, $html);
        $this->assertStringNotContainsString('storage/', preg_replace('/[^"]*lkm-review-evidence[^"]*/', '', $html));
    }

    public function test_the_empty_states(): void
    {
        LkmReviewRequest::query()->delete();

        $this->as()->get(route('platform.lkm.reviews'))->assertOk()->assertSee('No pending review requests from counselors.');
        $this->as()->get(route('platform.lkm.reviews', ['view' => 'resolved']))->assertOk()->assertSee('No review requests have been decided yet.');
    }

    public function test_the_resolved_tab_shows_who_decided_when_why_and_what_changed(): void
    {
        $response = $this->as()->get(route('platform.lkm.reviews', ['view' => 'resolved']))->assertOk();

        $this->assertSame(['KB10891', 'KB11935', 'KB12168'], $response->viewData('reviews')->getCollection()->map(fn ($r) => $r->counselorProfile->kb_number)->sort()->values()->all());

        $rejected = LkmReviewRequest::where('status', 'rejected')->firstOrFail();
        $approved = LkmReviewRequest::where('status', 'approved')->where('counselor_profile_id', $this->profile('KB10891')->id)->firstOrFail();

        $response->assertSee('Rejected')->assertSee('Record updated')
            ->assertSee($this->admin->name)->assertSee($rejected->decision_reason)
            ->assertSee('Before')->assertSee('After')
            ->assertSee(Carbon::parse($approved->before['pa_valid_until'])->format('j M Y'))
            ->assertSee(Carbon::parse($approved->after['pa_valid_until'])->format('j M Y'));
    }

    public function test_a_resolved_request_for_a_counselor_who_is_still_suspended_links_to_the_counselors_page(): void
    {
        $this->as()->get(route('platform.lkm.reviews', ['view' => 'resolved']))
            ->assertSee('still suspended')->assertSee('Reactivate on the Counselors page')
            ->assertSee(route('platform.counselors.show', $this->profile('KB12168')).'#manage', false);
    }

    public function test_removed_evidence_is_said_so_and_has_no_link(): void
    {
        $purged = LkmReviewRequest::whereNotNull('evidence_purged_at')->firstOrFail();

        $html = $this->as()->get(route('platform.lkm.reviews', ['view' => 'resolved']))->assertSee('Evidence removed')->getContent();

        $this->assertStringNotContainsString(route('platform.lkm.reviews.evidence.open', $purged), $html);
    }

    public function test_an_unknown_tab_means_pending_and_a_page_past_the_end_goes_to_the_last(): void
    {
        $this->assertSame('pending', $this->as()->get(route('platform.lkm.reviews', ['view' => 'bogus']))->viewData('view'));
        $this->as()->get(route('platform.lkm.reviews', ['page' => 9]))->assertRedirect(route('platform.lkm.reviews', ['view' => 'pending', 'page' => 1]));
    }

    // ---- evidence ------------------------------------------------------------------------------

    public function test_the_evidence_opens_only_through_a_short_signed_link_and_each_viewing_is_audited(): void
    {
        $review = $this->pendingFor('KB11935');

        $redirect = $this->as()->get(route('platform.lkm.reviews.evidence.open', $review));
        $redirect->assertRedirect();
        $url = $redirect->headers->get('Location');

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString("u={$this->admin->id}", $url);
        $this->assertSame(0, AuditLog::where('action', 'lkm.evidence_viewed')->count(), 'handing out a link is not a viewing');

        $response = $this->as()->get($url)->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));

        $log = $this->log('lkm.evidence_viewed');
        $this->assertSame([$this->admin->id, 'counselor', $review->counselor_profile_id, $review->id], [$log->actor_user_id, $log->target_type, $log->target_id, $log->metadata['review_id']]);
        $this->assertStringNotContainsString($review->evidence_path, json_encode($log->metadata));

        // A browser's PDF viewer asking for pieces does not count as another viewing.
        $this->as()->get($url, ['Range' => 'bytes=0-10'])->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'lkm.evidence_viewed')->count());
    }

    public function test_a_link_does_not_work_for_another_admin_or_after_it_expires_or_when_changed(): void
    {
        $review = $this->pendingFor('KB11935');
        $other = URL::temporarySignedRoute('platform.lkm.reviews.evidence', now()->addMinutes(2), ['review' => $review->id, 'u' => $this->admin->id + 999]);
        $this->as()->get($other)->assertForbidden();

        $mine = URL::temporarySignedRoute('platform.lkm.reviews.evidence', now()->addMinutes(2), ['review' => $review->id, 'u' => $this->admin->id]);
        $this->as()->get($mine.'&x=1')->assertForbidden();
        $this->as()->get(route('platform.lkm.reviews.evidence', ['review' => $review->id, 'u' => $this->admin->id]))->assertForbidden();

        Carbon::setTestNow(now()->addMinutes(5));
        $this->as()->get($mine)->assertForbidden();

        $this->assertSame(0, AuditLog::where('action', 'lkm.evidence_viewed')->count());
    }

    public function test_there_is_nothing_to_open_when_the_evidence_was_removed_or_the_file_is_missing(): void
    {
        $purged = LkmReviewRequest::whereNotNull('evidence_purged_at')->firstOrFail();
        $this->as()->get(route('platform.lkm.reviews.evidence.open', $purged))->assertStatus(410);

        $review = $this->pendingFor('KB11935');
        $this->disk()->delete($review->evidence_path);
        $url = URL::temporarySignedRoute('platform.lkm.reviews.evidence', now()->addMinutes(2), ['review' => $review->id, 'u' => $this->admin->id]);
        $this->as()->get($url)->assertNotFound();
        $this->assertSame(0, AuditLog::where('action', 'lkm.evidence_viewed')->count());
    }

    public function test_the_evidence_disk_is_private(): void
    {
        $disk = config('filesystems.disks.lkm_review_evidence');

        $this->assertSame('private', $disk['visibility']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertStringNotContainsString(storage_path('app/public'), $disk['root']);
        $this->assertStringNotContainsString(public_path(), $disk['root']);
        $this->assertSame('lkm_review_evidence', config('lkm.review.disk'));
    }

    // ---- approving -------------------------------------------------------------------------------

    public function test_updating_the_record_changes_it_refreshes_the_profile_and_tells_the_counselor(): void
    {
        $review = $this->pendingFor('KB11935');
        $profile = $this->profile('KB11935');
        $old = $this->record('KB11935')->pa_valid_until->toDateString();
        $new = $review->claimed['pa_valid_until'];

        $this->approve($review)->assertRedirect(route('platform.lkm.reviews'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'KB11935 updated') && str_contains($m, 'has been emailed') && ! str_contains($m, 'suspended'));

        $this->assertSame($new, $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame($new, $profile->fresh()->pa_valid_until->toDateString(), 'the profile takes the date from the record');
        $this->assertSame(VerificationStatus::Approved, $profile->fresh()->verification_status);

        $review->refresh();
        $this->assertSame([LkmReviewRequest::APPROVED, null, $this->admin->id], [$review->status, $review->pending_for, $review->reviewed_by]);
        $this->assertNotNull($review->reviewed_at);
        $this->assertSame([['pa_valid_until' => $old], ['pa_valid_until' => $new]], [$review->before, $review->after]);
        $this->assertSame(1, LkmReviewRequest::pending()->count(), 'only the other counselor is still waiting');

        $recordLog = $this->log('lkm.record_updated');
        $this->assertSame(['lkm_record', ['pa_valid_until' => ['old' => $old, 'new' => $new]], $review->id], [$recordLog->target_type, $recordLog->metadata['changes'], $recordLog->metadata['review_id']]);

        $log = $this->log('lkm.review_approved');
        $this->assertSame([$this->admin->id, 'counselor', $profile->id, $review->id, false], [$log->actor_user_id, $log->target_type, $log->target_id, $log->metadata['review_id'], $log->metadata['still_suspended']]);
        $this->assertSame(['pa_valid_until' => ['old' => $old, 'new' => $new]], $log->metadata['profile_changes']);

        Notification::assertSentTo($profile->user, LkmReviewApproved::class, fn ($n) => str_contains($n->toMail($profile->user)->render(), Carbon::parse($new)->format('j M Y')));
    }

    public function test_the_admin_can_adjust_the_date_and_that_is_what_is_saved(): void
    {
        $review = $this->pendingFor('KB11935');
        $adjusted = now()->addYears(3)->toDateString();

        $this->approve($review, ['pa_valid_until' => $adjusted])->assertSessionHas('status');

        $this->assertSame($adjusted, $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame($adjusted, $review->fresh()->after['pa_valid_until']);
        $this->assertNotSame($review->claimed['pa_valid_until'], $adjusted);
    }

    public function test_a_suspended_counselor_stays_suspended_but_is_told_and_the_admin_is_pointed_to_the_counselors_page(): void
    {
        $review = $this->pendingFor('KB13090');
        $profile = $this->profile('KB13090');
        $this->assertSame(VerificationStatus::Suspended, $profile->verification_status);

        $this->approve($review)->assertSessionHas('status', fn ($m) => str_contains($m, 'still suspended') && str_contains($m, 'Counselors page'));

        $fresh = $profile->fresh();
        $this->assertSame(VerificationStatus::Suspended, $fresh->verification_status, 'NOT reactivated');
        $this->assertSame('system', $fresh->suspension_source);
        $this->assertNotNull($fresh->suspended_at);
        $this->assertSame($review->claimed['pa_valid_until'], $fresh->pa_valid_until->toDateString(), 'but the profile is refreshed');
        $this->assertSame(0, AuditLog::where('action', 'counselor.reactivated')->count());
        $this->assertTrue($this->log('lkm.review_approved')->metadata['still_suspended']);

        Notification::assertSentTo($profile->user, LkmReviewApproved::class, fn ($n) => str_contains($n->toMail($profile->user)->render(), 'still suspended'));

        // The resolved card says what to do next.
        $this->as()->get(route('platform.lkm.reviews', ['view' => 'resolved']))->assertSee(route('platform.counselors.show', $profile).'#manage', false);

        // The next step is the admin's own, on the Counselors page, and it works now.
        $this->as()->post(route('platform.counselors.reactivate', $profile))->assertSessionHas('status');
        $this->assertSame(VerificationStatus::Approved, $profile->fresh()->verification_status);
    }

    public function test_a_record_that_is_not_active_is_updated_but_the_profile_is_not_refreshed(): void
    {
        $review = $this->pendingFor('KB11935');
        $profile = $this->profile('KB11935');
        $before = $profile->pa_valid_until->toDateString();
        $this->record('KB11935')->update(['status' => 'Tidak Aktif']);

        $this->approve($review)->assertSessionHas('status');

        $this->assertSame($review->claimed['pa_valid_until'], $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame($before, $profile->fresh()->pa_valid_until->toDateString());
        $this->assertNull($this->log('lkm.review_approved')->metadata['profile_changes'] ?? null);
    }

    public function test_dates_that_make_no_sense_are_refused_and_nothing_changes(): void
    {
        $review = $this->pendingFor('KB11935');
        $date = $this->record('KB11935')->pa_valid_until->toDateString();
        $from = $this->record('KB11935')->pa_valid_from->toDateString();
        $entries = AuditLog::count();

        foreach ([
            ['yesterday', now()->subDay()->toDateString(), 'already passed'],
            ['before the certificate starts', Carbon::parse($from)->subDay()->toDateString(), 'cannot be before the certificate starts'],
            ['the same as the record', $date, 'nothing to update'],
        ] as [$case, $value, $message]) {
            $this->approve($review, ['pa_valid_until' => $value])->assertSessionHas('error', fn ($m) => str_contains($m, $message));
        }

        $this->as()->post(route('platform.lkm.reviews.approve', $review), ['pa_valid_until' => '31/12/2030'])->assertSessionHasErrors('pa_valid_until');
        $this->as()->post(route('platform.lkm.reviews.approve', $review), [])->assertSessionHasErrors('pa_valid_until');

        $this->assertTrue($review->fresh()->isPending());
        $this->assertSame($date, $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame($entries, AuditLog::count(), 'nothing was logged either');
        Notification::assertNothingSent();
    }

    public function test_only_the_claimed_fields_can_be_changed_and_a_claimed_name_or_pa_number_is_applied_to_the_record_only(): void
    {
        // Only the date was claimed: other fields in the request are ignored.
        $review = $this->pendingFor('KB11935');
        $record = $this->record('KB11935');
        $name = $record->full_name;
        $this->approve($review, ['full_name' => 'Somebody Else', 'pa_number' => 'PA00000'])->assertSessionHas('status');
        $this->assertSame([$name, $record->pa_number], [$this->record('KB11935')->full_name, $this->record('KB11935')->pa_number]);

        // A claim about the name and the PA number too.
        $profile = $this->profile('KB10234');
        $userName = $profile->user->name;
        $second = $this->request('KB10234', ['pa_valid_until' => now()->addYears(2)->toDateString(), 'pa_number' => 'pa77777', 'full_name' => 'Ahmad Zulkarnain Yusof']);
        $this->assertSame(['pa_valid_until', 'pa_number', 'full_name'], $second->claimedFields());

        $this->approve($second, ['pa_number' => 'PA88888', 'full_name' => 'Ahmad Zulkarnain bin Yusof'])->assertSessionHas('status');

        $this->assertSame(['PA88888', 'Ahmad Zulkarnain bin Yusof'], [$this->record('KB10234')->pa_number, $this->record('KB10234')->full_name]);
        $this->assertSame($userName, $profile->fresh()->user->name, 'the counselor account itself is never renamed');
        $this->assertSame(['pa_valid_until', 'pa_number'], array_keys($this->log('lkm.record_updated')->metadata['changes']), 'the name was set back to what it already was');
    }

    public function test_the_second_admin_to_decide_is_told_it_was_already_done_and_nothing_happens_twice(): void
    {
        $review = $this->pendingFor('KB11935');

        $this->approve($review)->assertSessionHas('status');
        $this->approve($review, ['pa_valid_until' => now()->addYears(5)->toDateString()])->assertSessionHas('error', fn ($m) => str_contains($m, 'already approved') && str_contains($m, $this->admin->name));
        $this->as()->post(route('platform.lkm.reviews.reject', $review), ['reason' => 'Changed my mind about this one.'])->assertSessionHas('error', fn ($m) => str_contains($m, 'already approved'));

        $this->assertSame(1, AuditLog::where('action', 'lkm.review_approved')->where('metadata->review_id', $review->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'lkm.review_rejected')->count(), 'only the one from the sample data');
        $this->assertSame($review->claimed['pa_valid_until'], $this->record('KB11935')->pa_valid_until->toDateString());
        Notification::assertSentToTimes($this->profile('KB11935')->user, LkmReviewApproved::class, 1);
    }

    public function test_approving_is_all_or_nothing_when_the_audit_entry_fails(): void
    {
        $review = $this->pendingFor('KB11935');
        $profile = $this->profile('KB11935');
        $date = $this->record('KB11935')->pa_valid_until->toDateString();
        $profileDate = $profile->pa_valid_until->toDateString();

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->approve($review);
            $this->fail('the request should have failed');
        } catch (RuntimeException) {
        }

        $this->assertTrue($review->fresh()->isPending());
        $this->assertSame($date, $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame($profileDate, $profile->fresh()->pa_valid_until->toDateString());
        Notification::assertNothingSent();
    }

    // ---- rejecting -------------------------------------------------------------------------------

    public function test_rejecting_needs_a_reason_leaves_the_record_alone_and_emails_the_reason(): void
    {
        $review = $this->pendingFor('KB11935');
        $profile = $this->profile('KB11935');
        $date = $this->record('KB11935')->pa_valid_until->toDateString();
        $url = route('platform.lkm.reviews.reject', $review);

        foreach (['', 'too short', str_repeat('x', 1001)] as $reason) {
            $this->as()->post($url, ['reason' => $reason])->assertSessionHasErrorsIn('reject-'.$review->id, 'reason');
        }
        $this->assertTrue($review->fresh()->isPending());
        Notification::assertNothingSent();

        $reason = 'The date on the certificate is 2026, not 2028. Please check and send a new request.';
        $this->as()->post($url, ['reason' => "  {$reason}  "])->assertRedirect(route('platform.lkm.reviews'))->assertSessionHas('status', fn ($m) => str_contains($m, 'has been emailed the reason'));

        $review->refresh();
        $this->assertSame([LkmReviewRequest::REJECTED, $reason, null, $this->admin->id], [$review->status, $review->decision_reason, $review->pending_for, $review->reviewed_by]);
        $this->assertSame($date, $this->record('KB11935')->pa_valid_until->toDateString());
        $this->assertSame(0, AuditLog::where('action', 'lkm.record_updated')->count());

        $log = $this->log('lkm.review_rejected');
        $this->assertSame([$this->admin->id, 'counselor', $profile->id, $reason], [$log->actor_user_id, $log->target_type, $log->target_id, $log->metadata['reason']]);

        Notification::assertSentTo($profile->user, LkmReviewRejected::class, fn ($n) => str_contains($n->toMail($profile->user)->render(), $reason));

        // They may ask again.
        $again = $this->request('KB11935', ['pa_valid_until' => now()->addYears(2)->toDateString()]);
        $this->assertTrue($again->isPending());
    }

    public function test_rejecting_is_all_or_nothing_when_the_audit_entry_fails(): void
    {
        $review = $this->pendingFor('KB11935');

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->as()->post(route('platform.lkm.reviews.reject', $review), ['reason' => 'Not clear enough to read.']);
            $this->fail('the request should have failed');
        } catch (RuntimeException) {
        }

        $this->assertTrue($review->fresh()->isPending());
        Notification::assertNothingSent();
    }

    // ---- asking (what the counselor portal will call) -----------------------------------------------

    public function test_a_request_keeps_the_evidence_privately_under_a_random_name_and_is_audited_without_it(): void
    {
        $new = now()->addYears(2)->toDateString();
        $record = $this->record('KB10234');

        $review = $this->request('KB10234', ['pa_valid_until' => $new], $this->pdf('my-secret-renewal.pdf'));

        $this->assertSame([LkmReviewRequest::PENDING, $this->profile('KB10234')->id, ['pa_valid_until' => $new], ['pa_valid_until' => $record->pa_valid_until->toDateString()]], [$review->status, $review->pending_for, $review->claimed, $review->current]);
        $this->assertSame('my-secret-renewal.pdf', $review->evidence_name);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.pdf$/', $review->evidence_path);
        $this->assertTrue($this->disk()->exists($review->evidence_path));

        $log = $this->log('lkm.review_requested');
        $this->assertSame([$this->profile('KB10234')->user_id, 'counselor', $this->profile('KB10234')->id, ['pa_valid_until']], [$log->actor_user_id, $log->target_type, $log->target_id, $log->metadata['fields']]);
        $this->assertStringNotContainsString($review->evidence_path, json_encode($log->metadata));
        $this->assertStringNotContainsString('my-secret-renewal', json_encode($log->metadata));
    }

    public function test_evidence_is_always_required_and_must_be_a_pdf_or_picture(): void
    {
        // (The type is also checked from the file's own bytes, not only its name; the test fake cannot show that, it reports the name's type.)
        $count = count($this->disk()->allFiles());
        $claim = ['pa_valid_until' => now()->addYears(2)->toDateString()];
        $service = app(LkmReviewService::class);
        $profile = $this->profile('KB10234');

        foreach ([
            UploadedFile::fake()->createWithContent('notes.txt', 'just some words'),
            UploadedFile::fake()->createWithContent('run.exe', 'MZ'),
            UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'),
        ] as $file) {
            try {
                $service->request($profile, $claim, $file);
                $this->fail('the evidence should have been refused: '.$file->getClientOriginalName());
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('evidence', $e->errors());
            }
        }

        $this->assertSame($count, count($this->disk()->allFiles()), 'nothing was stored');
        $this->assertSame(0, LkmReviewRequest::where('counselor_profile_id', $profile->id)->count());
    }

    public function test_a_png_or_jpg_picture_is_fine(): void
    {
        $this->assertTrue($this->request('KB10234', ['pa_valid_until' => now()->addYears(2)->toDateString()], UploadedFile::fake()->image('scan.png', 200, 200))->hasEvidence());
    }

    public function test_one_pending_request_per_counselor_in_the_service_and_in_the_database(): void
    {
        $claim = ['pa_valid_until' => now()->addYears(2)->toDateString()];
        $this->request('KB10234', $claim);
        $files = count($this->disk()->allFiles());

        try {
            $this->request('KB10234', $claim);
            $this->fail('a second pending request should be refused');
        } catch (CounselorDecisionBlocked $e) {
            $this->assertStringContainsString('already have a request waiting', $e->getMessage());
        }

        $this->assertSame($files, count($this->disk()->allFiles()), 'the second file was not kept');
        $this->assertSame(1, LkmReviewRequest::pending()->where('counselor_profile_id', $this->profile('KB10234')->id)->count());

        // Even a request that skips the service cannot make a second pending row.
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        LkmReviewRequest::create(['counselor_profile_id' => $this->profile('KB10234')->id, 'status' => 'pending', 'pending_for' => $this->profile('KB10234')->id, 'current' => [], 'claimed' => $claim]);
    }

    public function test_a_claim_must_differ_from_the_record_and_must_not_be_in_the_past(): void
    {
        $service = app(LkmReviewService::class);
        $profile = $this->profile('KB10234');

        try {
            $service->request($profile, ['pa_valid_until' => $this->record('KB10234')->pa_valid_until->toDateString()], $this->pdf());
            $this->fail('nothing differs');
        } catch (CounselorDecisionBlocked $e) {
            $this->assertStringContainsString('already shows these details', $e->getMessage());
        }

        foreach (['2020-01-01', 'next year', ''] as $date) {
            try {
                $service->request($profile, ['pa_valid_until' => $date], $this->pdf());
                $this->fail("{$date} should be refused");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('pa_valid_until', $e->errors());
            }
        }

        $this->assertSame(0, LkmReviewRequest::where('counselor_profile_id', $profile->id)->count());
    }

    public function test_only_approved_or_suspended_counselors_can_ask(): void
    {
        $pending = $this->profile('KB11023');
        $this->assertSame(VerificationStatus::Pending, $pending->verification_status);

        $this->expectException(CounselorDecisionBlocked::class);
        app(LkmReviewService::class)->request($pending, ['pa_valid_until' => now()->addYears(2)->toDateString()], $this->pdf());
    }

    // ---- retention -------------------------------------------------------------------------------

    public function test_the_retention_command_removes_evidence_30_days_after_the_decision_and_nothing_else(): void
    {
        $old = LkmReviewRequest::where('status', 'rejected')->firstOrFail();
        $recent = LkmReviewRequest::where('status', 'approved')->whereNotNull('evidence_path')->firstOrFail();
        $pending = $this->pendingFor('KB11935');

        $old->update(['reviewed_at' => now()->subDays(31)]);
        $recent->update(['reviewed_at' => now()->subDays(29)]);
        $oldPath = $old->evidence_path;

        $this->artisan('lkm:purge-review-evidence', ['--dry-run' => true])->expectsOutputToContain('Would remove the evidence of 1 review requests')->assertSuccessful();
        $this->assertTrue($this->disk()->exists($oldPath));
        $this->assertNotNull($old->fresh()->evidence_path);
        $this->assertSame(0, AuditLog::where('action', 'lkm.evidence_purged')->count());

        $this->artisan('lkm:purge-review-evidence')->expectsOutputToContain('Removed the evidence of 1 review requests')->assertSuccessful();

        $old->refresh();
        $this->assertNull($old->evidence_path);
        $this->assertNull($old->evidence_name);
        $this->assertNotNull($old->evidence_purged_at);
        $this->assertFalse($this->disk()->exists($oldPath));
        $this->assertSame(LkmReviewRequest::REJECTED, $old->status, 'the row stays as the record');
        $this->assertNotNull($recent->fresh()->evidence_path);
        $this->assertTrue($pending->fresh()->hasEvidence());

        $log = $this->log('lkm.evidence_purged');
        $this->assertSame(['system', 'counselor', $old->counselor_profile_id, $old->id], [$log->actor_type, $log->target_type, $log->target_id, $log->metadata['review_id']]);
        $this->assertStringNotContainsString($oldPath, json_encode($log->metadata));

        // Running it again does nothing more.
        $this->artisan('lkm:purge-review-evidence')->expectsOutputToContain('Removed the evidence of 0 review requests');
        $this->assertSame(1, AuditLog::where('action', 'lkm.evidence_purged')->count());
    }

    public function test_the_retention_period_is_configurable_and_a_pending_request_is_never_touched(): void
    {
        config(['lkm.review.evidence_retention_days' => 5]);
        $recent = LkmReviewRequest::where('status', 'approved')->whereNotNull('evidence_path')->firstOrFail();
        $recent->update(['reviewed_at' => now()->subDays(6)]);
        $pending = $this->pendingFor('KB13090');
        $pending->update(['created_at' => now()->subDays(100)]);

        $this->artisan('lkm:purge-review-evidence')->assertSuccessful();

        $this->assertNull($recent->fresh()->evidence_path);
        $this->assertTrue($pending->fresh()->hasEvidence());
        $this->assertTrue($this->disk()->exists($pending->evidence_path));
    }

    public function test_the_command_is_scheduled_daily(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('lkm:purge-review-evidence')->assertSuccessful();
    }

    // ---- the Counselors page ------------------------------------------------------------------------

    public function test_the_counselors_page_shows_the_waiting_request_and_the_history(): void
    {
        $review = $this->pendingFor('KB11935');

        $this->as()->get(route('platform.counselors.show', $this->profile('KB11935')))->assertOk()
            ->assertSee('Asked for their LKM record to be checked again')
            ->assertSee(route('platform.lkm.reviews').'#review-'.$review->id, false)
            ->assertSee('LKM re-check request rejected');

        // A counselor with no request waiting has no such notice.
        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertOk()->assertDontSee('Review the request');
        $this->as()->get(route('platform.counselors.show', $this->profile('KB10891')))->assertSee('LKM record updated after their request');
    }

    // ---- the sample data and the catalogue -----------------------------------------------------------

    public function test_the_sample_requests_cover_every_case_and_the_seeder_is_safe_to_run_twice(): void
    {
        $this->seed(LkmReviewRequestSeeder::class);

        $this->assertSame(5, LkmReviewRequest::count());
        $this->assertSame(2, LkmReviewRequest::pending()->count());
        $this->assertSame(['approved' => 2, 'pending' => 2, 'rejected' => 1], LkmReviewRequest::query()->get()->countBy('status')->sortKeys()->all());
        $this->assertSame(1, LkmReviewRequest::whereNotNull('evidence_purged_at')->count());
        $this->assertCount(4, $this->disk()->allFiles());
        $this->assertSame(VerificationStatus::Suspended, $this->pendingFor('KB13090')->counselorProfile->verification_status);
        $this->assertSame('system', $this->pendingFor('KB13090')->counselorProfile->suspension_source);
    }

    public function test_the_audit_catalogue_has_the_review_events(): void
    {
        $actions = config('audit.actions');

        foreach (['lkm.review_requested', 'lkm.review_approved', 'lkm.review_rejected', 'lkm.evidence_viewed', 'lkm.evidence_purged'] as $action) {
            $this->assertSame('lkm', $actions[$action]['category'], $action);
        }

        $this->assertArrayNotHasKey('lkm.review_resolved', $actions);
    }
}
