<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the original Breeze account endpoints (/profile, PUT /password).
 *
 * Platform Admin accounts must manage themselves only through Platform Admin → Account, where
 * changes need the current password, are audited and sign out other devices. Anyone holding a
 * platform_admin membership is therefore blocked from the old write endpoints, whichever
 * context (role) they are currently browsing in.
 */
class LegacyAccountGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $isPlatformAdmin = $user && $user->memberships()
            ->where('role', MembershipRole::PlatformAdmin->value)
            ->exists();

        if (! $isPlatformAdmin) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return $request->session()->get('current_role') === MembershipRole::PlatformAdmin->value
                ? redirect()->route('platform.account')
                : $next($request);
        }

        abort(403, 'Platform Admin accounts are managed from Platform Admin → Account.');
    }
}
