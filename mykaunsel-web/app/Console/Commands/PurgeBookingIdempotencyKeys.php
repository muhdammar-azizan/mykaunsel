<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use App\Models\BookingIdempotencyKey;
use Illuminate\Console\Command;

/**
 * Retention for booking request keys (runs daily, see routes/console.php): a key and its stored answer are kept for
 * `booking.idempotency_ttl_hours` (24 hours) and then deleted. One system audit entry with COUNTS only. --dry-run counts and changes nothing.
 * (A booking itself is never touched: the booking row is the record.)
 */
class PurgeBookingIdempotencyKeys extends Command
{
    protected $signature = 'bookings:purge-idempotency {--dry-run : Count what would be deleted, change nothing}';

    protected $description = 'Delete expired booking request keys (and the answers kept with them)';

    public function handle(): int
    {
        $expired = fn () => BookingIdempotencyKey::query()->where('expires_at', '<', now());

        if ($this->option('dry-run')) {
            $this->info("Would delete {$expired()->count()} expired booking request keys.");

            return self::SUCCESS;
        }

        $deleted = 0;

        Audit::asSystem(function () use ($expired, &$deleted) {
            $deleted = $expired()->delete();

            if ($deleted > 0) {
                Audit::log('booking.idempotency_purged', null, ['deleted' => $deleted, 'ttl_hours' => (int) config('booking.idempotency_ttl_hours')], 'system');
            }
        });

        $this->info("Deleted {$deleted} expired booking request keys.");

        return self::SUCCESS;
    }
}
