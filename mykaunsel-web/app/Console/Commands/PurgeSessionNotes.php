<?php

namespace App\Console\Commands;

use App\Facades\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retention for counselor session notes (runs daily, see routes/console.php; the period is in config/session-notes.php). A note is deleted for good
 * this many years after the session it is about (the note's own date when the booking no longer has a time). One system audit entry with a count only.
 * Safe to run twice. Use --dry-run to see how many would be erased without changing anything.
 */
class PurgeSessionNotes extends Command
{
    protected $signature = 'session-notes:purge {--dry-run : Count what would be erased, change nothing}';

    protected $description = 'Erase session notes older than the retention period';

    public function handle(): int
    {
        $cutoff = now()->subYears((int) config('session-notes.retention_years'))->format('Y-m-d');

        $due = fn () => DB::table('session_notes')
            ->join('bookings', 'bookings.id', '=', 'session_notes.booking_id')
            ->leftJoin('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->whereRaw('COALESCE(DATE(calendar_entries.entry_date), DATE(session_notes.created_at)) < ?', [$cutoff]);

        if ($this->option('dry-run')) {
            $this->info("Would erase {$due()->count()} session notes.");

            return self::SUCCESS;
        }

        $erased = 0;

        Audit::asSystem(function () use ($due, &$erased) {
            DB::transaction(function () use ($due, &$erased) {
                $ids = $due()->pluck('session_notes.id');

                foreach ($ids->chunk(500) as $chunk) {
                    $erased += DB::table('session_notes')->whereIn('id', $chunk->all())->delete();
                }
            });

            if ($erased > 0) {
                Audit::log('session_note.purged', null, ['notes' => $erased, 'years' => (int) config('session-notes.retention_years')], 'system');
            }
        });

        $this->info("Erased {$erased} session notes.");

        return self::SUCCESS;
    }
}
