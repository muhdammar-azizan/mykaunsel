<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One in-app notification for one recipient. Created and read only through App\Services\NotificationCenter.
 * It stores a short label and ids, never text from a complaint, a note, a reason or a document (see the migration).
 */
class UserNotification extends Model
{
    /** Random public id the moment it is inserted (never a counter, never mass-assignable): the mobile app only ever sees this. */
    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    protected $fillable = [
        'recipient_user_id', 'type', 'target_type', 'target_id', 'label', 'occurrence_key', 'count', 'occurred_at', 'read_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'read_at' => 'datetime', 'count' => 'integer', 'target_id' => 'integer'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** Only this person's notifications: every read and change goes through here, so nobody can reach another admin's. */
    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where('recipient_user_id', $user instanceof User ? $user->getKey() : $user);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
