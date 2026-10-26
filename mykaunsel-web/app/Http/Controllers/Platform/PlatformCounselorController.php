<?php

namespace App\Http\Controllers\Platform;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\ComplaintStatus;
use App\Enums\MembershipRole;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Organization;
use App\Models\User;
use App\Services\CounselorReviewService;
use App\Services\CounselorTimeline;
use App\Services\LkmVerificationService;
use App\Support\CounselorListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Platform Admin → Counselors: review independent counselors and look after active ones.
 * Counts only: nothing here ever shows a client's name or what happened in a session.
 */
class PlatformCounselorController extends Controller
{
    private const OPEN_COMPLAINTS = [ComplaintStatus::Open, ComplaintStatus::UnderReview];

    private const PAST_COMPLAINTS = [ComplaintStatus::Resolved, ComplaintStatus::Dismissed];

    public function index(Request $request, LkmVerificationService $lkm): View|RedirectResponse
    {
        $filters = CounselorListFilters::fromRequest($request);
        $tab = $filters->tab();

        $query = CounselorListFilters::order(
            $filters->applyFilters(CounselorListFilters::forTab(CounselorProfile::query(), $tab)),
            $tab,
        )
            ->with(['user', 'documents', 'reviewer', 'suspender', 'memberships.organization'])
            ->withCount([
                'kbAttempts',
                'complaints as open_complaints_count' => fn ($q) => $q->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_COMPLAINTS)),
            ]);

        $profiles = $query->paginate(CounselorListFilters::PER_PAGE)->withQueryString();

        // A page number past the end (e.g. after narrowing the filters): go to the last real page.
        if ($profiles->isEmpty() && $profiles->currentPage() > 1) {
            return redirect()->route('platform.counselors', $filters->toQuery(extra: ['page' => $profiles->lastPage()]));
        }

        return view('platform.counselors.index', [
            'filters' => $filters,
            'tab' => $tab,
            'tabs' => CounselorListFilters::TABS,
            'counts' => $filters->counts(),
            'profiles' => $profiles,
            'lkm' => $tab === 'pending' ? $lkm->compareMany($profiles->getCollection()) : collect(),
            'organizations' => Organization::query()
                ->where('org_type', '!=', OrgType::Platform->value)
                ->whereHas('memberships', fn ($m) => $m->where('role', MembershipRole::Counselor->value))
                ->orderBy('name')->pluck('name', 'id'),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function show(Request $request, CounselorProfile $counselor, LkmVerificationService $lkm, CounselorTimeline $timeline, CounselorReviewService $review): View
    {
        $counselor->load([
            'user', 'documents', 'languages', 'specializations', 'reviewer', 'suspender',
            'memberships.organization',
            'kbAttempts' => fn ($q) => $q->latest('created_at')->latest('id'),
        ]);

        $today = Carbon::now(config('app.timezone'))->toDateString();

        // Counts only. Never a client's name, never anything about what was said or done in a session.
        $stats = [
            'total_sessions' => $counselor->bookings()->where('status', '!=', BookingStatus::Cancelled->value)->count(),
            'upcoming_sessions' => $counselor->bookings()
                ->where('status', BookingStatus::Confirmed->value)
                ->whereHas('calendarEntry', fn ($q) => $q->whereDate('entry_date', '>=', $today))
                ->count(),
            'open_complaints' => $counselor->complaints()->whereIn('status', array_map(fn ($s) => $s->value, self::OPEN_COMPLAINTS))->count(),
            'pending_requests' => BookingRequest::where('counselor_user_id', $counselor->user_id)->where('status', BookingRequestStatus::Pending->value)->count(),
            'past_complaints' => $counselor->complaints()->whereIn('status', array_map(fn ($s) => $s->value, self::PAST_COMPLAINTS))->count(),
        ];

        $independent = $counselor->isIndependent();
        $pending = $counselor->verification_status === VerificationStatus::Pending;

        // Review checklist boxes, with who ticked them.
        $saved = $counselor->review_checklist ?? [];
        $names = User::whereIn('id', collect($saved)->pluck('by')->filter())->pluck('name', 'id');
        $checklist = collect(CounselorProfile::REVIEW_CHECKLIST)->map(fn ($item, $key) => [
            'key' => $key,
            'label' => $item[0],
            'hint' => $item[1],
            'checked' => isset($saved[$key]),
            'by' => isset($saved[$key]) ? ($names[$saved[$key]['by']] ?? 'Unknown') : null,
            'at' => isset($saved[$key]) ? Carbon::parse($saved[$key]['at'])->diffForHumans() : null,
        ])->values();

        return view('platform.counselors.show', [
            'devTools' => app()->environment(['local', 'testing']),
            'lkmRecordId' => LkmDirectorySnapshot::where('kb_number', $counselor->kb_number)->value('id'),
            // A request to re-check their LKM record that is waiting for a decision (see LKM Directory → Review Requests).
            'pendingLkmReview' => $counselor->lkmReviewRequests()->pending()->first(),
            'readiness' => $independent && $pending ? $review->readiness($counselor) : null,
            'checklist' => $checklist,
            'notes' => $independent ? $counselor->reviewNotes()->with('author')->latest()->latest('id')->get() : collect(),
            'profile' => $counselor,
            'lkm' => $counselor->isIndependent() ? $lkm->compare($counselor) : null,
            'documents' => $counselor->documents->keyBy('type'),
            'stats' => $stats,
            'timeline' => $timeline->for($counselor),
            'listable' => $counselor->isListable(),
            'timezone' => $request->user()->display_timezone,
        ]);
    }
}
