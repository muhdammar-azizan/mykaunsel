<?php

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMembershipStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()->memberships()
            ->where('org_id', $request->session()->get('current_org_id'))
            ->first();

        if (! $membership || $membership->status !== MembershipStatus::Active) {
            return redirect()->route('membership.blocked');
        }

        return $next($request);
    }
}
