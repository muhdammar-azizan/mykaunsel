<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assignment of a plan to an organization, with the prices AS THEY WERE at that moment (snapshots): editing the plan or
 * the promo code later never changes them. The organization points to its current one (organizations.current_assignment_id);
 * older ones stay as history.
 */
class SubscriptionAssignment extends Model
{
    protected $fillable = [
        'org_id', 'plan_id', 'promo_code_id', 'plan_price_cents', 'discount_cents', 'final_price_cents', 'starts_on', 'ends_on', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'plan_price_cents' => 'integer',
            'discount_cents' => 'integer',
            'final_price_cents' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class, 'assignment_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
