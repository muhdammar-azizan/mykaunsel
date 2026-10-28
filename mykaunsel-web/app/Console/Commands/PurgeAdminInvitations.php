<?php

namespace App\Console\Commands;

use App\Enums\AdminInvitationStatus;
use App\Facades\Audit;
use App\Models\AdminInvitation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Retention for Platform Admin invitations (runs daily, see routes/console.php; the period is `platform-admins.invitation.retention_days`,
 * 30 days).
 *
 * An invitation that is ACCEPTED, REVOKED or EXPIRED loses its name and email that many days after it reached that state:
 *   accepted  -> counted from accepted_at
 *   revoked   -> counted from revoked_at
 *   expired   -> counted from the day it ran out (expires_at), or from the day a newer invitation replaced it (superseded_at)
 * The row stays as an empty record: id, status, dates, who invited (user id), who accepted (user id) and the token hash (not personal).
 * A PENDING invitation that is still within its time is never touched: it is needed to accept it.
 *
 * One system audit entry with the COUNT only (never a name or an email). --dry-run counts and changes nothing.
 */
class PurgeAdminInvitations extends Command
{
    protected $signature = 'admin-invitations:purge {--dry-run : Count what would be erased, change nothing}';

    protected $description = 'Erase the name and email of old (accepted, expired or revoked) Platform Admin invitations';

    public function handle(): int
    {
        $days = max(1, (int) config('platform-admins.invitation.retention_days'));
        $cutoff = now()->subDays($days);

        $due = fn (): Builder => AdminInvitation::query()
            ->whereNull('personal_data_erased_at')
            ->where(function (Builder $q) use ($cutoff) {
                $q->where(fn ($x) => $x->where('status', AdminInvitationStatus::Accepted->value)->where('accepted_at', '<=', $cutoff))
                    ->orWhere(fn ($x) => $x->where('status', AdminInvitationStatus::Revoked->value)->where('revoked_at', '<=', $cutoff))
                    ->orWhere(fn ($x) => $x->where('status', AdminInvitationStatus::Expired->value)->where(fn ($y) => $y->where('superseded_at', '<=', $cutoff)->orWhere(fn ($z) => $z->whereNull('superseded_at')->where('expires_at', '<=', $cutoff))))
                    ->orWhere(fn ($x) => $x->where('status', AdminInvitationStatus::Pending->value)->where('expires_at', '<=', $cutoff));
            });

        if ($this->option('dry-run')) {
            $this->info("Would erase the name and email of {$due()->count()} invitations (older than {$days} days).");

            return self::SUCCESS;
        }

        $erased = 0;

        Audit::asSystem(function () use ($due, $days, &$erased) {
            DB::transaction(function () use ($due, $days, &$erased) {
                $due()->chunkById(200, function ($batch) use (&$erased) {
                    // active_email is already empty for these (only a pending invitation holds it); a pending one that ran out is cleared too.
                    $erased += AdminInvitation::query()->whereIn('id', $batch->pluck('id'))->update([
                        'name' => null,
                        'email' => null,
                        'active_email' => null,
                        'personal_data_erased_at' => now(),
                    ]);
                });

                if ($erased > 0) {
                    Audit::log('admin.invitations_purged', null, ['invitations' => $erased, 'retention_days' => $days], 'system');
                }
            });
        });

        $this->info("Erased the name and email of {$erased} invitations.");

        return self::SUCCESS;
    }
}
