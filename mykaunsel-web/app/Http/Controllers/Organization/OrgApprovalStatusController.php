<?php

namespace App\Http\Controllers\Organization;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PracticeLocation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgApprovalStatusController extends Controller
{
    public function show(Request $request, Organization $organization): View
    {
        $isAdmin = $request->user()
            ->memberships()
            ->where('org_id', $organization->id)
            ->where('role', MembershipRole::OrgAdmin)
            ->exists();

        abort_unless($isAdmin, 403);

        $domains = $organization->orgDomains;
        $domainsVerified = $domains->isNotEmpty() && $domains->every(fn ($domain) => $domain->dns_verified);

        $hasLocation = PracticeLocation::where('owner_type', 'organization')
            ->where('owner_id', $organization->id)
            ->exists();

        return view('organizations.signup.waiting-approval', [
            'organization' => $organization,
            'status' => $organization->subscription_status,
            'domainsVerified' => $domainsVerified,
            'hasLocation' => $hasLocation,
        ]);
    }
}
