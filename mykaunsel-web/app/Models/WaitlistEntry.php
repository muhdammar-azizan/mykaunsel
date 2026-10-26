<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One address waiting to hear that a kind of sign-up has reopened (see App\Services\WaitlistService). */
class WaitlistEntry extends Model
{
    public const WAITING = 'waiting';

    public const NOTIFIED = 'notified';

    protected $fillable = ['type', 'email', 'email_hash', 'status', 'notified_at', 'emailed_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::WAITING);
    }

    /** Plain-text search on the address ("!" is the LIKE escape character; a backslash means different things to SQLite and MySQL). */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereRaw("email like ? escape '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%']);
    }
}
