<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselorWorkingHour extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_profile_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }
}
