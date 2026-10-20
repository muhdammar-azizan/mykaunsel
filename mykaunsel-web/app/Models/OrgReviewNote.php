<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Internal note between Platform Admins about an application. Never shown to the applicant. */
class OrgReviewNote extends Model
{
    protected $fillable = ['org_id', 'user_id', 'body'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
