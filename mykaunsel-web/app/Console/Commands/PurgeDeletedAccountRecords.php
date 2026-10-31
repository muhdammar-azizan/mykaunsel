<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for the bookings of deleted accounts (runs daily, see routes/console.php; docs/ACCOUNT_DELETION.md).
 *
 * Ninety days (`account.deleted_booking_days`) after an account was deleted, its bookings and time requests are deleted too. A booking is KEPT,
 * still without any name, when what a counselor wrote about the session (`session_notes`) or a complaint points at it: those belong to the
 * organization and to the complaint, not to the person. One system audit entry with COUNTS only. --dry-run counts and changes nothing.
 */
class PurgeDeletedAccountRecords extends Command
{
    protected $signature = 'accounts:purge-deleted {--dry-run : Count what would be deleted, change nothing}';

    protected $description = 'Delete the bookings of accounts deleted more than 90 days ago (keeping those a session note or a complaint needs)';

    public function handle(): int
    {
        $before = now()->subDays((int) config('account.deleted_booking_days'));

        $people = DB::table('users')->whereNotNull('deleted_at')->where('deleted_at', '<', $before)->pluck('id');

        $bookings = fn () => DB::table('bookings')->whereIn('user_id', $people)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('session_notes')->whereColumn('session_notes.booking_id', 'bookings.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('complaints')->whereColumn('complaints.booking_id', 'bookings.id'));

        if ($this->option('dry-run')) {
            $this->info("Would delete {$bookings()->count()} bookings of ".$people->count().' deleted accounts.');

            return self::SUCCESS;
        }

        $deleted = ['bookings' => 0, 'requests' => 0];

        Audit::asSystem(function () use ($bookings, $people, &$deleted) {
            DB::transaction(function () use ($bookings, $people, &$deleted) {
                $ids = $bookings()->pluck('id');

                // A request that became a booking points at it; it goes first.
                DB::table('booking_requests')->whereIn('booking_id', $ids)->update(['booking_id' => null]);
                $deleted['requests'] = DB::table('booking_requests')->whereIn('user_id', $people)->whereNull('booking_id')->delete();
                $deleted['bookings'] = DB::table('bookings')->whereIn('id', $ids)->delete();
            });

            if ($deleted['bookings'] + $deleted['requests'] > 0) {
                Audit::log('account.records_purged', null, $deleted + ['days' => (int) config('account.deleted_booking_days')], 'system');
            }
        });

        $this->info("Deleted {$deleted['bookings']} bookings and {$deleted['requests']} time requests of deleted accounts.");

        return self::SUCCESS;
    }
}
