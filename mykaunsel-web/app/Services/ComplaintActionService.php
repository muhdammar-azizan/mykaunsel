<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidComplaintTransition;
use App\Exceptions\InvalidCounselorTransition;
use App\Facades\Audit;
use App\Models\Complaint;
use App\Models\ComplaintWarning;
use App\Models\CounselorProfile;
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
 * The audit entries hold the kind of action, the old and new status and ids. Not what the complaint said, and not what the
 * admin wrote either: that is kept with the complaint (closing reason, warning messages) or on the counselor's profile.
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

            // What the admin wrote as the outcome lives on the complaint (and goes with the retention rule), NOT in the audit
            // log: the audit entry holds only the old and the new status.
            if ($to->isClosed()) {
                $complaint->forceFill(['closing_reason' => $reason, 'closed_at' => now()])->save();
            }

            Audit::log('complaint.status_changed', $complaint, ['changes' => ['status' => ['old' => $before->value, 'new' => $to->value]]], $admin);
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

            // A second warning on the same complaint right after the first is a double click or a second admin acting on the same
            // page: refused (checked here, with the complaint row locked, so two requests cannot both pass).
            $hours = (int) config('complaints.warning_cooldown_hours');
            $recent = $locked->warnings()->where('created_at', '>=', now()->subHours($hours))->latest('id')->first();

            if ($recent !== null) {
                throw new CounselorDecisionBlocked(["A warning was already sent for this complaint {$recent->created_at->diffForHumans()}. Wait {$hours} hours before sending another."]);
            }

            // The message is what the admin wrote to the counselor (not the complaint). It is kept with the complaint, in
            // complaint_warnings (so it goes with the retention rule), and is NOT copied into the audit log: the entry only
            // says that a warning was issued, and which one.
            $warning = ComplaintWarning::create(['complaint_id' => $locked->id, 'user_id' => $admin->id, 'message' => $message]);

            Audit::log('complaint.warning_issued', $locked, ['warning_id' => $warning->id], $admin);
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
        DB::transaction(function () use ($complaint, $admin, $reason, $cancelUpcoming) {
            // Everything is decided INSIDE the transaction, with both rows locked, always in the same order (the complaint
            // first, then the counselor's profile) so two admins can never wait on each other. A complaint another admin has
            // just closed, or a counselor who has just been suspended, stops this before anything changes or is emailed.
            $locked = $complaint->newQuery()->lockForUpdate()->findOrFail($complaint->id);

            if ($locked->status->isClosed()) {
                throw new CounselorDecisionBlocked(['This complaint is already closed. Suspend the counselor from their own page if that is still needed.']);
            }

            $profile = CounselorProfile::query()->where('user_id', $locked->counselor_user_id)->lockForUpdate()->first();

            if ($profile === null || $profile->verification_status !== VerificationStatus::Approved) {
                throw new CounselorDecisionBlocked(['This counselor is not currently approved, so there is nothing to suspend.']);
            }

            $this->counselors->suspend($profile, $admin, $reason, $cancelUpcoming, 'complaint');

            // The reason the admin wrote is on the counselor's profile (suspension_reason), not repeated here.
            Audit::log('complaint.counselor_suspended', $locked, [
                'counselor_profile_id' => $profile->id,
                'sessions' => $cancelUpcoming ? 'cancel_upcoming' : 'keep',
            ], $admin);
        });
    }
}
