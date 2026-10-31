<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An organization's invitation to one e-mail address. There is no link or token: the invited person signs in to the app with that address
 * (confirmed), sees the invitation under Account > Your organization, and accepts or declines. Accepting makes them an employee at once.
 */
class OrgMemberInvitation extends Model
{
    /** Days an invitation can be answered. */
    public const VALID_DAYS = 30;

    protected $fillable = ['org_id', 'email', 'invited_by_user_id', 'expires_at', 'accepted_at', 'declined_at', 'revoked_at'];

    /** Random public id the moment it is inserted (never a counter, never mass-assignable). */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
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

    /** What is stored and compared: no capitals, no spaces around it. */
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
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
