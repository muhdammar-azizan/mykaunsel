<?php

namespace App\Models;

use App\Enums\Attendance;
use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'attendance',
        'follow_up_status',
    ];

    protected function casts(): array
    {
        return [
            'attendance' => Attendance::class,
            'follow_up_status' => FollowUpStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
