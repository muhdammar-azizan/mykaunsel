<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person hiding another person's posts and comments from their own feed. */
class FeedMute extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'muted_user_id'];

    /** Every row gets its random public id the moment it is inserted (never a counter, never mass-assignable). */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    public function muted(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }
}
