<?php

namespace App\Console\Commands;

use App\Services\PlatformSettings;
use Illuminate\Console\Command;

/**
 * Switches maintenance mode off when its reopen time arrives (runs every minute, see routes/console.php; the server's
 * cron must run `php artisan schedule:run` every minute). Written to the audit log as a "system" action.
 *
 * Safe to run any number of times and at any moment: it acts once, and does nothing if maintenance is already off, no
 * time is set, the time has not come yet, or an admin cleared or moved it in the meantime. To switch maintenance off by
 * hand, from the terminal, see `php artisan platform:reopen`.
 */
class ReopenDueMaintenance extends Command
{
    protected $signature = 'maintenance:reopen-due';

    protected $description = 'Switch maintenance mode off if its scheduled reopen time has arrived';

    public function handle(PlatformSettings $settings): int
    {
        if ($settings->reopenIfDue()) {
            $this->info('Maintenance mode was switched off at its scheduled time.');
        }

        return self::SUCCESS;
    }
}
