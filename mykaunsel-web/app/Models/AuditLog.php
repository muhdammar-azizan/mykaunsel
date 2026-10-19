<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_user_id', 'action', 'target_type', 'target_id', 'metadata', 'ip'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Record an admin/security action. Never pass secrets (passwords, codes, tokens) in $metadata.
     */
    public static function record(string $action, ?Model $target = null, array $metadata = [], ?User $actor = null): self
    {
        $actor ??= auth()->user();

        return static::create([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'metadata' => $metadata ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
