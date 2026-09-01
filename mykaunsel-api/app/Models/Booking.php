<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_id',
        'user_id',
        'counselor_user_id',
        'calendar_entry_id',
        'booking_mode',
        'status',
        'session_mode',
        'location_id',
        'meeting_provider',
        'meeting_url',
        'meeting_space_id',
        'reschedule_count',
    ];

    protected function casts(): array
    {
        return [
            'booking_mode' => BookingMode::class,
            'status' => BookingStatus::class,
            'session_mode' => SessionMode::class,
            'reschedule_count' => 'integer',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function calendarEntry(): BelongsTo
    {
        return $this->belongsTo(CalendarEntry::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(PracticeLocation::class, 'location_id');
    }

    public function sessionNote(): HasOne
    {
        return $this->hasOne(SessionNote::class);
    }
}
