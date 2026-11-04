<?php

namespace App\Http\Middleware;

use App\Enums\VerificationStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended counselor "cannot open new slots" (that is what the suspension e-mail promises). Removing times and everything else in the planner stays open.
 */
class EnsureCounselorCanOpenTimes
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->counselorProfile;

        if ($profile !== null && $profile->verification_status === VerificationStatus::Suspended) {
            return back()->withErrors(['planner' => 'Your profile is suspended, so you can not open new times. Sessions that are already booked are not changed.']);
        }

        return $next($request);
    }
}
