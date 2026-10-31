<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedComment extends Model
{
    protected $fillable = ['feed_post_id', 'user_id', 'content', 'client_id', 'deleted_at', 'removed_at'];

    /** Every row gets its random public id the moment it is inserted (never a counter, never mass-assignable). */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('feed_comments.deleted_at')->whereNull('feed_comments.removed_at');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
