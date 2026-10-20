<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Helpers for the database session driver (used by every "sign out my other devices" flow). */
class SessionManager
{
    public function otherSessions(Request $request)
    {
        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId());
    }

    public function countOthers(Request $request): int
    {
        return config('session.driver') === 'database' ? $this->otherSessions($request)->count() : 0;
    }

    /** Remove stored session rows right away so lists are correct without waiting for those devices to make a request. */
    public function forgetOthers(Request $request): void
    {
        if (config('session.driver') === 'database') {
            $this->otherSessions($request)->delete();
        }
    }
}
