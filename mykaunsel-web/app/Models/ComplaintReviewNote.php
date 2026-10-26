<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Internal note between Platform Admins about a complaint. Never shown to the reporter or the counselor. */
class ComplaintReviewNote extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'body'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
