<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An invoice for a subscription (see InvoiceService). Everything on it is a snapshot of the day it was issued.
 */
class Invoice extends Model
{
    public const UNPAID = 'unpaid';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $fillable = [
        'number', 'org_id', 'assignment_id', 'status', 'issued_on', 'due_on', 'period_starts_on', 'period_ends_on', 'description',
        'subtotal_cents', 'discount_cents', 'tax_name', 'tax_rate_bp', 'tax_cents', 'total_cents', 'currency', 'seller', 'buyer',
        'paid_at', 'payment_method', 'payment_reference', 'paid_marked_by', 'voided_at', 'void_reason', 'voided_by', 'issued_by',
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
            'issued_on' => 'date',
            'due_on' => 'date',
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionAssignment::class, 'assignment_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::UNPAID;
    }

    /** Today as a Malaysian day: invoices are issued, due and late by the calendar of the people they are for. */
    public static function today(): Carbon
    {
        return Carbon::now((string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur'))->startOfDay();
    }

    /** Unpaid and past its due date (the day itself is not late). */
    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->status === self::UNPAID && $this->due_on->lt(($today ?? self::today())->startOfDay());
    }

    /** What is shown: Overdue is Unpaid that is late. */
    public function statusLabel(?Carbon $today = null): string
    {
        return match (true) {
            $this->status === self::PAID => 'Paid',
            $this->status === self::VOID => 'Void',
            $this->isOverdue($today) => 'Overdue',
            default => 'Unpaid',
        };
    }

    /** Invoices still waiting for payment. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::UNPAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::UNPAID)->where('due_on', '<', self::today()->toDateString());
    }
}
