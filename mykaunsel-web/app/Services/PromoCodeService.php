<?php

namespace App\Services;

use App\Exceptions\PricingChangeBlocked;
use App\Facades\Audit;
use App\Models\PromoCode;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Promo codes. There is no payment: a code only lowers the price that is recorded when a Platform Admin gives an organization a plan.
 *
 * - The code is stored in capitals, 4 to 32 characters (A-Z, 0-9, hyphen), and is unique without regard to capitals.
 * - Once a code has been redeemed (used, or linked to an assignment) its code, type, value, start and "applies to" are LOCKED. Only
 *   the end date, the usage limit and the disabled switch can change (the limit never below what is already used).
 * - A code that was never redeemed can be deleted; otherwise it can only be disabled.
 * - Two admins editing the same code: an edit says which version it saw and is refused when the code has changed since.
 * - Every change and its audit entry share one transaction. Audit metadata holds ids, numbers and dates only.
 * - Times are kept to the minute: a code is valid from the START of its first minute to the END of its last (:59).
 */
class PromoCodeService
{
    /**
     * @param  array{code: string, type: string, value: int, valid_from: CarbonInterface, valid_until: CarbonInterface, usage_limit: ?int, applies_to: string}  $data
     *
     * @throws PricingChangeBlocked
     */
    public function create(User $actor, array $data): PromoCode
    {
        $data = $this->normalise($data);
        $this->assertShape($data);

        try {
            return DB::transaction(function () use ($actor, $data) {
                if (PromoCode::query()->where('code', $data['code'])->exists()) {
                    throw $this->duplicate();
                }

                $promo = PromoCode::create($data + ['used_count' => 0, 'disabled' => false, 'version' => 1, 'created_by' => $actor->id]);

                Audit::log('promo.created', $promo, [
                    'promo_id' => $promo->id,
                    'type' => $promo->type,
                    'value' => $promo->value,
                    'valid_from' => $promo->valid_from->utc()->toIso8601String(),
                    'valid_until' => $promo->valid_until->utc()->toIso8601String(),
                    'usage_limit' => $promo->usage_limit,
                    'applies_to' => $promo->applies_to,
                ], $actor);

                return $promo;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate(); // another admin created the same code at the same moment
        }
    }

    /**
     * @param  array{code: string, type: string, value: int, valid_from: CarbonInterface, valid_until: CarbonInterface, usage_limit: ?int, applies_to: string}  $data
     * @return array{promo: PromoCode, changed: bool}
     *
     * @throws PricingChangeBlocked
     */
    public function update(User $actor, PromoCode $promo, int $seenVersion, array $data): array
    {
        $data = $this->normalise($data);
        $this->assertShape($data);

        try {
            $changed = DB::transaction(function () use ($actor, $promo, $seenVersion, $data) {
                $current = PromoCode::query()->lockForUpdate()->findOrFail($promo->getKey());

                if ($current->version !== $seenVersion) {
                    throw $this->conflict();
                }

                $new = $data + [];
                $changes = [];

                foreach (['type', 'value', 'usage_limit', 'applies_to'] as $field) {
                    if ($current->{$field} !== $new[$field]) {
                        $changes[$field] = ['old' => $current->{$field}, 'new' => $new[$field]];
                    }
                }
                foreach (['valid_from', 'valid_until'] as $field) {
                    if (! $this->sameMinute($current->{$field}, $new[$field])) {
                        $changes[$field] = ['old' => $current->{$field}->utc()->toIso8601String(), 'new' => $new[$field]->utc()->toIso8601String()];
                    }
                }
                $codeChanged = $current->code !== $new['code'];

                if ($changes === [] && ! $codeChanged) {
                    return false; // nothing to save: no new version, no audit entry
                }

                $locked = array_intersect(array_keys($changes), ['type', 'value', 'applies_to', 'valid_from']);
                if ($codeChanged) {
                    $locked[] = 'code';
                }

                if ($locked !== [] && $current->isRedeemed()) {
                    throw new PricingChangeBlocked(['This code has already been redeemed, so its code, type, value, start date and "applies to" can no longer change. You can still change the end date and the usage limit, or disable it.']);
                }

                if ($new['usage_limit'] !== null && $new['usage_limit'] < $current->used_count) {
                    throw new PricingChangeBlocked(["This code has already been used {$current->used_count} times, so the usage limit cannot be lower than that."]);
                }

                if ($codeChanged && PromoCode::query()->where('code', $new['code'])->whereKeyNot($current->getKey())->exists()) {
                    throw $this->duplicate();
                }

                $affected = PromoCode::query()
                    ->whereKey($current->getKey())
                    ->where('version', $seenVersion)
                    ->update($new + ['version' => DB::raw('version + 1'), 'updated_at' => now()]);

                if ($affected !== 1) {
                    throw $this->conflict();
                }

                Audit::log('promo.updated', $current->refresh(), array_filter([
                    'promo_id' => $current->id,
                    'changes' => $changes ?: null,
                    'code_changed' => $codeChanged ?: null,
                ], fn ($v) => $v !== null), $actor);

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }

        return ['promo' => $promo->fresh(), 'changed' => $changed];
    }

    /**
     * Disable or enable a code. Doing what is already done changes and logs nothing.
     *
     * @return bool whether it changed
     */
    public function setDisabled(User $actor, PromoCode $promo, bool $disabled): bool
    {
        return DB::transaction(function () use ($actor, $promo, $disabled) {
            $current = PromoCode::query()->lockForUpdate()->findOrFail($promo->getKey());

            if ($current->disabled === $disabled) {
                return false;
            }

            $current->forceFill(['disabled' => $disabled, 'version' => $current->version + 1])->save();

            Audit::log($disabled ? 'promo.disabled' : 'promo.enabled', $current, ['promo_id' => $current->id], $actor);

            return true;
        });
    }

    /**
     * Delete a code that was never redeemed. The audit entry is written first, in the same transaction, so it still names the code.
     *
     * @throws PricingChangeBlocked
     */
    public function delete(User $actor, PromoCode $promo): void
    {
        DB::transaction(function () use ($actor, $promo) {
            $current = PromoCode::query()->lockForUpdate()->findOrFail($promo->getKey());

            if ($current->isRedeemed()) {
                throw new PricingChangeBlocked(['This code has been redeemed, so it cannot be deleted. Disable it instead.']);
            }

            Audit::log('promo.deleted', $current, ['promo_id' => $current->id], $actor);

            $current->delete();
        });
    }

    // ---- internals -------------------------------------------------------------------------------------------

    /** Capitals for the code, whole minutes for the dates (start of the first minute, end of the last). */
    private function normalise(array $data): array
    {
        $data['code'] = strtoupper(trim((string) ($data['code'] ?? '')));

        if (($data['valid_from'] ?? null) instanceof CarbonInterface) {
            $data['valid_from'] = Carbon::instance($data['valid_from'])->utc()->startOfMinute();
        }
        if (($data['valid_until'] ?? null) instanceof CarbonInterface) {
            $data['valid_until'] = Carbon::instance($data['valid_until'])->utc()->startOfMinute()->setSecond(59);
        }

        return $data;
    }

    /** The checks that do not depend on other codes. Also done here (not only in the form) so no caller can skip them. */
    private function assertShape(array $data): void
    {
        $errors = [];

        if (! preg_match('/^[A-Z0-9-]{4,32}$/', $data['code'])) {
            $errors[] = 'The code must be 4 to 32 characters: letters, numbers and hyphens only.';
        }

        $value = $data['value'] ?? null;
        $type = $data['type'] ?? null;

        if (! array_key_exists((string) $type, PromoCode::TYPES)) {
            $errors[] = 'Choose Percentage or Fixed amount.';
        } elseif (! is_int($value)) {
            $errors[] = 'The discount must be a number.';
        } elseif ($type === 'percent' && ($value < 1 || $value > 100)) {
            $errors[] = 'A percentage must be a whole number from 1 to 100.';
        } elseif ($type === 'fixed' && ($value < 1 || $value > (int) config('pricing.plan.max_price_cents'))) {
            $errors[] = 'A fixed discount must be more than RM 0.00 and not more than RM '.number_format((int) config('pricing.plan.max_price_cents') / 100, 2).'.';
        }

        $from = $data['valid_from'] ?? null;
        $until = $data['valid_until'] ?? null;
        if (! $from instanceof CarbonInterface || ! $until instanceof CarbonInterface) {
            $errors[] = 'Give both the start and the end of the period the code is valid.';
        } elseif (! $from->lt($until)) {
            $errors[] = 'The code must start before it ends.';
        }

        $limit = $data['usage_limit'] ?? null;
        if ($limit !== null && (! is_int($limit) || $limit < 1 || $limit > 10_000_000)) {
            $errors[] = 'The usage limit must be a whole number of at least 1, or empty for unlimited.';
        }

        if (! array_key_exists((string) ($data['applies_to'] ?? ''), PromoCode::APPLIES_TO)) {
            $errors[] = 'Choose who the code applies to.';
        }

        if ($errors !== []) {
            throw new PricingChangeBlocked($errors);
        }
    }

    private function sameMinute(CarbonInterface $a, CarbonInterface $b): bool
    {
        return $a->utc()->format('Y-m-d H:i') === $b->utc()->format('Y-m-d H:i');
    }

    private function duplicate(): PricingChangeBlocked
    {
        return new PricingChangeBlocked(['That code already exists. Codes are not case sensitive, so "welcome" and "WELCOME" are the same code.']);
    }

    private function conflict(): PricingChangeBlocked
    {
        return new PricingChangeBlocked(['Someone else changed this code while you were editing it. Nothing was saved. Reload the page to see the latest version, then make your change again.'], conflict: true);
    }
}
