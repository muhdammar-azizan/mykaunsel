<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\SubscriptionEndedMail;
use App\Notifications\SubscriptionEndingMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The warnings before a subscription ends (docs/PAYMENTS_PLAN.md, "Subscription end date"; the steps are in config/subscription-reminders.php).
 * Run every day by `subscriptions:remind`.
 *
 * - For each active organization with a plan, by the days left to the plan's LAST day (the last day counts whole): the admins at 60, 30, 14 and 7 days
 *   (e-mail and the bell), the counselors at 30 and 14 days (e-mail), and the admins once more when it has ended.
 * - Each step goes out ONCE for each last day (a renewal has a new last day, so its steps start again). The step is written down with a unique key
 *   before anything is sent, and only the run that wrote it sends it, so two runs at the same moment can not send twice. But a step is written down
 *   only when there is someone to tell, and it is taken back if not one message could be handed over, so a warning is never lost to an empty list or a failure:
 *   the next run tries again (an admin who confirms an address tomorrow still gets today's warning).
 * - A run that comes late sends the most urgent warning that applies, once, and writes down the earlier steps as done: nobody gets
 *   "ends in 60 days" and "ends in 30 days" on the same morning. One that ended more than a week before the first run is not announced.
 * - Only people who can be written to: active, with a confirmed address. Always sent: nobody can switch these off.
 */
class SubscriptionReminders
{
    /** An ending is announced only if it happened at most this many days ago. */
    public const ENDED_NOTICE_DAYS = 7;

    public function __construct(private readonly BillingMail $billing, private readonly NotificationCenter $center) {}

    /** @return array{organizations: int, admin_mails: int, counselor_mails: int, ended: int} */
    public function run(bool $dryRun = false): array
    {
        $counts = ['organizations' => 0, 'admin_mails' => 0, 'counselor_mails' => 0, 'ended' => 0];

        $organizations = Organization::query()
            ->where('subscription_status', OrgStatus::Active->value)
            ->whereIn('org_type', [OrgType::University->value, OrgType::Corporate->value, OrgType::Clinic->value])
            ->whereNotNull('subscription_ends_at')->orderBy('id')->get();

        foreach ($organizations as $org) {
            $endsOn = $org->subscription_ends_at->toDateString();
            $daysLeft = $org->daysLeft();
            $sent = DB::table('subscription_reminders')->where(['org_id' => $org->getKey(), 'ends_on' => $endsOn])->get()->groupBy('audience')
                ->map(fn (Collection $rows) => $rows->pluck('step')->map(fn ($step) => (int) $step)->all());
            $told = false;

            if ($daysLeft >= 0) {
                $told = $this->steps($org, $endsOn, 'admin', (array) config('subscription-reminders.admin_days'), $daysLeft, $sent->get('admin', []), $dryRun, $counts) || $told;
                $told = $this->steps($org, $endsOn, 'counselor', (array) config('subscription-reminders.counselor_days'), $daysLeft, $sent->get('counselor', []), $dryRun, $counts) || $told;
            } elseif ($daysLeft >= -self::ENDED_NOTICE_DAYS && ! in_array(0, $sent->get('admin', []), true)) {
                $told = $this->ended($org, $endsOn, $dryRun, $counts);
            }

            $counts['organizations'] += $told ? 1 : 0;
        }

        if (! $dryRun && ($counts['admin_mails'] + $counts['counselor_mails'] + $counts['ended']) > 0) {
            Audit::asSystem(fn () => Audit::log('subscription.reminders_sent', null, $counts, 'system'));
        }

        return $counts;
    }

    /**
     * @param  list<int>  $steps  the steps of this audience
     * @param  list<int>  $done  the steps already written down for this last day
     */
    private function steps(Organization $org, string $endsOn, string $audience, array $steps, int $daysLeft, array $done, bool $dryRun, array &$counts): bool
    {
        $missing = array_values(array_diff(array_filter($steps, fn (int $step) => $daysLeft <= $step), $done));

        if ($missing === []) {
            return false;
        }

        $people = $audience === 'admin' ? $this->billing->recipients($org) : $this->counselors($org);

        if ($people->isEmpty()) {
            return false;                                                 // nobody to tell: nothing is written down, the next run looks again
        }

        if ($dryRun) {
            $counts[$audience === 'admin' ? 'admin_mails' : 'counselor_mails'] += $people->count();

            return true;
        }

        $fresh = $this->writeDown($org, $endsOn, $audience, $missing);

        if ($fresh === []) {
            return false;                                                 // another run wrote them down first and sends them
        }

        $sent = $this->send($people, new SubscriptionEndingMail($org->getKey(), $daysLeft, $audience, $endsOn));

        if ($sent === 0) {
            $this->takeBack($org, $endsOn, $audience, $fresh);

            return false;
        }

        $counts[$audience === 'admin' ? 'admin_mails' : 'counselor_mails'] += $sent;

        if ($audience === 'admin') {
            foreach ($people as $admin) {
                $this->center->notify($admin, 'subscription.ending', $org, $org->name, $endsOn.'-'.min($fresh));
            }
        }

        return true;
    }

    private function ended(Organization $org, string $endsOn, bool $dryRun, array &$counts): bool
    {
        $admins = $this->billing->recipients($org);

        if ($admins->isEmpty()) {
            return false;
        }

        if ($dryRun) {
            $counts['ended'] += $admins->count();

            return true;
        }

        if ($this->writeDown($org, $endsOn, 'admin', [0]) === []) {
            return false;
        }

        $sent = $this->send($admins, new SubscriptionEndedMail($org->getKey(), $endsOn));

        if ($sent === 0) {
            $this->takeBack($org, $endsOn, 'admin', [0]);

            return false;
        }

        $counts['ended'] += $sent;

        foreach ($admins as $admin) {
            $this->center->notify($admin, 'subscription.ended', $org, $org->name, $endsOn);
        }

        return true;
    }

    /**
     * Writes the steps down; only the rows this run really inserted come back (a unique key refuses the rest).
     *
     * @param  list<int>  $steps
     * @return list<int>
     */
    private function writeDown(Organization $org, string $endsOn, string $audience, array $steps): array
    {
        return array_values(array_filter($steps, fn (int $step) => DB::table('subscription_reminders')
            ->insertOrIgnore(['org_id' => $org->getKey(), 'ends_on' => $endsOn, 'audience' => $audience, 'step' => $step, 'sent_at' => now()]) === 1));
    }

    /** @param  list<int>  $steps */
    private function takeBack(Organization $org, string $endsOn, string $audience, array $steps): void
    {
        DB::table('subscription_reminders')->where(['org_id' => $org->getKey(), 'ends_on' => $endsOn, 'audience' => $audience])->whereIn('step', $steps)->delete();
    }

    /** The organization's counselors who can be written to. @return Collection<int, User> */
    private function counselors(Organization $org): Collection
    {
        return User::query()->whereIn('id', Membership::query()->where('org_id', $org->getKey())
            ->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->pluck('user_id'))
            ->whereNotNull('email_verified_at')->whereNull('deleted_at')->get();
    }

    /**
     * A failing e-mail never stops the run: it is only reported. Returns how many people it was handed over to.
     *
     * @param  Collection<int, User>  $people
     */
    private function send(Collection $people, object $notification): int
    {
        $sent = 0;

        foreach ($people as $person) {
            try {
                $person->notify($notification);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }
}
