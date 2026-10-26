<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\User;
use App\Services\PlatformSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While maintenance mode is on, nothing works for anyone except a signed-in Platform Admin. The sign-in pages stay
 * open so an admin can always get in (they are the only way to switch maintenance off again).
 *
 * The way in must work from a signed-out browser all the way to the Platform Admin area, step by step:
 *   sign-in page and its submit (and the two-factor step) -> /dashboard -> role chooser -> Platform Admin area.
 * Every step is let through here (see config/platform-settings.php). If this ever breaks there is a way out that needs no
 * browser: `php artisan platform:reopen`.
 *
 * The blocked answer is a 503 that search engines are told not to index, with Retry-After set to the time left until the
 * reopen time (or a default when there is none). The message is the admin's plain text, shown escaped.
 */
class BlockDuringMaintenance
{
    public function __construct(private PlatformSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->maintenanceOn() || $this->isAllowed($request)) {
            return $next($request);
        }

        $message = $this->settings->message('maintenance');
        $reopensAt = $this->settings->maintenanceReopenAt();
        $headers = ['Retry-After' => $this->settings->retryAfterSeconds(), 'X-Robots-Tag' => 'noindex, nofollow'];

        // A time that has already passed is not a promise to show anyone.
        $expected = $reopensAt !== null && $reopensAt->gt(now()) ? $reopensAt : null;

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'reopens_at' => $expected?->toIso8601ZuluString()], 503, $headers);
        }

        return response()->view('maintenance', [
            'message' => $message,
            'reopensAt' => $expected?->setTimezone(config('platform-settings.public_timezone')),
        ], 503, $headers);
    }

    private function isAllowed(Request $request): bool
    {
        $name = $request->route()?->getName();

        // Unknown URLs fall through to the normal 404 page.
        if ($request->route() === null || in_array($name, config('platform-settings.maintenance_allowed_routes'), true)) {
            return true;
        }

        // The API has no session: only the web has Platform Admins working in a role.
        if (! $request->hasSession() || $request->user() === null) {
            return false;
        }

        if ($request->session()->get('current_role') === 'platform_admin') {
            return true;
        }

        // Just signed in (no role chosen yet), or working in another role: a Platform Admin may pass through the dashboard
        // hop to the role chooser. The role chooser itself only offers roles the person really has.
        return in_array($name, config('platform-settings.maintenance_admin_entry_routes'), true) && $this->holdsPlatformAdminRole($request->user());
    }

    private function holdsPlatformAdminRole(User $user): bool
    {
        return $user->memberships()
            ->where('role', MembershipRole::PlatformAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->exists();
    }
}
