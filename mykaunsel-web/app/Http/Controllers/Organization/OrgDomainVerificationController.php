<?php

namespace App\Http\Controllers\Organization;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Services\DomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgDomainVerificationController extends Controller
{
    public function show(Request $request, Organization $organization, DomainVerificationService $domainService): View
    {
        $this->ensureOrgAdmin($request, $organization);

        foreach ($organization->orgDomains as $domain) {
            if (! $domain->verification_token) {
                $domainService->generateToken($domain);
            }
        }

        return view('organizations.signup.verify-domain', [
            'organization' => $organization,
            'domains' => $organization->orgDomains()->get(),
        ]);
    }

    public function check(Request $request, Organization $organization, OrgDomain $domain, DomainVerificationService $domainService): JsonResponse
    {
        $this->ensureOrgAdmin($request, $organization);
        abort_unless($domain->org_id === $organization->id, 404);

        $verified = $domainService->checkDns($domain);

        return response()->json([
            'verified' => $verified,
            'checked_at' => $domain->fresh()->last_checked_at,
        ]);
    }

    public function continue(Request $request, Organization $organization): RedirectResponse
    {
        $this->ensureOrgAdmin($request, $organization);

        return redirect()->route('organizations.signup.waiting-approval', $organization);
    }

    private function ensureOrgAdmin(Request $request, Organization $organization): void
    {
        $isAdmin = $request->user()
            ->memberships()
            ->where('org_id', $organization->id)
            ->where('role', MembershipRole::OrgAdmin)
            ->exists();

        abort_unless($isAdmin, 403);
    }
}
