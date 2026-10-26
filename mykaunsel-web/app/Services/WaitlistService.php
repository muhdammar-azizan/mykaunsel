<?php

namespace App\Services;

use App\Exceptions\CounselorDecisionBlocked;
use App\Facades\Audit;
use App\Jobs\SendWaitlistEmail;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistSuppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

/**
 * The waitlist for closed sign-ups (Organization and Counselor on the web; docs/WAITLIST.md).
 *
 * What it promises people: ONE email, only when that kind of sign-up reopens, and an opt-out in that email. So:
 *  - Signing up is silent: whatever happened (new address, already listed, opted out before, a bot), the caller gives the
 *    same answer. Nothing here can be used to find out who is on the list.
 *  - Notifying claims every address atomically (waiting -> notified in one conditional update), so running it twice, or
 *    two admins at once, can never send an address a second email.
 *  - Opting out deletes the address and keeps only a keyed hash, so the choice survives the address.
 *  - No address ever goes into the audit log.
 */
class WaitlistService
{
    private ?bool $tables = null;

    public function __construct(private WaitlistHasher $hasher, private PlatformSettings $settings) {}

    /** @return list<string> */
    public function types(): array
    {
        return config('waitlist.types');
    }

    public function validType(string $type): bool
    {
        return in_array($type, $this->types(), true);
    }

    /** The waitlist works only with its own secret key (and its tables). Otherwise it is switched off and invisible. */
    public function enabled(): bool
    {
        // (The table check runs once per request, not on every call.)
        return $this->hasher->available() && ($this->tables ??= Schema::hasTable('waitlist_entries'));
    }

    /** Trim and lower-case: "Ana@Example.com " and "ana@example.com" are one address. */
    public static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Should the public form be offered for this kind of sign-up right now? Only while it is closed (an open one has a real
     * sign-up form), and only if the waitlist is switched on. (Maintenance hides every public page by itself.)
     */
    public function offered(string $type): bool
    {
        return $this->validType($type) && $this->enabled() && ! $this->settings->registrationOpen($type);
    }

    // ---- the public form -------------------------------------------------------------------------------------

    /**
     * The limits on the public form: per IP, per address and per kind of sign-up. They count every attempt the same way,
     * whatever the address, so hitting one tells you nothing about the list. Returns true if this attempt may go on (and counts it).
     */
    public function allowAttempt(string $type, string $email, string $ip): bool
    {
        $limits = config('waitlist.limits');
        $checks = [
            ['waitlist:ip:'.$ip, (int) $limits['per_ip_per_hour'], 3600],
            ['waitlist:email:'.$this->hasher->emailHash($type, self::normalise($email)), (int) $limits['per_email_per_day'], 86400],
            ["waitlist:global:{$type}", (int) $limits['global_per_hour'], 3600],
        ];

        foreach ($checks as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }

        foreach ($checks as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }

        return true;
    }

