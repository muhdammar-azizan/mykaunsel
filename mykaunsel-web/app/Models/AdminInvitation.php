<?php

namespace App\Models;

use App\Enums\AdminInvitationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invitation to become a Platform Admin. See AdminInvitationService for the rules.
 * The plain token is never stored, only its hash.
 */
class AdminInvitation extends Model
{
    protected $fillable = [
        'name', 'email', 'token_hash', 'active_email', 'status', 'invited_by_user_id', 'expires_at', 'last_sent_at', 'sent_count',
        'accepted_at', 'accepted_user_id', 'revoked_at', 'superseded_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'status' => AdminInvitationStatus::class,
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    /** Still waiting and not past its time. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', AdminInvitationStatus::Pending->value)->where('expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->status === AdminInvitationStatus::Pending && $this->expires_at->isFuture();
    }

    /** What the admin sees: a pending invitation past its time is "expired". */
    public function displayStatus(): AdminInvitationStatus
    {
        if ($this->status === AdminInvitationStatus::Pending && ! $this->expires_at->isFuture()) {
            return AdminInvitationStatus::Expired;
        }

        return $this->status;
    }

    /** Audit entries name the invitation by number only: no name and no email. */
    public function auditName(): string
    {
        return "Admin invitation #{$this->id}";
    }
}
