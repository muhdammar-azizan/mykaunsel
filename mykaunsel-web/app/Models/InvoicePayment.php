<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One try to pay an invoice online (see App\Services\Payments\InvoicePayments). */
class InvoicePayment extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $fillable = ['invoice_id', 'provider', 'status', 'amount_cents', 'provider_reference', 'settled_at', 'started_by'];

    /** Random public id the moment it is inserted (never a counter in an address). */
    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return ['settled_at' => 'datetime', 'amount_cents' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isExpired(): bool
    {
        return $this->status === self::PENDING && $this->created_at->lt(now()->subMinutes((int) config('payments.attempt_minutes')));
    }

    /** What is shown: a pending try that is too old is Expired. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::SUCCEEDED => 'Paid',
            $this->status === self::FAILED => 'Failed',
            $this->isExpired() => 'Expired',
            default => 'Waiting',
        };
    }
}
