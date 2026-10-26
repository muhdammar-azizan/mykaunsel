<?php

namespace Tests\Feature\Counselor;

use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CounselorRetentionTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    /** Put a profile in a status with its decision made $days ago. KB11023 has all three files on the private disk. */
    private function decided(string $kb, VerificationStatus $status, int $daysAgo): CounselorProfile
    {
        $profile = $this->profile($kb);
        $profile->update(['verification_status' => $status, 'reviewed_at' => now()->subDays($daysAgo)]);

        return $profile->fresh();
    }

    private function doc(CounselorProfile $profile, string $type): CounselorDocument
    {
        return $profile->documents()->where('type', $type)->firstOrFail();
    }

    private function exists(CounselorDocument $doc): bool
    {
        return $doc->path !== null && Storage::disk('counselor_documents')->exists($doc->path);
    }

    private function purge(array $options = [])
    {
        return $this->artisan('counselors:purge-documents', $options);
    }

    // ---- ID / passport -------------------------------------------------------------------------

    public function test_the_id_copy_goes_30_days_after_approval_and_the_certificates_stay(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 31);
        [$ic, $cert, $pa] = [$this->doc($profile, 'ic'), $this->doc($profile, 'cert'), $this->doc($profile, 'pa')];
        $icPath = $ic->path;
        $this->assertTrue(Storage::disk('counselor_documents')->exists($icPath));

        $this->purge()->expectsOutput('Removed 1 ID copies and 0 certificates.')->assertSuccessful();

        $ic->refresh();
        $this->assertNull($ic->path);
        $this->assertNotNull($ic->purged_at);
        $this->assertNull($ic->original_name, 'the file name could identify the person');
        $this->assertNull($ic->size_bytes);
        $this->assertSame('ic', $ic->type, 'the row stays as the record that it existed');
        Storage::disk('counselor_documents')->assertMissing($icPath);

        $this->assertTrue($this->exists($cert->fresh()), 'KB certificate kept');
        $this->assertTrue($this->exists($pa->fresh()), 'PA certificate kept');
        $this->assertNull($cert->fresh()->purged_at);
    }

    public function test_nothing_is_removed_before_the_period_is_up(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 29);

        $this->purge()->expectsOutput('Removed 0 ID copies and 0 certificates.');

        $this->assertTrue($this->exists($this->doc($profile, 'ic')));
        $this->assertSame(0, AuditLog::where('action', 'counselor.document_purged')->count());
    }

    public function test_a_rejected_application_that_stays_rejected_loses_its_id_after_30_days_and_its_certificates_after_90(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Rejected, 31);

        $this->purge()->expectsOutput('Removed 1 ID copies and 0 certificates.');
        $this->assertNull($this->doc($profile, 'ic')->fresh()->path);
        $this->assertTrue($this->exists($this->doc($profile, 'cert')->fresh()));

        $profile->update(['reviewed_at' => now()->subDays(89)]);
        $this->purge()->expectsOutput('Removed 0 ID copies and 0 certificates.');

        $profile->update(['reviewed_at' => now()->subDays(91)]);
        $this->purge()->expectsOutput('Removed 0 ID copies and 2 certificates.');
        $this->assertNull($this->doc($profile, 'cert')->fresh()->path);
        $this->assertNull($this->doc($profile, 'pa')->fresh()->path);
        $this->assertNotNull($this->doc($profile, 'pa')->fresh()->purged_at);
    }

    public function test_a_counselor_who_was_approved_and_is_now_suspended_still_loses_the_id_but_keeps_the_certificates(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Suspended, 400);

        $this->purge()->expectsOutput('Removed 1 ID copies and 0 certificates.');

        $this->assertNull($this->doc($profile, 'ic')->fresh()->path);
        $this->assertTrue($this->exists($this->doc($profile, 'cert')->fresh()));
        $this->assertTrue($this->exists($this->doc($profile, 'pa')->fresh()));
    }

    public function test_pending_applications_are_never_touched_even_with_an_old_earlier_decision(): void
    {
        // A resubmitted application: rejected long ago, back in the queue now.
        $profile = $this->decided('KB11023', VerificationStatus::Pending, 500);

        $this->purge()->expectsOutput('Removed 0 ID copies and 0 certificates.');

        foreach (['cert', 'pa', 'ic'] as $type) {
            $this->assertTrue($this->exists($this->doc($profile, $type)->fresh()), $type);
        }
    }

    public function test_applications_without_a_decision_date_are_left_alone(): void
    {
        $profile = $this->profile('KB11023');
        $profile->update(['verification_status' => VerificationStatus::Approved, 'reviewed_at' => null]);

        $this->purge()->expectsOutput('Removed 0 ID copies and 0 certificates.');

        $this->assertTrue($this->exists($this->doc($profile, 'ic')));
    }

    public function test_the_periods_come_from_the_configuration(): void
    {
        config(['counselors.retention.ic_days' => 5, 'counselors.retention.rejected_certificates_days' => 10]);
        $profile = $this->decided('KB11023', VerificationStatus::Rejected, 6);

        $this->purge()->assertSuccessful();
        $this->assertNotNull($this->doc($profile, 'ic')->fresh()->purged_at, '6 days is past the 5-day ID period');
        $this->assertNull($this->doc($profile, 'cert')->fresh()->purged_at, 'but not past the 10-day certificate period');

        $profile->update(['reviewed_at' => now()->subDays(11)]);
        $this->purge()->assertSuccessful();
        $this->assertNotNull($this->doc($profile, 'cert')->fresh()->purged_at);
        $this->assertNotNull($this->doc($profile, 'pa')->fresh()->purged_at);
    }

    // ---- audit -----------------------------------------------------------------------------------

    public function test_every_removal_is_a_system_audit_event_without_personal_data(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 40);
        $ic = $this->doc($profile, 'ic');
        $originalName = $ic->original_name;

        $this->purge();

        $log = AuditLog::where('action', 'counselor.document_purged')->firstOrFail();
        $this->assertSame('system', $log->actor_type);
        $this->assertNull($log->actor_user_id);
        $this->assertSame(['counselor', $profile->id], [$log->target_type, $log->target_id]);
        $this->assertSame(['ID / passport', $ic->id, 'ic_after_decision'], [$log->metadata['document'], $log->metadata['document_id'], $log->metadata['rule']]);
        $this->assertStringNotContainsString($originalName, json_encode($log->toArray()));

        $this->as()->get(route('platform.counselors.show', $profile))->assertSee('Document removed (retention)')->assertSee('Removed on')->assertSee('under the retention rules')->assertSee('System');
    }

    // ---- dry run, repeat, chunks ------------------------------------------------------------------

    public function test_a_dry_run_lists_what_would_go_and_changes_nothing(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Rejected, 120);
        $paths = $profile->documents->pluck('path', 'type')->all();

        $this->purge(['--dry-run' => true])
            ->expectsOutputToContain('Would remove ID / passport')
            ->expectsOutputToContain('Would remove KB certificate')
            ->expectsOutput('Would remove 1 ID copies and 2 certificates.')
            ->assertSuccessful();

        $this->assertSame($paths, $profile->documents()->get()->pluck('path', 'type')->all());
        $this->assertSame(0, $profile->documents()->whereNotNull('purged_at')->count());
        $this->assertSame(0, AuditLog::where('action', 'counselor.document_purged')->count());
        foreach ($paths as $path) {
            Storage::disk('counselor_documents')->assertExists($path);
        }
    }

    public function test_running_it_again_finds_nothing_more(): void
    {
        $this->decided('KB11023', VerificationStatus::Approved, 40);

        $this->purge()->expectsOutput('Removed 1 ID copies and 0 certificates.');
        $this->purge()->expectsOutput('Removed 0 ID copies and 0 certificates.');

        $this->assertSame(1, AuditLog::where('action', 'counselor.document_purged')->count());
    }

    public function test_many_documents_are_handled_in_chunks_without_losing_or_repeating_any(): void
    {
        config(['counselors.retention.chunk' => 2]);
        foreach (['KB11023', 'KB11256', 'KB11489', 'KB11702', 'KB12391'] as $kb) {
            $this->decided($kb, VerificationStatus::Approved, 45);
        }

        $this->purge()->expectsOutput('Removed 5 ID copies and 0 certificates.');

        $this->assertSame(5, AuditLog::where('action', 'counselor.document_purged')->count());
        $this->assertSame(5, CounselorDocument::where('type', 'ic')->whereNotNull('purged_at')->count());
        $this->assertSame(0, CounselorDocument::where('type', 'ic')->whereNotNull('path')->whereHas('counselorProfile', fn ($q) => $q->whereNotNull('reviewed_at')->where('reviewed_at', '<', now()->subDays(30)))->count());
    }

    // ---- order of events ---------------------------------------------------------------------------

    public function test_the_file_is_deleted_only_after_the_database_change_is_safe(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 40);
        $ic = $this->doc($profile, 'ic');
        $seen = [];

        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->andReturnUsing(function ($path) use ($ic, &$seen) {
            $row = CounselorDocument::find($ic->id);
            $seen = ['path' => $row->path, 'purged' => $row->purged_at !== null, 'audited' => AuditLog::where('action', 'counselor.document_purged')->exists()];

            return true;
        });
        Storage::set('counselor_documents', $disk);

        $this->purge()->assertSuccessful();

        $this->assertSame(['path' => null, 'purged' => true, 'audited' => true], $seen, 'row and audit entry were already saved when the file went');
    }

    public function test_a_file_that_cannot_be_deleted_is_reported_and_the_row_is_still_marked(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 40);

        $disk = \Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->andThrow(new RuntimeException('disk is read-only'));
        Storage::set('counselor_documents', $disk);

        $this->purge()->expectsOutputToContain('1 files could not be deleted')->assertFailed();

        $ic = $this->doc($profile, 'ic');
        $this->assertNull($ic->path, 'nothing in the app can reach it any more');
        $this->assertNotNull($ic->purged_at);
    }

    public function test_if_the_audit_entry_cannot_be_written_nothing_is_removed(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 40);
        $ic = $this->doc($profile, 'ic');
        $path = $ic->path;

        Audit::shouldReceive('asSystem')->andReturnUsing(fn ($callback) => $callback());
        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            $this->purge()->run();
            $this->fail('the command should have stopped');
        } catch (RuntimeException) {
        }

        $this->assertSame($path, $ic->fresh()->path);
        $this->assertNull($ic->fresh()->purged_at);
        Storage::disk('counselor_documents')->assertExists($path);
    }

    // ---- afterwards ----------------------------------------------------------------------------------

    public function test_an_applicant_can_upload_a_removed_document_again(): void
    {
        $profile = $this->decided('KB12391', VerificationStatus::Rejected, 40); // rejected 40 days ago, still rejected
        $this->purge();
        $ic = $this->doc($profile, 'ic');
        $this->assertNotNull($ic->purged_at);

        $this->actingAs($profile->user)->post(route('counselors.signup.documents.store'), ['ic' => UploadedFile::fake()->create('new-id.pdf', 40, 'application/pdf')]);

        $ic->refresh();
        $this->assertNotNull($ic->path);
        $this->assertNull($ic->purged_at);
        Storage::disk('counselor_documents')->assertExists($ic->path);
        $this->assertSame(VerificationStatus::Pending, $profile->fresh()->verification_status);
    }

    public function test_a_removed_document_cannot_be_opened_by_an_admin(): void
    {
        $profile = $this->decided('KB11023', VerificationStatus::Approved, 40);
        $this->purge();
        $ic = $this->doc($profile, 'ic');

        $this->as()->getJson(route('platform.counselors.documents.link', [$profile, $ic]))->assertStatus(410)->assertJsonPath('message', 'This document was removed under the retention rules.');
    }

    public function test_organization_counselors_have_no_documents_to_purge(): void
    {
        $this->artisan('counselors:purge-documents')->expectsOutput('Removed 0 ID copies and 0 certificates.');
        $this->assertSame(0, CounselorDocument::whereHas('counselorProfile', fn ($q) => $q->where('verification_type', 'org_assigned'))->count());
    }

    // ---- schedule ------------------------------------------------------------------------------------

    public function test_the_command_is_scheduled_daily_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'counselors:purge-documents'));

        $this->assertNotNull($event, 'counselors:purge-documents is not scheduled');
        $this->assertSame('30 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
