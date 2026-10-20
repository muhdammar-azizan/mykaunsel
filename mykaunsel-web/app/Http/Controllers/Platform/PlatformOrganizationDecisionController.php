<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrgType;
use App\Exceptions\InvalidOrgTransition;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrganizationDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformOrganizationDecisionController extends Controller
{
    public function approve(Request $request, Organization $organization, OrganizationDecisionService $decisions): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        try {
            $decisions->approve($organization, $request->user());
        } catch (InvalidOrgTransition) {
            return $this->alreadyDecided($organization);
        }

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', "{$organization->name} approved. The admin has been emailed.");
    }

    public function reject(Request $request, Organization $organization, OrganizationDecisionService $decisions): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write a reason — it is sent to the applicant.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $decisions->reject($organization, $request->user(), trim($validated['reason']));
        } catch (InvalidOrgTransition) {
            return $this->alreadyDecided($organization);
        }

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', "{$organization->name} rejected. The admin has been emailed the reason.");
    }

    /** Another admin got there first (or the page was stale): show what actually happened. */
    private function alreadyDecided(Organization $organization): RedirectResponse
    {
        $organization->refresh()->load('reviewer');

        $who = $organization->reviewer?->name ?? 'another admin';

        return redirect()->route('platform.organizations.show', $organization)
            ->with('error', "This application is already {$organization->subscription_status->value} (by {$who}). Nothing was changed.");
    }
}
