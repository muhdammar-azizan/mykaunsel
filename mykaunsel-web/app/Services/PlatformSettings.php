<?php

namespace App\Services;

use App\Facades\Audit;
use App\Models\PlatformSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Platform-wide switches and wording: which sign-ups are open, maintenance mode and the messages shown for them.
 *
 * Reads are cheap (one small query per request, remembered). Every change locks the rows it touches, ignores a
 * switch that is already in the wanted state (no empty audit entry) and writes its audit entry in the same transaction.
 */
class PlatformSettings
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /** @return list<string> */
    public function roles(): array
    {
        return array_keys(config('platform-settings.registration_roles'));
    }

    public function registrationOpen(string $role): bool
    {
        $this->assertRole($role);

        return (bool) ($this->all()["registration.{$role}"] ?? true);
    }

    /** @return array<string, bool> role => open */
    public function registrationStatus(): array
    {
        return collect($this->roles())->mapWithKeys(fn ($role) => [$role => $this->registrationOpen($role)])->all();
    }

    /**
     * Is the individual sign-up on the WEB switched on at all (config `registration.web_individual_enabled`)? Individuals
     * normally sign up in the mobile app only; the web form is kept for a later web version.
     */
    public function webIndividualEnabled(): bool
    {
        return (bool) config('registration.web_individual_enabled');
    }

    /**
     * May a NEW individual account be created from the web right now? Both the config door and the Registration Control
     * switch must be open. Any way of making a new account on the web must ask this: the sign-up form, and "Continue with
     * Google" if it is ever added (an existing account signing in with Google is not sign-up and is not asked).
     */
    public function canCreateIndividualOnWeb(): bool
    {
        return $this->webIndividualEnabled() && $this->registrationOpen('individual');
    }

    /**
     * The commission (in percent, 0 to 100, at most two decimals) the platform keeps from sessions of clinics and independent
     * counselors. A record only: no payment is processed. The example value from config/pricing.php is used until one is saved.
     */
    public function commissionPercent(): float
    {
        $saved = $this->all()['pricing.commission_percent'] ?? null;

        return is_numeric($saved) ? (float) $saved : (float) config('pricing.commission_percent_default');
    }

    /**
     * Save the commission. Written with its audit entry in one transaction; saving the same value again changes nothing and logs nothing.
     *
     * @return bool whether it changed
     *
     * @throws InvalidArgumentException when it is not a number from 0 to 100 with at most two decimals
     */
    public function setCommissionPercent(User $admin, string $percent): bool
    {
        $percent = trim($percent);

        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $percent) || (float) $percent > 100) {
            throw new InvalidArgumentException('The commission must be a number from 0 to 100, with at most two decimals.');
        }

        $new = round((float) $percent, 2);

        $changed = DB::transaction(function () use ($admin, $new) {
            $row = $this->lockedRow('pricing.commission_percent', (float) config('pricing.commission_percent_default'));
            $old = is_numeric($row->value) ? round((float) $row->value, 2) : (float) config('pricing.commission_percent_default');

            if ($old === $new) {
                return false;
            }

            $row->update(['value' => $new, 'updated_by' => $admin->id]);

            Audit::log('commission.updated', null, ['changes' => ['commission_percent' => ['old' => $old, 'new' => $new]]], $admin);

            return true;
        });

        $this->values = null;

        return $changed;
    }

    public function anyRegistrationOpen(): bool
    {
        return in_array(true, $this->registrationStatus(), true);
    }

    public function maintenanceOn(): bool
    {
        return (bool) ($this->all()['maintenance'] ?? false);
    }

    /** When maintenance switches itself off, or null when no time is set (or maintenance is off). */
    public function maintenanceReopenAt(): ?CarbonImmutable
    {
        $value = $this->all()['maintenance.reopen_at'] ?? null;

        if (! $this->maintenanceOn() || ! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null; // a damaged value must never matter more than "no time set"
        }
    }

    /** The set reopen time has passed but maintenance is still on (the scheduler is late or not running). */
    public function reopenOverdue(): bool
    {
        $at = $this->maintenanceReopenAt();

        return $at !== null && $at->lte(now());
    }

    /** What to put in Retry-After: seconds until the reopen time (at least 1), or the default while there is none. */
    public function retryAfterSeconds(): int
    {
        $at = $this->maintenanceReopenAt();

        if ($at === null) {
            return (int) config('platform-settings.retry_after_default');
        }

        return $at->lte(now()) ? 60 : max(1, (int) ceil(now()->diffInSeconds($at, false)));
    }

    /** The saved message (or the default when it was never set or was emptied). */
    public function message(string $key): string
    {
        $saved = $this->all()["message.{$key}"] ?? null;

        return is_string($saved) && trim($saved) !== '' ? $saved : (string) config("platform-settings.default_messages.{$key}");
    }

    /** The "sign-ups are closed" text for one role, or for every role when $role is null. */
    public function registrationClosedMessage(?string $role = null): string
    {
        $word = $role === null ? 'account' : config("platform-settings.registration_roles.{$role}.word", 'account');

        return str_replace('{role}', $word, $this->message('registration_closed'));
    }

    /**
     * Open or close sign-up for some roles. One audit entry per role that actually changed.
     * $admin is null when the "system" does it (the emergency command `platform:reopen`); $via then says so in the audit entry.
     *
     * @param  list<string>  $roles
     * @return list<string> the roles that changed
     */
    public function setRegistration(?User $admin, array $roles, bool $open, bool $forAll = false, ?string $via = null): array
    {
        foreach ($roles as $role) {
            $this->assertRole($role);
        }

        $changed = DB::transaction(function () use ($admin, $roles, $open, $forAll, $via) {
            $changed = [];

            foreach ($roles as $role) {
                $row = $this->lockedRow("registration.{$role}", true);

                if ((bool) $row->value === $open) {
                    continue;
                }

                $row->update(['value' => $open, 'updated_by' => $admin?->id]);

                Audit::log('settings.registration_toggled', null, array_filter([
                    'role' => $role,
                    'changes' => ['open' => ['old' => ! $open, 'new' => $open]],
                    'via' => $via,
                    'summary' => $via === 'command' ? 'Changed from the command line (platform:reopen)' : ($forAll ? 'Changed together with every other sign-up' : null),
                ], fn ($v) => $v !== null), $admin ?? 'system');

                $changed[] = $role;
            }

            return $changed;
        });

        $this->values = null;

        return $changed;
    }

    /**
     * Turn maintenance on (optionally with the time it switches itself off) or off (which also forgets any reopen time).
     *
     * @param  User|null  $admin  null when the "system" does it (the emergency command `platform:reopen`)
     * @param  CarbonInterface|null  $reopenAt  only used when turning ON
     * @return bool whether it changed
     */
    public function setMaintenance(?User $admin, bool $on, ?string $via = null, ?CarbonInterface $reopenAt = null): bool
    {
        $changed = DB::transaction(function () use ($admin, $on, $via, $reopenAt) {
            $row = $this->lockedRow('maintenance', false);
            $timeRow = $this->lockedRow('maintenance.reopen_at', '');

            if ((bool) $row->value === $on) {
                return false;
            }

            $row->update(['value' => $on, 'updated_by' => $admin?->id]);
            $summary = $via === 'command' ? 'Changed from the command line (platform:reopen)' : null;

            if ($on) {
                $new = $reopenAt !== null ? $this->instant($reopenAt) : '';
                $timeRow->update(['value' => $new, 'updated_by' => $admin?->id]);

                Audit::log('settings.maintenance_toggled', null, array_filter([
                    'changes' => ['maintenance' => ['old' => false, 'new' => true]] + ($new !== '' ? ['reopen_at' => ['old' => null, 'new' => $new]] : []),
                    'via' => $via,
                    'summary' => $summary,
                ], fn ($v) => $v !== null), $admin ?? 'system');

                return true;
            }

            // Off: a reopen time has no meaning any more.
            $old = (string) $timeRow->value;
            if ($old !== '') {
                $timeRow->update(['value' => '', 'updated_by' => $admin?->id]);
            }

            Audit::log('settings.maintenance_toggled', null, array_filter([
                'changes' => ['maintenance' => ['old' => true, 'new' => false]] + ($old !== '' ? ['reopen_at' => ['old' => $old, 'new' => null]] : []),
                'via' => $via,
                'summary' => $summary,
            ], fn ($v) => $v !== null), $admin ?? 'system');

            return true;
        });

        $this->values = null;

        return $changed;
    }

    /**
     * Set, change or clear (null) the time maintenance switches itself off. Only while maintenance is on.
     *
     * @return bool whether it changed (false: maintenance is off, or the time is the same)
     */
    public function setMaintenanceReopenAt(?User $admin, ?CarbonInterface $at): bool
    {
        $changed = DB::transaction(function () use ($admin, $at) {
            $row = $this->lockedRow('maintenance', false);
            $timeRow = $this->lockedRow('maintenance.reopen_at', '');

            if (! (bool) $row->value) {
                return false;
            }

            $old = (string) $timeRow->value;
            $new = $at !== null ? $this->instant($at) : '';

            if ($old === $new) {
                return false;
            }

            $timeRow->update(['value' => $new, 'updated_by' => $admin?->id]);

            Audit::log('settings.maintenance_reopen_scheduled', null, [
                'changes' => ['reopen_at' => ['old' => $old !== '' ? $old : null, 'new' => $new !== '' ? $new : null]],
                'summary' => $new === '' ? 'Reopen time removed' : ($old === '' ? 'Reopen time set' : 'Reopen time changed'),
            ], $admin ?? 'system');

            return true;
        });

        $this->values = null;

        return $changed;
    }

    /**
     * The scheduler's job (every minute): if maintenance is on and its reopen time has arrived, switch it off, as the
     * "system". Safe to run any number of times: it acts once, and not at all if the time was cleared, moved or already handled.
     *
     * @return bool whether maintenance was switched off now
     */
    public function reopenIfDue(): bool
    {
        $done = DB::transaction(function () {
            $row = $this->lockedRow('maintenance', false);
            $timeRow = $this->lockedRow('maintenance.reopen_at', '');
            $value = (string) $timeRow->value;

            if (! (bool) $row->value || $value === '') {
                return false;
            }

            try {
                $at = CarbonImmutable::parse($value);
            } catch (Throwable) {
                return false;
            }

            if ($at->gt(now())) {
                return false;
            }

            $row->update(['value' => false, 'updated_by' => null]);
            $timeRow->update(['value' => '', 'updated_by' => null]);

            Audit::log('settings.maintenance_auto_reopened', null, [
                'changes' => ['maintenance' => ['old' => true, 'new' => false], 'reopen_at' => ['old' => $value, 'new' => null]],
                'summary' => 'Maintenance ended at the scheduled time',
            ], 'system');

            return true;
        });

        $this->values = null;

        return $done;
    }

    /**
     * Save the wording. Only messages that really changed are written and logged (the audit entry says which one,
     * not what it says).
     *
     * @param  array<string, string>  $messages  key (registration_closed | maintenance) => text
     * @return list<string> keys that changed
     */
    public function updateMessages(User $admin, array $messages): array
    {
        $changed = DB::transaction(function () use ($admin, $messages) {
            $changed = [];

            foreach ($messages as $key => $text) {
                if (! array_key_exists($key, config('platform-settings.default_messages'))) {
                    throw new InvalidArgumentException("Unknown message [{$key}].");
                }

                $text = trim($text);
                $row = $this->lockedRow("message.{$key}", config("platform-settings.default_messages.{$key}"));

                if ($row->value === $text) {
                    continue;
                }

                $row->update(['value' => $text, 'updated_by' => $admin->id]);

                Audit::log('settings.message_updated', null, [
                    'message' => $key,
                    'summary' => $key === 'maintenance' ? 'Maintenance message edited' : 'Sign-up closed message edited',
                ], $admin);

                $changed[] = $key;
            }

            return $changed;
        });

        $this->values = null;

        return $changed;
    }

    /** @return array<string, mixed> */
    private function all(): array
    {
        if ($this->values === null) {
            try {
                $this->values = PlatformSetting::query()->pluck('value', 'key')->all();
            } catch (QueryException) {
                // The table does not exist yet (fresh checkout, migrations still running): behave as "everything open".
                $this->values = [];
            }
        }

        return $this->values;
    }

    private function lockedRow(string $key, mixed $default): PlatformSetting
    {
        return PlatformSetting::query()->lockForUpdate()->find($key)
            ?? PlatformSetting::create(['key' => $key, 'value' => $default]);
    }

    /** A moment as stored: ISO-8601 in UTC with a trailing Z (never a local time). */
    private function instant(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->toIso8601ZuluString();
    }

    private function assertRole(string $role): void
    {
        if (! in_array($role, $this->roles(), true)) {
            throw new InvalidArgumentException("Unknown sign-up role [{$role}].");
        }
    }
}
