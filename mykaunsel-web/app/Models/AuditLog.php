<?php

namespace App\Models;

use App\Exceptions\AuditLogImmutable;
use App\Models\Builders\AuditLogBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * One append-only audit entry. Write ONLY through App\Services\AuditLogger (the Audit facade);
 * rows can never be updated or deleted through the model or the Eloquent query builder.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_user_id', 'actor_name', 'actor_role',
        'action', 'category',
        'target_type', 'target_id', 'target_name',
        'organization_id',
        'metadata', 'ip', 'user_agent', 'request_id', 'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function newEloquentBuilder($query): AuditLogBuilder
    {
        return new AuditLogBuilder($query);
    }

    // The three ways a model instance can write to an existing row, all blocked
    // (overriding these also stops saveQuietly(), which skips model events).

    protected function performUpdate(EloquentBuilder $query)
    {
        throw AuditLogImmutable::make('update');
    }

    protected function performDeleteOnModel()
    {
        throw AuditLogImmutable::make('delete');
    }

    public function delete()
    {
        throw AuditLogImmutable::make('delete');
    }

    /** Staff entries keep a copy of name + role as they were; system entries have neither. */
    public function isSystem(): bool
    {
        return $this->actor_type === 'system';
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