    /**
     * Put an address on the list. Silent on purpose (returns nothing): the caller answers the same for a new address, one
     * already listed, one that opted out, and one that was notified before.
     *
     *  - opted out before: not stored, same answer.
     *  - listed already: nothing changes.
     *  - notified before: they are asking again, so they go back to waiting (one more email, because they asked).
     * The same few queries run on every path, so the answer does not take noticeably longer for one case than another.
     * Two requests at the same instant cannot make two rows: the unique index decides, and the loser is ignored.
     */
    public function signUp(string $type, string $email): void
    {
        $email = self::normalise($email);
        $hash = $this->hasher->emailHash($type, $email);
        $suppressed = WaitlistSuppression::query()->where('type', $type)->where('email_hash', $hash)->exists();

        DB::transaction(function () use ($type, $email, $hash, $suppressed) {
            // A suppressed address is "inserted" nowhere: the condition is simply false, so the work looks the same.
            $suppressed ? WaitlistEntry::query()->whereRaw('1 = 0')->update(['status' => WaitlistEntry::WAITING]) : WaitlistEntry::query()->insertOrIgnore([
                'type' => $type, 'email' => $email, 'email_hash' => $hash, 'status' => WaitlistEntry::WAITING,
                'notified_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            WaitlistEntry::query()->where('type', $type)->where('email', $email)->where('status', WaitlistEntry::NOTIFIED)
                ->whereRaw($suppressed ? '1 = 0' : '1 = 1')
                ->update(['status' => WaitlistEntry::WAITING, 'notified_at' => null, 'emailed_at' => null, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    // ---- opting out -------------------------------------------------------------------------------------------

    /**
     * Opt out, from a link in an email: keep only the keyed hash, delete the address. Idempotent, and fine if the address
     * has already been deleted (for example by the retention rule): the choice is still remembered.
     */
    public function optOut(string $type, string $emailHash): void
    {
        DB::transaction(function () use ($type, $emailHash) {
            WaitlistSuppression::query()->insertOrIgnore(['type' => $type, 'email_hash' => $emailHash, 'created_at' => now()]);
            WaitlistEntry::query()->where('type', $type)->where('email_hash', $emailHash)->delete();
        });
    }

    // ---- what the Platform Admin sees ----------------------------------------------------------------------------

    /**
     * Numbers per kind of sign-up (organization and counselor only; individuals have no waitlist). The opted-out number is
     * only a count: nobody can see who they are.
     *
     * @return array<string, array{waiting: int, notified: int, opted_out: int}>
     */
    public function counts(): array
    {
        if (! Schema::hasTable('waitlist_entries')) {
            return collect($this->types())->mapWithKeys(fn ($type) => [$type => ['waiting' => 0, 'notified' => 0, 'opted_out' => 0]])->all();
        }

        $byStatus = WaitlistEntry::query()->selectRaw('type, status, count(*) as total')->groupBy('type', 'status')->get();
        $optedOut = WaitlistSuppression::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

        return collect($this->types())->mapWithKeys(fn (string $type) => [$type => [
            'waiting' => (int) $byStatus->where('type', $type)->where('status', WaitlistEntry::WAITING)->sum('total'),
            'notified' => (int) $byStatus->where('type', $type)->where('status', WaitlistEntry::NOTIFIED)->sum('total'),
            'opted_out' => (int) ($optedOut[$type] ?? 0),
        ]])->all();
    }

    /** Only the people still waiting, per kind (for the cards and the Dashboard banner). @return array<string, int> */
    public function waitingCounts(): array
    {
        return array_map(fn (array $c) => $c['waiting'], $this->counts());
    }

    /** Why "Notify all" is not allowed right now (empty list = it is). @return list<string> */
    public function notifyBlockers(string $type): array
    {
        $blockers = [];

        if (! $this->enabled()) {
            $blockers[] = 'The waitlist is switched off (no WAITLIST_HMAC_KEY).';
        }

        if (! $this->settings->registrationOpen($type)) {
            $blockers[] = 'Sign-up is still closed. Open it first: the email says it is open.';
        }

        if ($this->settings->maintenanceOn()) {
            $blockers[] = 'Maintenance mode is on. Turn it off first: people would find a maintenance page.';
        }

        if (($this->counts()[$type]['waiting'] ?? 0) === 0) {
            $blockers[] = 'Nobody is waiting.';
        }

        return $blockers;
    }

    /**
     * Email everyone still waiting for this kind of sign-up, once. Every address is claimed (waiting -> notified) by a
     * conditional update before its email is queued, so a second run, or two at once, sends nothing twice. The emails are
     * queued after the commit and spread out (`waitlist.send_per_minute` a minute). If one cannot be sent it goes back to
     * waiting and can be tried again. The audit entry holds counts only: never an address.
     *
     * @return array{sent: int, failed: int} sent = claimed and queued; failed = could not be claimed (changed meanwhile)
     *
     * @throws CounselorDecisionBlocked
     */
    public function notifyAll(User $admin, string $type): array
    {
        if ($blockers = $this->notifyBlockers($type)) {
            throw new CounselorDecisionBlocked($blockers);
        }

        $perMinute = max(1, (int) config('waitlist.send_per_minute'));
        $sent = 0;
        $failed = 0;

        DB::transaction(function () use ($admin, $type, $perMinute, &$sent, &$failed) {
            WaitlistEntry::query()->ofType($type)->waiting()->orderBy('id')->chunkById((int) config('waitlist.chunk'), function ($entries) use ($perMinute, &$sent, &$failed) {
                foreach ($entries as $entry) {
                    $claimedAt = $this->claim($entry->id);

                    if ($claimedAt === null) {
                        $failed++;

                        continue;
                    }

                    SendWaitlistEmail::dispatch($entry->id, $claimedAt->toIso8601String())->afterCommit()->delay(now()->addSeconds(intdiv($sent, $perMinute) * 60));
                    $sent++;
                }
            });

            Audit::log('waitlist.notified', null, ['type' => $type, 'sent' => $sent, 'failed' => $failed], $admin);
        });

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Claim one address for notification: waiting -> notified, only if it is still waiting. The one thing that decides who
     * gets an email, so it can only ever happen once per address and sign-up.
     *
     * @return Carbon|null the claim time, or null if someone else got there first (or it was removed)
     */
    public function claim(int $entryId): ?Carbon
    {
        $now = now()->startOfSecond();

        $won = WaitlistEntry::query()->where('id', $entryId)->where('status', WaitlistEntry::WAITING)
            ->update(['status' => WaitlistEntry::NOTIFIED, 'notified_at' => $now, 'emailed_at' => null, 'updated_at' => $now]);

        return $won === 1 ? $now : null;
    }

    /** An email could not be sent: put the address back to waiting, but only if it is still the same claim. */
    public function release(int $entryId, Carbon $claimedAt): void
    {
        WaitlistEntry::query()->where('id', $entryId)->where('status', WaitlistEntry::NOTIFIED)->where('notified_at', $claimedAt)
            ->update(['status' => WaitlistEntry::WAITING, 'notified_at' => null, 'emailed_at' => null, 'updated_at' => now()]);
    }

    /** Remove one address (a request from that person). The audit entry has the record's id only. */
    public function remove(User $admin, WaitlistEntry $entry): void
    {
        DB::transaction(function () use ($admin, $entry) {
            $gone = WaitlistEntry::query()->where('id', $entry->id)->delete();

            if ($gone === 1) {
                Audit::log('waitlist.entry_removed', null, ['entry_id' => $entry->id, 'type' => $entry->type], $admin);
            }
        });
    }

    /**
     * Retention (daily): addresses notified more than `notified_days` ago, and addresses never notified that signed up more
     * than `waiting_days` ago. The opt-out hashes stay: that is a deliberate choice (docs/WAITLIST.md).
     *
     * @return array{notified: int, waiting: int}
     */
    public function purge(bool $dryRun = false): array
    {
        $notified = WaitlistEntry::query()->where('status', WaitlistEntry::NOTIFIED)->where('notified_at', '<=', now()->subDays((int) config('waitlist.retention.notified_days')));
        $waiting = WaitlistEntry::query()->where('status', WaitlistEntry::WAITING)->where('created_at', '<=', now()->subDays((int) config('waitlist.retention.waiting_days')));

        if ($dryRun) {
            return ['notified' => $notified->count(), 'waiting' => $waiting->count()];
        }

        return Audit::asSystem(fn () => DB::transaction(function () use ($notified, $waiting) {
            $removed = ['notified' => $notified->delete(), 'waiting' => $waiting->delete()];

            if (array_sum($removed) > 0) {
                Audit::log('waitlist.purged', null, $removed, 'system');
            }

            return $removed;
        }));
    }
}
