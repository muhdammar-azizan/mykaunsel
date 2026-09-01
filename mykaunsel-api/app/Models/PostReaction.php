<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'feed_post_id',
        'user_id',
    ];

    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
