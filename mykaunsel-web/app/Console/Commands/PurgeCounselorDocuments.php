<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Models\CounselorDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Retention for counselor documents (runs daily, see routes/console.php). The periods are in config/counselors.php.
 *
 *   ID / passport copy   → deleted N days after the final decision: approved (or approved and later suspended),
 *                          or rejected and not resubmitted.
 *   KB + PA certificates → kept for approved counselors as proof of verification; for an application that stays
 *                          rejected they are deleted N days after the rejection.
 *   Pending applications → never touched.
 *
 * For every document, in this order: the database row is updated (path removed, purged_at set) together with a
 * system audit entry in one transaction, and only AFTER that commits is the file deleted. The row is kept as the
 * record that it existed. If a file cannot be deleted it is reported and left (the row already says "purged", so
 * nothing can reach it); run the command again after fixing the disk.
 *
 * Use --dry-run to see what would be removed without changing anything.
 */
class PurgeCounselorDocuments extends Command
{
    protected $signature = 'counselors:purge-documents {--dry-run : List what would be removed, change nothing}';

    protected $description = 'Delete counselor documents whose retention period has ended';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('counselors.documents_disk'));
        $counts = ['ic' => 0, 'certificates' => 0, 'file_errors' => 0];

        Audit::asSystem(function () use ($dryRun, $disk, &$counts) {
            foreach ($this->due() as $query) {
                [$builder, $reason, $bucket] = $query;

                $builder->chunkById((int) config('counselors.retention.chunk', 200), function ($documents) use ($dryRun, $disk, $reason, $bucket, &$counts) {
                    foreach ($documents as $document) {
                        $counts[$bucket]++;

                        if ($dryRun) {
                            $this->line("Would remove {$document->label()} (document #{$document->id}, {$reason})");

                            continue;
                        }

                        $path = $document->path;

                        // 1. database + audit, together
                        DB::transaction(function () use ($document, $reason) {
                            $document->update(['path' => null, 'original_name' => null, 'size_bytes' => null, 'purged_at' => now()]);

                            Audit::log('counselor.document_purged', $document->counselorProfile, [
                                'document' => $document->label(),
                                'document_id' => $document->id,
                                'rule' => $reason,
                            ]);
                        });

                        // 2. the file, only after the database change is safe
                        try {
                            $disk->delete($path);
                        } catch (Throwable $e) {
                            $counts['file_errors']++;
                            report($e);
                        }
                    }
                });
            }
        });

        $verb = $dryRun ? 'Would remove' : 'Removed';
        $this->info("{$verb} {$counts['ic']} ID copies and {$counts['certificates']} certificates"
            .($counts['file_errors'] ? " ({$counts['file_errors']} files could not be deleted — see the log)" : '').'.');

        return $counts['file_errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<array{0: \Illuminate\Database\Eloquent\Builder, 1: string, 2: string}> */
    private function due(): array
    {
        $held = fn () => CounselorDocument::query()->whereNotNull('path')->whereNull('purged_at')->with('counselorProfile');

        $icCutoff = now()->subDays((int) config('counselors.retention.ic_days', 30));
        $certCutoff = now()->subDays((int) config('counselors.retention.rejected_certificates_days', 90));

        return [
            // ID: final decision is old enough. (A profile that was approved and is now suspended counts as approved.)
            [$held()->where('type', 'ic')->whereHas('counselorProfile', fn ($p) => $p
                ->whereIn('verification_status', [VerificationStatus::Approved->value, VerificationStatus::Suspended->value, VerificationStatus::Rejected->value])
                ->whereNotNull('reviewed_at')->where('reviewed_at', '<=', $icCutoff)), 'ic_after_decision', 'ic'],

            // KB + PA: only for an application that is still rejected.
            [$held()->whereIn('type', ['cert', 'pa'])->whereHas('counselorProfile', fn ($p) => $p
                ->where('verification_status', VerificationStatus::Rejected->value)
                ->whereNotNull('reviewed_at')->where('reviewed_at', '<=', $certCutoff)), 'certificates_after_rejection', 'certificates'],
        ];
    }
}
