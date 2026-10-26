<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidComplaintTransition;
use App\Exceptions\InvalidCounselorTransition;
use App\Facades\Audit;
use App\Models\Complaint;
use App\Models\User;
use App\Notifications\CounselorComplaintWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What a Platform Admin can do about a complaint: move it along, warn the counselor, suspend the counselor.
 *
 * Same rules as everywhere else: the row is locked and its status re-checked (two admins cannot both act on the same
 * page), each change and its audit entry share one transaction, and emails go out only after the commit.
 * The audit entries hold the complaint's id and what the ADMIN wrote — never what the complaint said.
 */
class ComplaintActionService
{
    public function __construct(private CounselorStatusService $counselors) {}

    /**
     * Start the review, or close the complaint as resolved / dismissed. Resolving and dismissing need a written outcome.
     *
     * @param  ?ComplaintStatus  $seen  the status on the page the admin was looking at (refuses a stale page)
     *
     * @throws InvalidComplaintTransition
     */
    public function changeStatus(Complaint $complaint, ComplaintStatus $to, User $admin, ?string $reason = null, ?ComplaintStatus $seen = null): void
    {
        DB::transaction(function () use ($complaint, $to, $admin, $reason, $seen) {
            $before = $complaint->newQuery()->lockForUpdate()->findOrFail($complaint->id)->status;

            $complaint->transitionTo($to, $seen);

            Audit::log('complaint.status_changed', $complaint, array_filter([
                'reason' => $reason,
                'changes' => ['status' => ['old' => $before->value, 'new' => $to->value]],
            ], fn ($v) => $v !== null), $admin);
        });
    }

    /**
     * Warn the counselor. Does not change the complaint's status. Only while the complaint is still being looked at.
     *
     * @throws CounselorDecisionBlocked
     */
    public function warnCounselor(Complaint $complaint, User $admin, string $message): void
    {
        DB::transaction(function () use ($complaint, $admin, $message) {
            $locked = $complaint->newQuery()->lockForUpdate()->findOrFail($complaint->id);

            if ($locked->status->isClosed()) {
                throw new CounselorDecisionBlocked(['This complaint is already '.$locked->status->value.', so a warning can no longer be sent from it.']);
            }

            // The message is what the admin wrote to the counselor (not the complaint), kept so it can be looked up later.
            Audit::log('complaint.warning_issued', $locked, ['reason' => $message, 'summary' => 'Warning emailed to the counselor'], $admin);
        });

        DB::afterCommit(function () use ($complaint, $message) {
            try {
                $complaint->counselor->notify(new CounselorComplaintWarning($message));
            } catch (Throwable $e) {
                Log::warning('Could not email the counselor a complaint warning.', ['complaint_id' => $complaint->id, 'error' => $e->getMessage()]);
            }
        });
    }

    /**
     * Suspend the counselor the complaint is about (source "complaint"). The counselor suspension and the complaint's own
     * audit entry are written together; if the counselor is no longer approved nothing at all is changed.
     *
     * @throws InvalidCounselorTransition
     * @throws CounselorDecisionBlocked
     */
    public function suspendCounselor(Complaint $complaint, User $admin, string $reason, bool $cancelUpcoming): void
    {
        $profile = $complaint->counselorProfile;

        if ($complaint->fresh()->status->isClosed()) {
            throw new CounselorDecisionBlocked(['This complaint is already closed. Suspend the counselor from their own page if that is still needed.']);
        }

        if ($profile === null || $profile->verification_status !== VerificationStatus::Approved) {
            throw new CounselorDecisionBlocked(['This counselor is not currently approved, so there is nothing to suspend.']);
        }

        DB::transaction(function () use ($complaint, $profile, $admin, $reason, $cancelUpcoming) {
            $this->counselors->suspend($profile, $admin, $reason, $cancelUpcoming, 'complaint');

            Audit::log('complaint.counselor_suspended', $complaint, [
                'reason' => $reason,
                'counselor_profile_id' => $profile->id,
                'sessions' => $cancelUpcoming ? 'cancel_upcoming' : 'keep',
            ], $admin);
        });
    }
}
