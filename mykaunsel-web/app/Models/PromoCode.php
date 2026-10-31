<?php

namespace App\Models;

use App\Enums\OrgType;
use App\Enums\PromoCodeStatus;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A promo code. Only a Platform Admin applies it, when assigning a plan (there is no public place to type a code).
 * There is no payment: a code only lowers the price that is RECORDED for an organization.
 *
 * The code is stored in capitals. The status (Scheduled, Active, Expired, Used up, Disabled) is worked out from the data and
 * never stored: status() is the only place that does it.
 */
class PromoCode extends Model
{
    public const TYPES = ['percent' => 'Percentage', 'fixed' => 'Fixed amount'];

    public const APPLIES_TO = ['all' => 'All organizations', 'university' => 'University only', 'corporate' => 'Corporate only', 'clinic' => 'Clinic only'];

    protected $fillable = ['code', 'type', 'value', 'valid_from', 'valid_until', 'usage_limit', 'used_count', 'applies_to', 'disabled', 'version', 'created_by'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'disabled' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubscriptionAssignment::class, 'promo_code_id');
    }

    /**
     * Order of the checks: Disabled, Used up, Expired, Scheduled, Active. A code is valid from the START of its first minute
     * to the END of its last minute (valid_until is stored with :59 seconds).
     */
    public function status(?CarbonInterface $at = null): PromoCodeStatus
    {
        $at ??= now();

        return match (true) {
            $this->disabled => PromoCodeStatus::Disabled,
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => PromoCodeStatus::UsedUp,
            $at->gt($this->valid_until) => PromoCodeStatus::Expired,
            $at->lt($this->valid_from) => PromoCodeStatus::Scheduled,
            default => PromoCodeStatus::Active,
        };
    }

    /** Has it been used, or is it linked to an assignment? Then its code, type, value, start and "applies to" are locked and it cannot be deleted. */
    public function isRedeemed(): bool
    {
        // `assignments_exists` is there when the list loaded the codes with withExists('assignments'); otherwise ask the database.
        return $this->used_count > 0 || (bool) ($this->assignments_exists ?? $this->assignments()->exists());
    }

    /** Can this code be used for an organization of this type (ignoring dates and the limit)? */
    public function appliesToType(OrgType $type): bool
    {
        return $this->applies_to === 'all' || $this->applies_to === $type->value;
    }

    /** "20%" or "RM 500.00" */
    public function discountLabel(): string
    {
        return $this->type === 'percent' ? $this->value.'%' : Money::rm($this->value);
    }

    /** "34 / 100" or "34 / Unlimited" */
    public function usageLabel(): string
    {
        return number_format($this->used_count).' / '.($this->usage_limit === null ? 'Unlimited' : number_format($this->usage_limit));
    }

    public function appliesToLabel(): string
    {
        return self::APPLIES_TO[$this->applies_to] ?? $this->applies_to;
    }

    /** Audit entries name the code by the code itself (it is not personal data). */
    public function auditName(): string
    {
        return $this->code;
    }
}
