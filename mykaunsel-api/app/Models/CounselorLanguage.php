<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselorLanguage extends Model
{
    use HasFactory;

    protected $fillable = [
        'counselor_profile_id',
        'language',
    ];

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }
}
