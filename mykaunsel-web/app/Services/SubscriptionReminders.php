<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\Invoice;
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
 * - Each step goes out ONCE for each last day (a renewal has a new last day, so its steps start again): the step is written down first, with a unique
 *   key, and only the run that wrote it sends it, so two runs at the same moment can not send twice.
 * - A run that comes late (the server was off) sends the most urgent warning that applies, once, and writes down the earlier steps as done: nobody gets
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
        $today = Invoice::today();
        $counts = ['organizations' => 0, 'admin_mails' => 0, 'counselor_mails' => 0, 'ended' => 0];

        $organizations = Organization::query()
            ->where('subscription_status', OrgStatus::Active->value)
            ->whereIn('org_type', [OrgType::University->value, OrgType::Corporate->value, OrgType::Clinic->value])
            ->whereNotNull('subscription_ends_at')->orderBy('id')->get();

        foreach ($organizations as $org) {
            $endsOn = $org->subscription_ends_at->toDateString();
            $daysLeft = (int) $today->diffInDays(\Illuminate\Support\Carbon::parse($endsOn, $today->getTimezone())->startOfDay(), false);
            $told = false;

            if ($daysLeft >= 0) {
                $told = $this->steps($org, $endsOn, 'admin', (array) config('subscription-reminders.admin_days'), $daysLeft, $dryRun, $counts) || $told;
                $told = $this->steps($org, $endsOn, 'counselor', (array) config('subscription-reminders.counselor_days'), $daysLeft, $dryRun, $counts) || $told;
            } elseif ($daysLeft >= -self::ENDED_NOTICE_DAYS) {
                $told = $this->ended($org, $endsOn, $dryRun, $counts);
            }

            $counts['organizations'] += $told ? 1 : 0;
        }

        if (! $dryRun && ($counts['admin_mails'] + $counts['counselor_mails'] + $counts['ended']) > 0) {
            Audit::asSystem(fn () => Audit::log('subscription.reminders_sent', null, $counts, 'system'));
        }

        return $counts;
    }

    /** @param  list<int>  $steps */
    private function steps(Organization $org, string $endsOn, string $audience, array $steps, int $daysLeft, bool $dryRun, array &$counts): bool
    {
        $applicable = array_values(array_filter($steps, fn (int $step) => $daysLeft <= $step));

        if ($applicable === []) {
            return false;
        }

        $fresh = [];

        foreach ($applicable as $step) {
            if ($dryRun) {
                if (! DB::table('subscription_reminders')->where(['org_id' => $org->getKey(), 'ends_on' => $endsOn, 'audience' => $audience, 'step' => $step])->exists()) {
                    $fresh[] = $step;
                }

                continue;
            }

            // Written down first: only the run that wrote the row sends the e-mail.
            if (DB::table('subscription_reminders')->insertOrIgnore(['org_id' => $org->getKey(), 'ends_on' => $endsOn, 'audience' => $audience, 'step' => $step, 'sent_at' => now()]) === 1) {
                $fresh[] = $step;
            }
        }

        if ($fresh === []) {
            return false;
        }

        if ($audience === 'admin') {
            $admins = $this->billing->recipients($org);
            $counts['admin_mails'] += $admins->count();

            if (! $dryRun) {
                $this->tellAdmins($org, $admins, $daysLeft, $endsOn.'-'.min($fresh));
            }
        } else {
            $counselors = $this->counselors($org);
            $counts['counselor_mails'] += $counselors->count();

            if (! $dryRun) {
                $this->send($counselors, new SubscriptionEndingMail($org->getKey(), $daysLeft, 'counselor'));
            }
        }

        return true;
    }

    private function ended(Organization $org, string $endsOn, bool $dryRun, array &$counts): bool
    {
        $key = ['org_id' => $org->getKey(), 'ends_on' => $endsOn, 'audience' => 'admin', 'step' => 0];

        if ($dryRun ? DB::table('subscription_reminders')->where($key)->exists() : DB::table('subscription_reminders')->insertOrIgnore($key + ['sent_at' => now()]) !== 1) {
            return false;
        }

        $admins = $this->billing->recipients($org);
        $counts['ended'] += $admins->count();

        if (! $dryRun) {
            $this->send($admins, new SubscriptionEndedMail($org->getKey()));

            foreach ($admins as $admin) {
                $this->center->notify($admin, 'subscription.ended', $org, $org->name, $endsOn);
            }
        }

        return true;
    }

    /** @param  Collection<int, User>  $admins */
    private function tellAdmins(Organization $org, Collection $admins, int $daysLeft, string $occurrence): void
    {
        $this->send($admins, new SubscriptionEndingMail($org->getKey(), $daysLeft, 'admin'));

        foreach ($admins as $admin) {
            $this->center->notify($admin, 'subscription.ending', $org, $org->name, $occurrence);
        }
    }

    /** The organization's counselors who can be written to. @return Collection<int, User> */
    private function counselors(Organization $org): Collection
    {
        return User::query()->whereIn('id', Membership::query()->where('org_id', $org->getKey())
            ->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->pluck('user_id'))
            ->whereNotNull('email_verified_at')->whereNull('deleted_at')->get();
    }

    /** A failing e-mail never stops the run: it is only reported. @param  Collection<int, User>  $people */
    private function send(Collection $people, object $notification): void
    {
        foreach ($people as $person) {
            try {
                $person->notify($notification);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
