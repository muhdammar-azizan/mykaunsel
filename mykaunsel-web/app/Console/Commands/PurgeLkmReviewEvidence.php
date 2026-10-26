<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Models\LkmReviewRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retention for the evidence of LKM review requests (runs daily, see routes/console.php). The evidence is deleted
 * `config('lkm.review.evidence_retention_days')` days (30 by default) after the decision. A request that is still pending
 * is never touched.
 *
 * For every request, in this order: the row is updated (path removed, evidence_purged_at set) together with a system audit
 * entry in one transaction, and only AFTER that commits is the file deleted. The row stays as the record that it existed.
 * If a file cannot be deleted it is reported and left (nothing can reach it any more); run the command again after fixing the disk.
 *
 * Use --dry-run to see what would be removed without changing anything.
 */
class PurgeLkmReviewEvidence extends Command
{
    protected $signature = 'lkm:purge-review-evidence {--dry-run : List what would be removed, change nothing}';

    protected $description = 'Delete the evidence of decided LKM review requests once the retention period has ended';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('lkm.review.disk'));
        $cutoff = now()->subDays((int) config('lkm.review.evidence_retention_days'));
        $removed = 0;
        $fileErrors = 0;

        Audit::asSystem(function () use ($dryRun, $disk, $cutoff, &$removed, &$fileErrors) {
            LkmReviewRequest::query()
                ->resolved()
                ->whereNotNull('evidence_path')->whereNull('evidence_purged_at')
                ->whereNotNull('reviewed_at')->where('reviewed_at', '<=', $cutoff)
                ->with('counselorProfile')
                ->chunkById((int) config('lkm.review.purge_chunk'), function ($reviews) use ($dryRun, $disk, &$removed, &$fileErrors) {
                    foreach ($reviews as $review) {
                        $removed++;

                        if ($dryRun) {
                            $this->line("Would remove the evidence of review request #{$review->id} (decided {$review->reviewed_at->toDateString()})");

                            continue;
                        }

                        $path = $review->evidence_path;

                        // 1. database + audit, together
                        DB::transaction(function () use ($review) {
                            $review->update(['evidence_path' => null, 'evidence_name' => null, 'evidence_size' => null, 'evidence_purged_at' => now()]);

                            Audit::log('lkm.evidence_purged', $review->counselorProfile, [
                                'review_id' => $review->id,
                                'kb_number' => $review->counselorProfile->kb_number,
                                'rule' => 'evidence_after_decision',
                            ]);
                        });

                        // 2. the file, only after the database change is safe
                        try {
                            $disk->delete($path);
                        } catch (Throwable $e) {
                            $fileErrors++;
                            report($e);
                        }
                    }
                });
        });

        $this->info(($dryRun ? 'Would remove' : 'Removed')." the evidence of {$removed} review requests"
            .($fileErrors ? " ({$fileErrors} files could not be deleted — see the log)" : '').'.');

        return $fileErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
