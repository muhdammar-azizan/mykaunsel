<?php

namespace App\Http\Middleware;

use App\Enums\OrgStatus;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrgStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = Organization::find($request->session()->get('current_org_id'));

        if (! $organization) {
            return redirect()->route('context.select');
        }

        if ($organization->subscription_status === OrgStatus::Pending) {
            return redirect()->route('organization.pending');
        }

        if ($organization->subscription_status === OrgStatus::Suspended) {
            return redirect()->route('organization.suspended');
        }

        return $next($request);
    }
}
