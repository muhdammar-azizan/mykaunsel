<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Services\NotificationCenter;
use Illuminate\Console\Command;

/**
 * Retention for the in-app notifications (runs daily, see routes/console.php; the periods are `notifications.retention`, 90 days for
 * read ones and 180 days for unread ones, counted from when the thing happened).
 *
 * One system audit entry with COUNTS only (never a name or a label). --dry-run counts and changes nothing.
 */
class PurgeNotifications extends Command
{
    protected $signature = 'notifications:purge {--dry-run : Count what would be deleted, change nothing}';

    protected $description = 'Delete old in-app notifications (read after 90 days, unread after 180 days)';

    public function handle(NotificationCenter $center): int
    {
        $readDays = max(1, (int) config('notifications.retention.read_days'));
        $unreadDays = max(1, (int) config('notifications.retention.unread_days'));

        if ($this->option('dry-run')) {
            $would = $center->purge(dryRun: true);
            $this->info("Would delete {$would['read']} read notifications (older than {$readDays} days) and {$would['unread']} unread ones (older than {$unreadDays} days).");

            return self::SUCCESS;
        }

        $deleted = ['read' => 0, 'unread' => 0];

        Audit::asSystem(function () use ($center, $readDays, $unreadDays, &$deleted) {
            $deleted = $center->purge();

            if ($deleted['read'] + $deleted['unread'] > 0) {
                Audit::log('notification.purged', null, ['read' => $deleted['read'], 'unread' => $deleted['unread'], 'read_days' => $readDays, 'unread_days' => $unreadDays], 'system');
            }
        });

        $this->info("Deleted {$deleted['read']} read and {$deleted['unread']} unread notifications.");

        return self::SUCCESS;
    }
}
