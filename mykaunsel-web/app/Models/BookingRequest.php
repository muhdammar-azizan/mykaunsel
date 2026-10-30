<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'counselor_user_id',
        'context_org_id',
        'requested_date',
        'requested_time',
        'duration_minutes',
        'session_mode',
        'location_id',
        'message',
        'response_reason',
        'status',
        'expires_at',
        'responded_at',
        'booking_id',
    ];

    /** Every request gets its random public id the moment it is inserted (never a counter, never mass-assignable). */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'status' => BookingRequestStatus::class,
            'session_mode' => \App\Enums\SessionMode::class,
            'duration_minutes' => 'integer',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_user_id');
    }

    public function contextOrg(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'context_org_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(PracticeLocation::class, 'location_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
