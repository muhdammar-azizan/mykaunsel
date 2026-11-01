<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The receipt of a paid invoice (see App\Services\ReceiptService): a document of its own, with its own number, made when the invoice was paid.
 * Everything on it is a snapshot of that day.
 */
class Receipt extends Model
{
    protected $fillable = [
        'number', 'invoice_id', 'org_id', 'paid_at', 'payment_method', 'payment_reference', 'description', 'period_starts_on', 'period_ends_on',
        'subtotal_cents', 'discount_cents', 'tax_name', 'tax_rate_bp', 'tax_cents', 'total_cents', 'currency', 'seller', 'buyer',
    ];

    /** Random public id the moment it is inserted (never a counter in an address). */
    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'seller' => 'array',
            'buyer' => 'array',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_rate_bp' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
