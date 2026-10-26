<?php

namespace App\Console\Commands;

use App\Services\WaitlistService;
use Illuminate\Console\Command;

/**
 * Retention for the waitlist (runs daily, see routes/console.php; periods in config/waitlist.php): an address is deleted
 * 30 days after it was notified, and an address that was never notified 365 days after it signed up. One system audit entry
 * with counts (never addresses). The hashes of people who opted out are kept on purpose, so their choice is still respected.
 *
 * Use --dry-run to see how many would go without changing anything.
 */
class PurgeWaitlist extends Command
{
    protected $signature = 'waitlist:purge {--dry-run : Count what would be removed, change nothing}';

    protected $description = 'Delete waitlist addresses whose retention period has ended';

    public function handle(WaitlistService $waitlist): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $waitlist->purge($dryRun);

        $this->info(($dryRun ? 'Would remove' : 'Removed')." {$counts['notified']} notified and {$counts['waiting']} never-notified addresses.");

        return self::SUCCESS;
    }
}
