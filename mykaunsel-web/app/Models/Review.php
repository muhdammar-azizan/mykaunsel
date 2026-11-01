<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A client's review of one session (see App\Services\ReviewService). `client_user_id` is never shown to the counselor or the organization. */
class Review extends Model
{
    public const PUBLISHED = 'published';

    public const HELD = 'held';

    public const REMOVED = 'removed';

    protected $fillable = [
        'booking_id', 'counselor_user_id', 'client_user_id', 'rating', 'tags', 'comment', 'attendance_confirmed', 'status',
        'reply', 'replied_at', 'flagged_at', 'flag_reason', 'flag_status',
    ];

    /** Who wrote it and the words are never written to a log or a JSON answer by accident. */
    protected $hidden = ['client_user_id', 'comment', 'reply'];

    protected function performInsert(Builder $query)
    {
        $this->public_id ??= strtolower((string) Str::ulid());

        return parent::performInsert($query);
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'comment' => 'encrypted',
            'reply' => 'encrypted',
            'rating' => 'integer',
            'attendance_confirmed' => 'boolean',
            'replied_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Reviews that count: everything the MyKaunsel team has not removed. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('status', '!=', self::REMOVED);
    }
}
