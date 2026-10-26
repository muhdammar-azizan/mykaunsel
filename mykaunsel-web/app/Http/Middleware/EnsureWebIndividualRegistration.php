<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Individuals sign up in the mobile app, not on the web. While `registration.web_individual_enabled` is false the web
 * sign-up routes do not exist as far as anyone can tell: 404, for signed-in and signed-out visitors alike, and never a
 * redirect to somewhere else. (When it is true, the Individuals switch in Registration Control still applies: see
 * EnsureRegistrationOpen.)
 */
class EnsureWebIndividualRegistration
{
    public function __construct(private PlatformSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->webIndividualEnabled(), 404);

        return $next($request);
    }
}
