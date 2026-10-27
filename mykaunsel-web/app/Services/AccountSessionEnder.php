<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ends every way a person could still be signed in: their sessions in the database (when sessions are kept there),
 * their remember-me cookie (a new random token makes the old cookies worthless) and their API tokens.
 * Call it inside the same transaction as the change that needs it.
 */
class AccountSessionEnder
{
    public function endAll(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        $user->tokens()->delete();
    }
}
