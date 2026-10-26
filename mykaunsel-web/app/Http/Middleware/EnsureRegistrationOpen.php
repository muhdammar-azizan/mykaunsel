<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use App\Services\WaitlistService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops new sign-ups for one kind of account while a Platform Admin has closed them (Registration Control).
 * Only the first step is guarded: someone who already started signing up can finish.
 */
class EnsureRegistrationOpen
{
    public function __construct(private PlatformSettings $settings, private WaitlistService $waitlist) {}

    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($this->settings->registrationOpen($role)) {
            return $next($request);
        }

        $message = $this->settings->registrationClosedMessage($role);

        // A closed page is temporary: search engines must not keep it, and it must never be cached (it carries the waitlist
        // form with its CSRF token: a cached copy would have an old token).
        $headers = ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'no-store, private'];

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403, $headers);
        }

        // Only Organization and Counselor have a waitlist (individuals sign up in the app).
        return response()->view('registration-closed', ['message' => $message, 'waitlistTypes' => $this->waitlist->offered($role) ? [$role] : []], 403, $headers);
    }
}
