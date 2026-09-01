<?php

namespace App\Models;

use App\Enums\CalendarEntryType;
use App\Enums\SessionMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_user_id',
        'entry_type',
        'context_org_id',
        'title',
        'entry_date',
        'start_time',
        'duration_minutes',
        'session_mode',
        'location_id',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => CalendarEntryType::class,
            'entry_date' => 'date',
            'duration_minutes' => 'integer',
            'session_mode' => SessionMode::class,
            'is_available' => 'boolean',
        ];
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

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'calendar_entry_id');
    }

    public function isSlot(): bool
    {
        return $this->entry_type === CalendarEntryType::AvailableSlot;
    }

    public function isBlock(): bool
    {
        return $this->entry_type === CalendarEntryType::PersonalBlock;
    }
}
