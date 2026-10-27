<?php

namespace App\Models;

use App\Enums\OrgType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A yearly subscription plan for universities or corporates (see SubscriptionPlanService). Never deleted, only made inactive.
 * There is no payment: the price is a record.
 */
class SubscriptionPlan extends Model
{
    protected $fillable = ['org_type', 'name', 'min_members', 'max_members', 'price_cents', 'active', 'display_order', 'version'];

    protected function casts(): array
    {
        return [
            'org_type' => OrgType::class,
            'min_members' => 'integer',
            'max_members' => 'integer',
            'price_cents' => 'integer',
            'active' => 'boolean',
            'display_order' => 'integer',
            'version' => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubscriptionAssignment::class, 'plan_id');
    }

    /** "1–500", or "5,000+" when there is no upper limit. */
    public function rangeLabel(): string
    {
        return self::range($this->min_members, $this->max_members);
    }

    public static function range(int $min, ?int $max): string
    {
        return $max === null ? number_format($min).'+' : number_format($min).'–'.number_format($max);
    }

    /** "RM 1,200.00" */
    public function priceLabel(): string
    {
        return Money::rm($this->price_cents);
    }

    /** Audit entries name the plan by its name (it is not personal data). */
    public function auditName(): string
    {
        return $this->name;
    }
}
