<?php

namespace App\Services;

use App\Enums\OrgStatus;
use App\Exceptions\InvalidOrgTransition;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationApproved;
use App\Notifications\OrganizationRejected;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Approve / reject a pending organization application.
 * The status change is concurrency-safe (see Organization::transitionTo); the audit entry is written
 * right after, and the email last — a mail failure never undoes a decision.
 */
class OrganizationDecisionService
{
    /** @throws InvalidOrgTransition when someone else already decided */
    public function approve(Organization $organization, User $reviewer): void
    {
        $organization->transitionTo(OrgStatus::Active, [
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ], OrgStatus::Pending);

        AuditLog::record('organization_approved', $organization, ['name' => $organization->name], $reviewer);

        $this->notify($organization, new OrganizationApproved($organization));
    }

    /** @throws InvalidOrgTransition when someone else already decided */
    public function reject(Organization $organization, User $reviewer, string $reason): void
    {
        $organization->transitionTo(OrgStatus::Rejected, [
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ], OrgStatus::Pending);

        AuditLog::record('organization_rejected', $organization, ['name' => $organization->name, 'reason' => $reason], $reviewer);

        $this->notify($organization, new OrganizationRejected($organization, $reason));
    }

    private function notify(Organization $organization, object $notification): void
    {
        $registrant = $organization->registrant()->with('user')->first()?->user;

        if (! $registrant) {
            return;
        }

        try {
            $registrant->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Could not email the organization admin about a decision.', ['org_id' => $organization->id, 'error' => $e->getMessage()]);
        }
    }
}
