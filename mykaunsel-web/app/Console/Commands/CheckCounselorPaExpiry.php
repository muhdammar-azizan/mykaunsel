<?php

namespace App\Console\Commands;

use App\Services\CounselorPaExpiryService;
use Illuminate\Console\Command;

/**
 * Daily practising-certificate (PA) check (scheduled at 00:10, see routes/console.php): automatic reminders before a
 * PA runs out, automatic suspension once it has expired. See CounselorPaExpiryService for the exact rules.
 *
 * Use --dry-run to see what would happen without changing or sending anything.
 */
class CheckCounselorPaExpiry extends Command
{
    protected $signature = 'counselors:check-pa-expiry {--dry-run : List what would happen, change and send nothing}';

    protected $description = 'Remind counselors whose practising certificate is running out and suspend those whose has expired';

    public function handle(CounselorPaExpiryService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->run($dryRun);

        $verb = $dryRun ? 'Would' : 'Did';
        $this->info(sprintf(
            '%s: suspend %d, extend %d from LKM, remind %d.',
            $verb,
            count($result['suspended']),
            count($result['extended']),
            count($result['reminded']),
        ));

        foreach (['suspended' => 'Suspended', 'extended' => 'Date updated from LKM', 'reminded' => 'Reminded'] as $key => $label) {
            if ($result[$key] !== []) {
                $this->line("  {$label}: ".implode(', ', $result[$key]));
            }
        }

        if ($result['errors'] > 0) {
            $this->error("{$result['errors']} counselor(s) could not be processed — see the log. They are tried again on the next run.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
