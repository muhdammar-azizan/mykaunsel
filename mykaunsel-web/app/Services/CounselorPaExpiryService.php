<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Exceptions\InvalidCounselorTransition;
use App\Facades\Audit;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Notifications\CounselorPaRenewalReminder;
use App\Support\PaValidity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The daily practising-certificate (PA) check, run by `counselors:check-pa-expiry`.
 *
 *  1. An approved counselor whose PA has expired is looked up in the LKM directory first: if LKM already shows a
 *     newer date the profile is simply updated (they renewed); otherwise they are SUSPENDED by "system" (source
 *     `system`, audit `counselor.pa_expired_suspended`). Pending session requests are closed and the clients told;
 *     sessions already booked are left alone. Reactivating stays a manual decision on the Counselors page.
 *  2. An approved counselor whose PA runs out soon gets an email at each step in config('counselors.pa_reminder_days'),
 *     once per step per PA date.
 *
 * Safe to run twice or late: suspended counselors are skipped, each reminder step is remembered on the profile, and
 * one counselor failing never stops the others.
 */
class CounselorPaExpiryService
{
    public function __construct(private CounselorStatusService $status, private LkmVerificationService $lkm) {}

    /**
     * The lists hold KB numbers. With $dryRun nothing is changed or sent and the lists say what WOULD happen.
     *
     * @return array{suspended: list<string>, extended: list<string>, reminded: list<string>, errors: int}
     */
    public function run(bool $dryRun = false): array
    {
        $result = ['suspended' => [], 'extended' => [], 'reminded' => [], 'errors' => 0];

        Audit::asSystem(function () use ($dryRun, &$result) {
            $this->expired($dryRun, $result);
            $this->reminders($dryRun, $result);
        });

        return $result;
    }

    /** @param array{suspended: list<string>, extended: list<string>, reminded: list<string>, errors: int} $result */
    private function expired(bool $dryRun, array &$result): void
    {
        CounselorProfile::query()
            ->where('verification_status', VerificationStatus::Approved->value)
            ->whereNotNull('pa_valid_until')
            ->whereDate('pa_valid_until', '<', $this->today()->toDateString())
            ->with('user')
            ->chunkById(100, function ($profiles) use ($dryRun, &$result) {
                foreach ($profiles as $profile) {
                    try {
                        $this->handleExpired($profile, $dryRun, $result);
                    } catch (Throwable $e) {
                        $result['errors']++;
                        report($e);
                    }
                }
            });
    }

    /** @param array{suspended: list<string>, extended: list<string>, reminded: list<string>, errors: int} $result */
    private function handleExpired(CounselorProfile $profile, bool $dryRun, array &$result): void
    {
        if ($dryRun) {
            $record = LkmDirectorySnapshot::where('kb_number', $profile->kb_number)->first();
            $renewed = $record !== null && $record->status === LkmVerificationService::ACTIVE_STATUS && PaValidity::isValid($record->pa_valid_until);
            $result[$renewed ? 'extended' : 'suspended'][] = $profile->kb_number;

            return;
        }

        // 1. Did they renew? LKM is the source of truth for the date. Done under a lock, with its own audit entry.
        $outcome = DB::transaction(function () use ($profile) {
            $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);

            if ($locked->verification_status !== VerificationStatus::Approved || PaValidity::isValid($locked->pa_valid_until)) {
                return 'skip';
            }

            $changes = $this->lkm->refreshPaDate($locked);

            if ($changes !== []) {
                Audit::log('counselor.lkm_rechecked', $locked, [
                    'summary' => 'PA date updated from the LKM record by the daily check',
                    'changes' => $changes,
                ]);
            }

            return PaValidity::isValid($locked->pa_valid_until) ? 'extended' : 'expired';
        });

        if ($outcome === 'extended') {
            $result['extended'][] = $profile->kb_number;

            return;
        }

        if ($outcome === 'skip') {
            return;
        }

        // 2. Still expired: suspend.
        $profile->refresh();

        try {
            $this->status->suspend($profile, null, $this->suspensionReason($profile), false, 'system');
        } catch (InvalidCounselorTransition) {
            return; // an admin got there first
        }

        $result['suspended'][] = $profile->kb_number;
    }

    /** @param array{suspended: list<string>, extended: list<string>, reminded: list<string>, errors: int} $result */
    private function reminders(bool $dryRun, array &$result): void
    {
        $steps = collect(config('counselors.pa_reminder_days', [30, 7, 1]))->map(fn ($d) => (int) $d)->sort()->values();

        if ($steps->isEmpty()) {
            return;
        }

        $today = $this->today();

        CounselorProfile::query()
            ->where('verification_status', VerificationStatus::Approved->value)
            ->whereNotNull('pa_valid_until')
            ->whereDate('pa_valid_until', '>=', $today->toDateString())
            ->whereDate('pa_valid_until', '<=', $today->copy()->addDays($steps->last())->toDateString())
            ->with('user')
            ->chunkById(100, function ($profiles) use ($steps, $dryRun, &$result) {
                foreach ($profiles as $profile) {
                    try {
                        $this->remind($profile, $steps->all(), $dryRun, $result);
                    } catch (Throwable $e) {
                        $result['errors']++;
                        report($e);
                    }
                }
            });
    }

    /**
     * @param  list<int>  $steps  ascending, e.g. [1, 7, 30]
     * @param  array{suspended: list<string>, extended: list<string>, reminded: list<string>, errors: int}  $result
     */
    private function remind(CounselorProfile $profile, array $steps, bool $dryRun, array &$result): void
    {
        $daysLeft = PaValidity::daysLeft($profile->pa_valid_until);

        if ($daysLeft === null || $daysLeft < 0 || PaValidity::isExpired($profile->pa_valid_until)) {
            return;
        }

        // The step we are in: the smallest step that is still >= the days left (5 days left => the 7-day step).
        $step = collect($steps)->first(fn ($s) => $s >= $daysLeft);

        if ($step === null) {
            return;
        }

        $alreadySent = $profile->pa_reminder_for !== null
            && $profile->pa_reminder_for->isSameDay($profile->pa_valid_until)
            && $profile->pa_reminder_days !== null
            && $profile->pa_reminder_days <= $step;

        if ($alreadySent) {
            return;
        }

        if ($dryRun) {
            $result['reminded'][] = $profile->kb_number;

            return;
        }

        // The email first: the profile and the audit entry say "sent" only if it really was (a failure is retried tomorrow).
        $profile->user->notify(new CounselorPaRenewalReminder($profile->pa_valid_until->copy(), false));

        DB::transaction(function () use ($profile, $step, $daysLeft) {
            $profile->update(['pa_reminder_for' => $profile->pa_valid_until->toDateString(), 'pa_reminder_days' => $step]);

            Audit::log('counselor.renewal_reminder_sent', $profile, [
                'summary' => 'Automatic reminder: PA valid until '.$profile->pa_valid_until->format('j M Y').' ('.$daysLeft.' '.($daysLeft === 1 ? 'day' : 'days').' left)',
                'reminder_step_days' => $step,
            ]);
        });

        $result['reminded'][] = $profile->kb_number;
    }

    private function suspensionReason(CounselorProfile $profile): string
    {
        return 'Your practising certificate (PA) expired on '.$profile->pa_valid_until->format('j M Y').'. '
            .'Please renew it with the Lembaga Kaunselor Malaysia (LKM) and reply to this email, so we can check and reactivate your account.';
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }
}
