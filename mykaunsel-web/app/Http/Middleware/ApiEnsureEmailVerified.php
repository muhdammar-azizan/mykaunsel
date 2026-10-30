<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's version of Laravel's "verified" middleware: the same rule (an unconfirmed email address cannot use the signed-in routes) but the
 * answer carries the machine-readable code the app already understands (`email_not_verified`), the same one sign-in gives.
 */
class ApiEnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'hasVerifiedEmail') && ! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is not verified.', 'code' => 'email_not_verified'], 403);
        }

        return $next($request);
    }
}
