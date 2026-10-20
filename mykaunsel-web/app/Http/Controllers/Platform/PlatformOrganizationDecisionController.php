<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrgType;
use App\Exceptions\InvalidOrgTransition;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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

    public function suspend(Request $request, Organization $organization, OrganizationDecisionService $decisions): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $validated = $request->validateWithBag('suspend', [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write the reason for suspending — it is kept in the audit log.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $decisions->suspend($organization, $request->user(), trim($validated['reason']));
        } catch (InvalidOrgTransition) {
            return $this->alreadyDecided($organization);
        }

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', "{$organization->name} suspended. Its members and counselors can no longer sign in to it.");
    }

    public function reactivate(Request $request, Organization $organization, OrganizationDecisionService $decisions): RedirectResponse
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        try {
            $decisions->reactivate($organization, $request->user());
        } catch (InvalidOrgTransition) {
            return $this->alreadyDecided($organization);
        }

        return redirect()->route('platform.organizations.show', $organization)
            ->with('status', "{$organization->name} reactivated. Access is restored.");
    }

    /** Another admin got there first (or the page was stale): show what actually happened. */
    private function alreadyDecided(Organization $organization): RedirectResponse
    {
        $organization->refresh()->load('reviewer');

        // Whoever made the most recent status change (approve / reject / suspend / reactivate).
        $last = AuditLog::with('actor')
            ->where('target_type', Organization::class)
            ->where('target_id', $organization->id)
            ->whereIn('action', ['organization_approved', 'organization_rejected', 'organization_suspended', 'organization_reactivated'])
            ->latest('id')
            ->first();

        $who = $last?->actor?->name ?? $organization->reviewer?->name ?? 'another admin';

        return redirect()->route('platform.organizations.show', $organization)
            ->with('error', "This organization is already {$organization->subscription_status->value} (by {$who}). Nothing was changed.");
    }
}
