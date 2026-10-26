<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A warning a Platform Admin sent the counselor about a complaint. The message is what the ADMIN wrote (it never says who
 * complained or what they wrote); it is kept here, not in the audit log, and erased by the retention rule with the complaint's text.
 */
class ComplaintWarning extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'message'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
