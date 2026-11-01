<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An organization's invitation to a counselor's e-mail address (see App\Services\OrgCounselors). No link or token: the counselor signs in to the
 * portal with that address (confirmed) and answers on the Dashboard.
 */
class OrgCounselorInvitation extends Model
{
    /** Days an invitation can be answered. */
    public const VALID_DAYS = 30;

    protected $fillable = ['org_id', 'email', 'invited_by_user_id', 'expires_at', 'accepted_at', 'declined_at', 'revoked_at'];

    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime', 'declined_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function isOpen(?Carbon $at = null): bool
    {
        return $this->accepted_at === null && $this->declined_at === null && $this->revoked_at === null && $this->expires_at->isAfter($at ?? now());
    }

    public function state(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'Accepted',
            $this->declined_at !== null => 'Declined',
            $this->revoked_at !== null => 'Cancelled',
            $this->expires_at->isPast() => 'Expired',
            default => 'Waiting',
        };
    }
}
