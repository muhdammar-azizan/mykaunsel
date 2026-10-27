<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A promo code. Only a Platform Admin applies it, when assigning a plan (there is no public place to type a code).
 * The status (Scheduled, Active, Expired, Used up, Disabled) is worked out from the data and never stored.
 * The rules for it come in the next step of the Pricing page.
 */
class PromoCode extends Model
{
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

    public function auditName(): string
    {
        return $this->code;
    }
}
