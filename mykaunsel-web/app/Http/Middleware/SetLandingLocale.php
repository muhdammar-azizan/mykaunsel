<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of the public website: Malay by default, English when the visitor picks it (?lang=en). The choice is kept in a cookie. It only applies to
 * the public website pages, never to the rest of the system.
 */
class SetLandingLocale
{
    public const COOKIE = 'mk_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $locales = (array) config('landing.locales');
        $picked = $request->query('lang');
        $fromCookie = $request->cookie(self::COOKIE);

        $locale = match (true) {
            is_string($picked) && in_array($picked, $locales, true) => $picked,
            is_string($fromCookie) && in_array($fromCookie, $locales, true) => $fromCookie,
            default => (string) config('landing.default_locale'),
        };

        app()->setLocale($locale);

        /** @var Response $response */
        $response = $next($request);

        if (is_string($picked) && $picked === $locale) {
            $response->headers->setCookie(cookie(self::COOKIE, $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax'));
        }

        return $response;
    }
}
