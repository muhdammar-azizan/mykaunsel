<?php

namespace App\Console\Commands;

use App\Enums\ComplaintStatus;
use App\Facades\Audit;
use App\Models\Complaint;
use App\Models\ComplaintReviewNote;
use App\Models\ComplaintWarning;
use App\Models\CounselorProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for complaints (runs daily, see routes/console.php; the period is in config/complaints.php, 3 years). A complaint
 * that was CLOSED (resolved or dismissed) that long ago loses ALL the text written about it: what was reported, the closing
 * reason, the messages of the warnings sent, the internal notes admins wrote, and the reason of a suspension that came from it.
 * The record stays: reference, status, dates, who it was about, who wrote each note and when, and how it ended.
 * Open and under-review complaints are never touched.
 *
 * One system audit entry with counts only. Safe to run twice: an erased complaint is marked and skipped.
 * Use --dry-run to see how many would be erased without changing anything.
 */
class PurgeComplaintText extends Command
{
    protected $signature = 'complaints:purge-text {--dry-run : Count what would be erased, change nothing}';

    protected $description = 'Erase the text of complaints closed longer ago than the retention period';

    public function handle(): int
    {
        $due = fn () => Complaint::query()
            ->whereIn('status', [ComplaintStatus::Resolved->value, ComplaintStatus::Dismissed->value])
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', now()->subYears((int) config('complaints.retention.closed_years')))
            ->whereNull('text_purged_at');

        if ($this->option('dry-run')) {
            $this->info("Would erase the text of {$due()->count()} complaints (".ComplaintWarning::whereIn('complaint_id', $due()->select('id'))->whereNotNull('message')->count().' warning messages, '.ComplaintReviewNote::whereIn('complaint_id', $due()->select('id'))->where('body', '!=', '')->count().' notes).');

            return self::SUCCESS;
        }

        $complaints = 0;
        $warnings = 0;
        $notes = 0;

        Audit::asSystem(function () use ($due, &$complaints, &$warnings, &$notes) {
            DB::transaction(function () use ($due, &$complaints, &$warnings, &$notes) {
                $due()->chunkById(200, function ($batch) use (&$complaints, &$warnings, &$notes) {
                    $ids = $batch->pluck('id');

                    $warnings += ComplaintWarning::query()->whereIn('complaint_id', $ids)->whereNotNull('message')->update(['message' => null]);
                    // The notes stay as rows (who wrote one and when) but their words go.
                    $notes += ComplaintReviewNote::query()->whereIn('complaint_id', $ids)->where('body', '!=', '')->update(['body' => '']);
                    // A suspension that came from one of these complaints keeps its reason only until the complaint's text is erased.
                    CounselorProfile::query()->whereIn('suspension_complaint_id', $ids)->whereNotNull('suspension_reason')->update(['suspension_reason' => null]);
                    $complaints += Complaint::query()->whereIn('id', $ids)->update(['description' => null, 'closing_reason' => null, 'text_purged_at' => now()]);
                });

                if ($complaints > 0) {
                    Audit::log('complaint.text_purged', null, ['complaints' => $complaints, 'warnings' => $warnings, 'notes' => $notes], 'system');
                }
            });
        });

        $this->info("Erased the text of {$complaints} complaints ({$warnings} warning messages, {$notes} notes).");

        return self::SUCCESS;
    }
}
