<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMembershipStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()->memberships()
            ->where('org_id', $request->session()->get('current_org_id'))
            ->first();

        if (! $membership || $membership->status !== MembershipStatus::Active) {
            // Platform Admin access that has ended (removed or suspended) is not a "blocked" page: the person is signed out at
            // once. This is what ends a session that removing the admin could not delete (sessions kept outside the database).
            if ($request->session()->get('current_role') === MembershipRole::PlatformAdmin->value) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('status', 'You have been signed out because your Platform Admin access has ended.');
            }

            return redirect()->route('membership.blocked');
        }

        return $next($request);
    }
}
