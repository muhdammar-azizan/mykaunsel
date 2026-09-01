<?php

namespace App\Models;

use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_user_id',
        'counselor_user_id',
        'booking_id',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_user_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
