<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Someone who opted out of a waitlist: only a keyed hash of the address is kept (never the address). */
class WaitlistSuppression extends Model
{
    public $timestamps = false;

    protected $fillable = ['type', 'email_hash', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
