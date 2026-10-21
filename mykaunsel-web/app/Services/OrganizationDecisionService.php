<?php

namespace App\Services;

use App\Enums\OrgStatus;
use App\Exceptions\InvalidOrgTransition;
use App\Facades\Audit;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationApproved;
use App\Notifications\OrganizationRejected;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Approve / reject / suspend / reactivate an organization.
 *
 * Each change and its audit entry are written in ONE transaction (both happen or neither does), the status
 * change is concurrency-safe (see Organization::transitionTo), and emails go out only after the commit —
 * a mail failure never undoes a decision.
 */
class OrganizationDecisionService
{
    /** @throws InvalidOrgTransition when someone else already decided */
    public function approve(Organization $organization, User $reviewer): void
    {
        DB::transaction(function () use ($organization, $reviewer) {
            $organization->transitionTo(OrgStatus::Active, [
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ], OrgStatus::Pending);

            Audit::log('organization.approved', $organization, $this->statusChange(OrgStatus::Pending, OrgStatus::Active), $reviewer);
        });

        DB::afterCommit(fn () => $this->notify($organization, new OrganizationApproved($organization)));
    }

    /** @throws InvalidOrgTransition when someone else already decided */
    public function reject(Organization $organization, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($organization, $reviewer, $reason) {
            $organization->transitionTo(OrgStatus::Rejected, [
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ], OrgStatus::Pending);

            Audit::log('organization.rejected', $organization, ['reason' => $reason] + $this->statusChange(OrgStatus::Pending, OrgStatus::Rejected), $reviewer);
        });

        DB::afterCommit(fn () => $this->notify($organization, new OrganizationRejected($organization, $reason)));
    }

    /**
     * Suspend an active organization. Everyone in it loses access immediately (see CheckOrgStatus); nothing is deleted.
     *
     * @throws InvalidOrgTransition when it is no longer active
     */
    public function suspend(Organization $organization, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($organization, $reviewer, $reason) {
            $organization->transitionTo(OrgStatus::Suspended, [
                'suspension_reason' => $reason,
                'suspended_at' => now(),
            ], OrgStatus::Active);

            Audit::log('organization.suspended', $organization, ['reason' => $reason] + $this->statusChange(OrgStatus::Active, OrgStatus::Suspended), $reviewer);
        });
    }

    /**
     * Bring a suspended organization back. Access returns right away; the old reason stays in the audit log.
     *
     * @throws InvalidOrgTransition when it is no longer suspended
     */
    public function reactivate(Organization $organization, User $reviewer): void
    {
        DB::transaction(function () use ($organization, $reviewer) {
            $organization->transitionTo(OrgStatus::Active, [
                'suspension_reason' => null,
                'suspended_at' => null,
            ], OrgStatus::Suspended);

            Audit::log('organization.reactivated', $organization, $this->statusChange(OrgStatus::Suspended, OrgStatus::Active), $reviewer);
        });
    }

    /** @return array{changes: array{status: array{old: string, new: string}}} */
    private function statusChange(OrgStatus $from, OrgStatus $to): array
    {
        return ['changes' => ['status' => ['old' => $from->value, 'new' => $to->value]]];
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
