<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One booking request remembered by its Idempotency-Key (see BookingIdempotency). Never holds a token or headers. */
class BookingIdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
