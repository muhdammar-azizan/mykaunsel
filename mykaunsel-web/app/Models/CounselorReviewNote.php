<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Internal note between Platform Admins about a counselor application. Never shown to the applicant. */
class CounselorReviewNote extends Model
{
    protected $fillable = ['counselor_profile_id', 'user_id', 'body'];

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
