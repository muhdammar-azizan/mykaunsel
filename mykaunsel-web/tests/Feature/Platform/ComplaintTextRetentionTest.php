<?php

namespace Tests\Feature\Platform;

use App\Enums\ComplaintStatus;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintReviewNote;
use App\Models\ComplaintWarning;
use App\Models\User;
use App\Services\ComplaintTimeline;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Where the words go. What the complaint says, the closing reason and the warning messages live with the complaint and are
 * erased by the retention rule 3 years after it was closed; the audit log holds only kinds of action, statuses and ids.
 * A reporter who deletes their account does not block the complaint.
 */
class ComplaintTextRetentionTest extends CounselorTestCase
{
    private const TEXT = 'The counselor shouted at me during the session and refused to refund the fee.';

    private const OUTCOME = 'Both sides were heard and the counselor agreed to a refund.';

    private const WARNING = 'Please keep every session calm and respectful from now on.';

    private const REASON = 'Serious and repeated conduct concerns raised by clients.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
        Complaint::query()->delete();
        Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00', 'UTC'));
    }

    private function reporter(): User
    {
        return User::where('email', 'klien.contoh@example.test')->firstOrFail();
    }

    private function complaint(string $status = 'open', ?Carbon $closedAt = null, string $kb = 'KB10234', ?User $reporter = null): Complaint
    {
        $c = Complaint::create([
            'reporter_user_id' => ($reporter ?? $this->reporter())->id,
            'counselor_user_id' => $this->profile($kb)->user_id,
            'description' => self::TEXT,
            'status' => $status,
            'closing_reason' => $closedAt ? self::OUTCOME : null,
            'closed_at' => $closedAt,
        ]);

        return $c;
    }

    private function audit(Complaint $c)
    {
        return AuditLog::where('target_type', 'complaint')->where('target_id', $c->id)->orderBy('id')->get();
    }

    // ---- the audit log holds no words ---------------------------------------------------------------------------

    public function test_the_complaint_audit_entries_hold_only_the_kind_of_action_statuses_and_ids(): void
    {
        Notification::fake();
        $c = $this->complaint();

        $this->as()->post(route('platform.complaints.notes.store', $c), ['body' => 'Spoke to the counselor about the refund.']);
        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => self::WARNING])->assertSessionHas('status');
        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => self::REASON, 'sessions' => 'keep'])->assertSessionHas('status');
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'open', 'reason' => self::OUTCOME])->assertSessionHas('status');

        $entries = $this->audit($c);
        $this->assertEqualsCanonicalizing(['complaint.note_added', 'complaint.warning_issued', 'complaint.counselor_suspended', 'complaint.status_changed'], $entries->pluck('action')->all());

        foreach ($entries as $entry) {
            $keys = collect($entry->metadata)->except(['request', 'seeded'])->keys()->sort()->values()->all();
            $expected = [
                'complaint.note_added' => ['note_id'],
                'complaint.warning_issued' => ['warning_id'],
                'complaint.counselor_suspended' => ['counselor_profile_id', 'sessions'],
                'complaint.status_changed' => ['changes'],
            ][$entry->action];

            $this->assertSame($expected, $keys, $entry->action);

            $json = json_encode($entry->getAttributes());
            foreach ([self::TEXT, self::OUTCOME, self::WARNING, self::REASON, 'refund', 'shouted', 'respectful', 'Serious and repeated'] as $words) {
                $this->assertStringNotContainsString($words, $json, "{$entry->action} must not hold: {$words}");
            }
        }

        $status = $entries->firstWhere('action', 'complaint.status_changed');
        $this->assertSame(['status' => ['old' => 'open', 'new' => 'resolved']], $status->metadata['changes']);
    }

    public function test_the_words_are_kept_with_the_complaint_the_warning_and_the_profile(): void
    {
        Notification::fake();
        $c = $this->complaint();

        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => self::WARNING]);
        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => self::REASON, 'sessions' => 'keep']);
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'dismissed', 'from' => 'open', 'reason' => self::OUTCOME]);

        $c->refresh();
        $this->assertSame([self::OUTCOME, ComplaintStatus::Dismissed], [$c->closing_reason, $c->status]);
        $this->assertTrue($c->closed_at->equalTo(now()));
        $this->assertSame([self::WARNING], $c->warnings->pluck('message')->all());
        $this->assertSame(self::REASON, $this->profile('KB10234')->suspension_reason);
    }

    public function test_the_audit_log_page_and_its_csv_export_do_not_show_the_complaint_words(): void
    {
        Notification::fake();
        $c = $this->complaint();
        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => self::WARNING]);
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'open', 'reason' => self::OUTCOME]);

        $page = $this->as()->get(route('platform.audit-log', ['category' => 'complaints']))->assertOk()->getContent();
        $csv = $this->as()->get(route('platform.audit-log.export', ['category' => 'complaints']))->assertOk()->streamedContent();

        foreach ([self::OUTCOME, self::WARNING, self::TEXT] as $words) {
            $this->assertStringNotContainsString($words, $page);
            $this->assertStringNotContainsString($words, $csv);
        }
    }

    public function test_the_suspension_entry_the_counselors_page_also_writes_is_unchanged_and_still_has_its_reason(): void
    {
        // counselor.suspended is shared with the Counselors page and is deliberately left as it was (docs/COMPLAINTS.md).
        Notification::fake();
        $c = $this->complaint();

        $this->as()->post(route('platform.complaints.suspend-counselor', $c), ['reason' => self::REASON, 'sessions' => 'keep']);

        $shared = AuditLog::where('action', 'counselor.suspended')->where('target_id', $this->profile('KB10234')->id)->sole();
        $this->assertSame(self::REASON, $shared->metadata['reason']);
        $this->assertSame('complaint', $shared->metadata['source']);
    }

    // ---- the page and the activity read the words from the complaint's own records -------------------------------

    public function test_the_activity_shows_what_the_admin_wrote_while_it_is_kept_and_a_neutral_line_after_it_is_erased(): void
    {
        Notification::fake();
        $c = $this->complaint();
        $this->as()->post(route('platform.complaints.warn', $c), ['reason' => self::WARNING]);
        $this->as()->post(route('platform.complaints.status', $c), ['to' => 'resolved', 'from' => 'open', 'reason' => self::OUTCOME]);

        $events = app(ComplaintTimeline::class)->for($c->fresh())->keyBy('title');
        $this->assertSame(self::WARNING, $events['Warning issued to the counselor']['detail']);
        $this->assertSame('open → resolved — '.self::OUTCOME, $events['Status changed']['detail']);

        $c->refresh()->forceFill(['closed_at' => now()->subYears(4)])->save();
        $this->artisan('complaints:purge-text')->assertSuccessful();

        $events = app(ComplaintTimeline::class)->for($c->fresh())->keyBy('title');
        $this->assertSame('Warning emailed to the counselor', $events['Warning issued to the counselor']['detail']);
        $this->assertSame('open → resolved', $events['Status changed']['detail']);
    }

    // ---- retention ----------------------------------------------------------------------------------------------------

    public function test_three_years_after_closing_the_text_is_erased_and_the_record_stays(): void
    {
        $old = $this->complaint('resolved', now()->subYears(3)->subMinute());
        $warning = ComplaintWarning::create(['complaint_id' => $old->id, 'user_id' => $this->admin->id, 'message' => self::WARNING]);
        $filed = $old->created_at;

        $this->artisan('complaints:purge-text')->expectsOutputToContain('Erased the text of 1 complaints (1 warning messages)')->assertSuccessful();

        $old->refresh();
        $this->assertNull($old->description);
        $this->assertNull($old->closing_reason);
        $this->assertNotNull($old->text_purged_at);
        $this->assertNull($warning->fresh()->message);
        $this->assertNotNull($warning->fresh(), 'the warning record itself stays');

        // The record stays: reference, status, dates, who it was about, who made it.
        $this->assertSame([ComplaintStatus::Resolved, $this->profile('KB10234')->user_id, $this->reporter()->id], [$old->status, $old->counselor_user_id, $old->reporter_user_id]);
        $this->assertTrue($old->created_at->equalTo($filed));
        $this->assertNotNull($old->closed_at);
    }

    public function test_nothing_is_erased_before_three_years_and_open_complaints_are_never_touched(): void
    {
        $almost = $this->complaint('dismissed', now()->subYears(3)->addMinute());
        $open = $this->complaint('open');
        $review = $this->complaint('under_review');
        // Even an open complaint that is very old.
        $open->forceFill(['created_at' => now()->subYears(6)])->save();

        $this->artisan('complaints:purge-text')->expectsOutputToContain('Erased the text of 0 complaints')->assertSuccessful();

        foreach ([$almost, $open, $review] as $c) {
            $this->assertSame(self::TEXT, $c->fresh()->description);
            $this->assertNull($c->fresh()->text_purged_at);
        }
        $this->assertSame(0, AuditLog::where('action', 'complaint.text_purged')->count());
    }

    public function test_the_audit_entry_is_a_system_one_with_counts_only_and_a_second_run_does_nothing(): void
    {
        $a = $this->complaint('resolved', now()->subYears(5));
        $b = $this->complaint('dismissed', now()->subYears(4));
        ComplaintWarning::create(['complaint_id' => $a->id, 'user_id' => $this->admin->id, 'message' => self::WARNING]);
        ComplaintWarning::create(['complaint_id' => $a->id, 'user_id' => $this->admin->id, 'message' => 'A second one.']);

        $this->artisan('complaints:purge-text')->assertSuccessful();

        $log = AuditLog::where('action', 'complaint.text_purged')->sole();
        $this->assertSame(['system', null, ['complaints' => 2, 'warnings' => 2]], [$log->actor_type, $log->actor_user_id, $log->metadata]);
        $this->assertStringNotContainsString(self::TEXT, json_encode($log->getAttributes()));

        $this->artisan('complaints:purge-text')->expectsOutputToContain('Erased the text of 0 complaints')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'complaint.text_purged')->count());
    }

    public function test_dry_run_counts_and_changes_nothing_and_the_period_is_configurable_and_the_task_runs_daily(): void
    {
        $this->complaint('resolved', now()->subYears(2));

        $this->artisan('complaints:purge-text', ['--dry-run' => true])->expectsOutputToContain('Would erase the text of 0 complaints')->assertSuccessful();

        config(['complaints.retention.closed_years' => 1]);
        $this->artisan('complaints:purge-text', ['--dry-run' => true])->expectsOutputToContain('Would erase the text of 1 complaints')->assertSuccessful();
        $this->assertNotNull(Complaint::first()->description, 'a dry run changes nothing');
        $this->assertSame(3, (require base_path('config/complaints.php'))['retention']['closed_years']);

        Artisan::call('schedule:list');
        $this->assertMatchesRegularExpression('/15\s+3\s+\*\s+\*\s+\*\s+php artisan complaints:purge-text/', Artisan::output());
    }

    public function test_internal_notes_are_not_part_of_the_rule_yet(): void
    {
        $c = $this->complaint('resolved', now()->subYears(5));
        ComplaintReviewNote::create(['complaint_id' => $c->id, 'user_id' => $this->admin->id, 'body' => 'An internal note.']);

        $this->artisan('complaints:purge-text')->assertSuccessful();

        $this->assertSame('An internal note.', ComplaintReviewNote::firstOrFail()->body, 'a decision still to be made (docs/COMPLAINTS.md)');
    }

    public function test_an_erased_complaint_still_opens_lists_and_searches_and_says_the_text_was_erased(): void
    {
        $c = $this->complaint('resolved', now()->subYears(4));
        $this->artisan('complaints:purge-text')->assertSuccessful();

        $this->as()->get(route('platform.complaints.show', $c))->assertOk()
            ->assertSee('The text of this complaint was erased')->assertSee('under the retention rule')->assertSee('The written outcome was erased')
            ->assertDontSee(self::TEXT)->assertDontSee(self::OUTCOME);
        $this->as()->get(route('platform.complaints', ['tab' => 'resolved']))->assertOk()->assertSee("#{$c->id}");
        $this->assertSame([$c->id], $this->as()->get(route('platform.complaints', ['tab' => 'all', 'q' => "#{$c->id}"]))->viewData('complaints')->getCollection()->pluck('id')->all());
    }

    // ---- accounts that are deleted ------------------------------------------------------------------------------------

    public function test_a_reporter_who_deletes_their_account_does_not_block_the_complaint(): void
    {
        $gone = User::factory()->create(['name' => 'Leaving Reporter', 'email' => 'leaving@example.test']);
        $c = $this->complaint('open', null, 'KB10234', $gone);
        $other = $this->complaint('open');

        $gone->delete();

        $c->refresh();
        $this->assertNull($c->reporter_user_id);
        $this->assertSame(self::TEXT, $c->description, 'the complaint stays in full');
        $this->assertSame('Deleted user', $c->reporterName());

        $this->as()->get(route('platform.complaints.show', $c))->assertOk()->assertSee('Deleted user')->assertSee(self::TEXT)->assertDontSee('Leaving Reporter');
        $this->as()->get(route('platform.complaints'))->assertOk()->assertSee('Deleted user');
        $this->assertNotNull($other->fresh()->reporter_user_id);
    }

    public function test_each_reporter_who_has_deleted_their_account_still_counts_as_a_person_in_the_history(): void
    {
        $gone = User::factory()->create();
        $this->complaint('dismissed', null, 'KB10234', $gone);
        $this->complaint('dismissed', null, 'KB10234', $gone);
        $c = $this->complaint('open');
        $gone->delete();

        // Two complaints from people we can no longer name count as two people (we cannot tell they are one), plus the sample client.
        $this->as()->get(route('platform.complaints.show', $c))->assertSeeText('3 complaints, 3 reporters');
    }

    public function test_a_counselor_with_a_complaint_cannot_be_deleted_open_or_closed(): void
    {
        $counselor = User::factory()->create();
        Complaint::create(['reporter_user_id' => $this->reporter()->id, 'counselor_user_id' => $counselor->id, 'description' => self::TEXT, 'status' => 'open']);

        try {
            $counselor->delete();
            $this->fail('the restriction on the counselor side stays');
        } catch (QueryException) {
            $this->assertNotNull($counselor->fresh());
        }

        Complaint::query()->update(['status' => 'resolved', 'closed_at' => now()->subYears(5)]);
        $this->artisan('complaints:purge-text')->assertSuccessful();

        $this->expectException(QueryException::class);
        $counselor->delete(); // even after the text is erased the record is about this counselor: the app must settle that when account deletion is built
    }
}
