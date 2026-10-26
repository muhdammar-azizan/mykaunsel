<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops new sign-ups for one kind of account while a Platform Admin has closed them (Registration Control).
 * Only the first step is guarded: someone who already started signing up can finish.
 */
class EnsureRegistrationOpen
{
    public function __construct(private PlatformSettings $settings) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($this->settings->registrationOpen($role)) {
            return $next($request);
        }

        $message = $this->settings->registrationClosedMessage($role);

        // A closed page is temporary: search engines must not keep it.
        $headers = ['X-Robots-Tag' => 'noindex, nofollow'];

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403, $headers);
        }

        return response()->view('registration-closed', ['message' => $message], 403, $headers);
    }
}
