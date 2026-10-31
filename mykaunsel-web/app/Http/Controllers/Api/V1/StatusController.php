<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireCurrentAppVersion;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the app should know before it shows anything: is MyKaunsel in maintenance, and which sign-ups are open.
 * Public (no sign-in), and the only API route that still answers during maintenance.
 */
class StatusController extends Controller
{
    public function __invoke(Request $request, PlatformSettings $settings): JsonResponse
    {
        $maintenance = $settings->maintenanceOn();

        return response()->json([
            'api_version' => 'v1',
            // The app sends X-App-Version; if it is older than the minimum it must show an update screen.
            'app' => [
                'min_version' => config('api.min_app_version'),
                'update_required' => RequireCurrentAppVersion::isTooOld($request->header('X-App-Version')),
                'message' => config('api.update_message'),
                'store_url' => config('api.store_urls.'.($request->header('X-App-Platform') === 'ios' ? 'ios' : 'android')),
            ],
            'maintenance' => [
                'on' => $maintenance,
                'message' => $maintenance ? $settings->message('maintenance') : null,
                // When it is expected to end (ISO-8601, UTC), or null if no time was set or the time has already passed.
                'reopens_at' => ($at = $settings->maintenanceReopenAt()) !== null && $at->gt(now()) ? $at->toIso8601ZuluString() : null,
            ],
            'support_email' => filled(config('api.support_email')) ? (string) config('api.support_email') : null,
            'registration' => collect($settings->registrationStatus())->map(fn (bool $open, string $role) => [
                'open' => $open,
                'message' => $open ? null : $settings->registrationClosedMessage($role),
            ])->all(),
        ]);
    }
}
