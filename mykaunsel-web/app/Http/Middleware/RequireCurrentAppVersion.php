<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Old app versions cannot be updated by us, so the app says which version it is (X-App-Version: 1.2.3) and, once an
 * API change would break an older one, config('api.min_app_version') is raised and that app is told to update (426).
 * A request without the header (browsers, curl, tests) is not blocked. `status` always answers, so the app can read
 * the message and the store link.
 */
class RequireCurrentAppVersion
{
    public static function isTooOld(?string $version): bool
    {
        $min = (string) config('api.min_app_version', '0.0.0');

        if ($version === null || ! preg_match('/^\d+\.\d+\.\d+/', $version)) {
            return false;
        }

        return version_compare($version, $min, '<');
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() === 'api.v1.status' || ! self::isTooOld($request->header('X-App-Version'))) {
            return $next($request);
        }

        return response()->json([
            'message' => config('api.update_message'),
            'code' => 'app_update_required',
            'min_version' => config('api.min_app_version'),
            'store_url' => config('api.store_urls.'.($request->header('X-App-Platform') === 'ios' ? 'ios' : 'android')),
        ], 426);
    }
}
