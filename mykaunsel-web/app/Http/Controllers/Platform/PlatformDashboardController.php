<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Support\CounselorAttention;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PlatformDashboardController extends Controller
{
    public function index(Request $request)
    {
        $realOrgs = Organization::where('org_type', '!=', OrgType::Platform->value);

        $pendingOrgs = (clone $realOrgs)->where('subscription_status', OrgStatus::Pending->value);
        // Independent counselors waiting for approval — the same numbers as the Counselors page (organization counselors need no approval).
        $pendingCounselors = CounselorAttention::pending();

        $counts = [
            'pending_orgs' => (clone $pendingOrgs)->count(),
            'pending_counselors' => (clone $pendingCounselors)->count(),
            'active_complaints' => Complaint::whereIn('status', [
                ComplaintStatus::Open->value,
                ComplaintStatus::UnderReview->value,
            ])->count(),
            'skipped_dns' => (clone $pendingOrgs)->whereHas(
                'orgDomains',
                fn ($q) => $q->where('dns_verified', false)
            )->count(),
        ];

        $stats = [
            'organizations' => (clone $realOrgs)->where('subscription_status', OrgStatus::Active->value)->count(),
            'counselors' => CounselorProfile::where('verification_status', VerificationStatus::Approved->value)->count(),
            'members' => Membership::where('status', MembershipStatus::Active->value)
                ->whereIn('role', [
                    MembershipRole::Student->value,
                    MembershipRole::Staff->value,
                    MembershipRole::Employee->value,
                ])->count(),
            'bookings' => Booking::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        // Pending applications whose LKM record no longer backs them up (missing, inactive, expired PA, name or PA number differ).
        $lkmUnverifiedIds = CounselorAttention::lkmNotVerified()->pluck('counselor_profiles.id')->all();

        $attention = collect()
            ->merge(
                (clone $pendingOrgs)->oldest()->limit(5)->get()->map(fn ($org) => [
                    'kind' => 'organization',
                    'text' => $org->name.' — organization awaiting approval',
                    'days' => $org->waitingDays(),
                    'note' => null,
                    'url' => route('platform.organizations'),
                ])
            )
            ->merge(
                (clone $pendingCounselors)->with('user')->oldest()->limit(5)->get()->map(fn ($profile) => [
                    'kind' => 'counselor',
                    'text' => ($profile->user->name ?? 'Unknown').(in_array($profile->id, $lkmUnverifiedIds, true)
                        ? ' — LKM number could not be verified'
                        : ' — credentials awaiting verification'),
                    'days' => $profile->waitingDays(),
                    'note' => null,
                    'url' => route('platform.counselors.show', $profile),
                ])
            )
            ->sortByDesc('days')
            ->take(6)
            ->values();

        // Someone tried to register a KB number that already has an account (any status). Always shown, however old the other items are.
        $duplicateAlerts = CounselorAttention::duplicateKb()->with('user')->withMax('kbAttempts', 'created_at')->orderByDesc('kb_attempts_max_created_at')->limit(3)->get()->map(function ($profile) {
            $last = Carbon::parse($profile->kb_attempts_max_created_at);

            return [
                'kind' => 'counselor',
                'text' => ($profile->user->name ?? 'Unknown').' — duplicate KB registration attempt',
                'days' => (int) $last->diffInDays(now()),
                'note' => 'Last attempt '.($last->isToday() ? 'today' : $last->diffForHumans()),
                'url' => route('platform.counselors.show', $profile),
            ];
        });

        $attention = $attention->merge($duplicateAlerts)->values();

        // One source of truth: approvals are read from the audit log (nothing is back-filled for approvals made before it existed).
        $recentApprovals = AuditLog::query()
            ->whereIn('action', ['organization.approved', 'counselor.approved'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (AuditLog $log) => [
                'name' => $log->target_name ?? ('#'.$log->target_id),
                'type' => $log->action === 'counselor.approved' ? 'Counselor' : 'Organization',
                'date' => $log->created_at->copy()->timezone($request->user()->display_timezone),
            ]);

        return view('platform.dashboard', compact('counts', 'stats', 'attention', 'recentApprovals'));
    }
}
