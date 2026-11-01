<?php

namespace App\Console\Commands;

use App\Services\SubscriptionReminders;
use Illuminate\Console\Command;

/**
 * The daily warnings before an organization's subscription ends (see SubscriptionReminders and config/subscription-reminders.php). Safe to run twice: each
 * step is sent once. Use --dry-run to see how many e-mails would go out without sending or writing anything down.
 */
class RemindSubscriptionEnding extends Command
{
    protected $signature = 'subscriptions:remind {--dry-run : Count what would be sent, send and write down nothing}';

    protected $description = 'Warn organizations (and their counselors) before a subscription ends, and once when it has ended';

    public function handle(SubscriptionReminders $reminders): int
    {
        $done = $reminders->run((bool) $this->option('dry-run'));
        $verb = $this->option('dry-run') ? 'Would send' : 'Sent';

        $this->info("{$verb} {$done['admin_mails']} warnings to admins, {$done['counselor_mails']} to counselors and {$done['ended']} \"it has ended\" notices, for {$done['organizations']} organizations.");

        return self::SUCCESS;
    }
}
