<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'content',
        'client_id',
        'edited_at',
        'deleted_at',
        'removed_at',
    ];

    /** Every row gets its random public id the moment it is inserted (never a counter, never mass-assignable). */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        $this->public_id ??= strtolower((string) \Illuminate\Support\Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** Posts people can see: not taken down by the author and not removed by a moderator. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('feed_posts.deleted_at')->whereNull('feed_posts.removed_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function postReactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }
}
