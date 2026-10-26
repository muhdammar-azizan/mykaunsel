<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A blocked attempt to register a KB number that already belongs to an account.
 * Shown only on the ORIGINAL account's page in Platform Admin; the person trying is never told who owns the number.
 */
class CounselorKbAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['counselor_profile_id', 'kb_number', 'submitted_name', 'ip'];

    public function counselorProfile(): BelongsTo
    {
        return $this->belongsTo(CounselorProfile::class);
    }
}
