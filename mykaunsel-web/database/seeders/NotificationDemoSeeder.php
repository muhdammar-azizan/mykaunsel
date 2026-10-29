<?php

namespace Database\Seeders;

use App\Enums\AccessModel;
use App\Enums\ComplaintStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\LkmReviewRequest;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use Database\Seeders\Concerns\RefusesOutsideDevelopment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEMO data for the Platform Admin notification center (the bell): for every active Platform Admin a mix of read and unread items across all
 * the groups (applications, LKM, complaints, admins, system), one item whose target is already reviewed, one repeated duplicate-KB item with a
 * count, and enough items to see the page's pagination. The targets are small made-up records ("[Demo] Notifications …", "Demo …",
 * @example.test emails); nobody can sign in as them. Local and testing only. Safe to run twice (it does nothing the second time).
 */
class NotificationDemoSeeder extends Seeder
{
    use RefusesOutsideDevelopment;

    public function run(): void
    {
        $this->refuseOutsideDevelopment();

        if (Organization::where('name', 'like', '[Demo] Notifications %')->exists()) {
            return;
        }

        $admins = app(NotificationCenter::class)->activeAdminIds();

        if ($admins === []) {
            $this->command?->warn('There is no active Platform Admin, so there is nobody to give demo notifications to.');

            return;
        }

        $pendingOrg = $this->organization('[Demo] Notifications Pending Org', OrgStatus::Pending);
        $approvedOrg = $this->organization('[Demo] Notifications Approved Org', OrgStatus::Active);
        $pendingCounselor = $this->counselor('Demo Notifications Pending', VerificationStatus::Pending);
        $approvedCounselor = $this->counselor('Demo Notifications Approved', VerificationStatus::Approved);
        $suspendedCounselor = $this->counselor('Demo Notifications Suspended', VerificationStatus::Suspended);
        $waitingLkm = $this->lkmRequest($pendingCounselor, LkmReviewRequest::PENDING);
        $decidedLkm = $this->lkmRequest($approvedCounselor, LkmReviewRequest::APPROVED);
        $openComplaint = $this->complaint($approvedCounselor, ComplaintStatus::Open);
        $closedComplaint = $this->complaint($approvedCounselor, ComplaintStatus::Resolved);

        foreach ($admins as $admin) {
            $other = User::find($admins[0] === $admin && isset($admins[1]) ? $admins[1] : $admins[0]);

            // [type, target, label, minutes ago, read?, count]
            $items = [
                ['organization.application_submitted', $pendingOrg, $pendingOrg->name, 12, false, 1],
                ['counselor.application_submitted', $pendingCounselor, 'Demo Notifications Pending', 95, false, 1],
                ['counselor.kb_duplicate_flagged', $approvedCounselor, 'Demo Notifications Approved', 180, false, 3],          // repeated: "3 attempts"
                ['lkm.review_requested', $waitingLkm, 'Demo Notifications Pending', 60 * 5, false, 1],
                ['complaint.received', $openComplaint, '#'.$openComplaint->id, 60 * 7, false, 1],
                ['organization.application_submitted', $approvedOrg, $approvedOrg->name, 60 * 26, true, 1],                       // already reviewed (approved)
                ['lkm.review_requested', $decidedLkm, 'Demo Notifications Approved', 60 * 30, true, 1],                           // already reviewed (decided)
                ['complaint.escalated', $closedComplaint, '#'.$closedComplaint->id, 60 * 50, true, 1],                             // already reviewed (resolved)
                ['counselor.pa_expired_suspended', $suspendedCounselor, 'Demo Notifications Suspended', 60 * 24 * 3, true, 1],
                ['admin.invitation_accepted', $other, $other->name, 60 * 24 * 5, true, 1],
                ['system.maintenance_auto_reopened', null, null, 60 * 24 * 9, true, 1],
            ];

            // Enough older ones to need a second page (25 per page).
            foreach (range(1, 24) as $i) {
                $items[] = [$i % 3 === 0 ? 'counselor.application_resubmitted' : 'organization.application_submitted', $i % 3 === 0 ? $approvedCounselor : $approvedOrg, $i % 3 === 0 ? 'Demo Notifications Approved' : $approvedOrg->name, 60 * 24 * (10 + $i), $i % 4 !== 0, 1];
            }

            foreach ($items as $n => [$type, $target, $label, $minutesAgo, $read, $count]) {
                UserNotification::create([
                    'recipient_user_id' => $admin,
                    'type' => $type,
                    'target_type' => $target === null ? null : match (true) {
                        $target instanceof Organization => 'organization',
                        $target instanceof CounselorProfile => 'counselor_profile',
                        $target instanceof LkmReviewRequest => 'lkm_review_request',
                        $target instanceof Complaint => 'complaint',
                        default => 'user',
                    },
                    'target_id' => $target?->getKey(),
                    'label' => $label,
                    'occurrence_key' => "demo:{$n}",
                    'count' => $count,
                    'occurred_at' => now()->subMinutes($minutesAgo),
                    'read_at' => $read ? now()->subMinutes(max(1, $minutesAgo - 30)) : null,
                ]);
            }
        }
    }

    private function organization(string $name, OrgStatus $status): Organization
    {
        $organization = Organization::create(['name' => $name, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => $status]);
        $organization->forceFill(['submitted_at' => now()->subDays(2), 'reviewed_at' => $status === OrgStatus::Active ? now()->subDay() : null])->saveQuietly();

        return $organization;
    }

    private function counselor(string $name, VerificationStatus $status): CounselorProfile
    {
        static $n = 0;
        $n++;

        $user = User::create(['name' => $name, 'email' => 'demo.notifications.'.$n.'.'.Str::lower(Str::random(6)).'@example.test', 'password' => Hash::make(Str::random(40))]);

        return CounselorProfile::create([
            'user_id' => $user->id, 'kb_number' => 'KBDN'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'pa_number' => 'PADN'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'pa_valid_until' => now()->addYear(), 'verification_type' => VerificationType::PlatformVerified, 'verification_status' => $status,
        ]);
    }

    private function lkmRequest(CounselorProfile $profile, string $status): LkmReviewRequest
    {
        return LkmReviewRequest::create([
            'counselor_profile_id' => $profile->id, 'status' => $status, 'pending_for' => $status === LkmReviewRequest::PENDING ? $profile->id : null,
            'current' => ['pa_valid_until' => now()->subMonth()->toDateString()], 'claimed' => ['pa_valid_until' => now()->addYear()->toDateString()],
            'evidence_path' => 'demo/none.pdf', 'evidence_name' => 'demo.pdf', 'evidence_size' => 1,
        ]);
    }

    private function complaint(CounselorProfile $profile, ComplaintStatus $status): Complaint
    {
        $reporter = User::create(['name' => 'Demo Notifications Reporter', 'email' => 'demo.notifications.reporter.'.Str::lower(Str::random(6)).'@example.test', 'password' => Hash::make(Str::random(40))]);

        return Complaint::create(['reporter_user_id' => $reporter->id, 'counselor_user_id' => $profile->user_id, 'description' => '[Demo] A made-up complaint for the demo data.', 'status' => $status]);
    }
}
