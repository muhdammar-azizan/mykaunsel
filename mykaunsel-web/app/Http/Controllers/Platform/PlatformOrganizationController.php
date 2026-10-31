<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\DomainVerificationService;
use App\Services\OrganizationTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PlatformOrganizationController extends Controller
{
    private const TABS = ['pending', 'skipped', 'approved', 'rejected'];

    private const SORTS = ['oldest', 'newest', 'name'];

    private const PER_PAGE = 10;

    public function index(Request $request)
    {
        // Unknown values fall back to the defaults instead of erroring on a hand-edited URL.
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'pending';
        $type = in_array($request->query('type'), ['university', 'corporate', 'clinic'], true) ? $request->query('type') : null;
        $search = $this->cleanSearch($request->query('q'));
        $isWaitingTab = in_array($tab, ['pending', 'skipped'], true);
        $sort = in_array($request->query('sort'), self::SORTS, true)
            ? $request->query('sort')
            : ($isWaitingTab ? 'oldest' : 'newest');

        // Search + type filter apply to every tab, so the tab counts match what each tab would show.
        $filtered = fn (): Builder => Organization::query()
            ->applicants()
            ->when($type, fn ($q) => $q->where('org_type', $type))
            ->when($search !== '', fn ($q) => $this->search($q, $search));

        $counts = [
            'pending' => $filtered()->awaitingReview()->count(),
            'skipped' => $filtered()->skippedVerification()->count(),
            'approved' => $filtered()->whereIn('subscription_status', $this->approvedStatuses())->count(),
            'rejected' => $filtered()->where('subscription_status', OrgStatus::Rejected->value)->count(),
        ];

        $query = match ($tab) {
            'pending' => $filtered()->awaitingReview()
                ->with(['orgDomains', 'registrant.user']),
            'skipped' => $filtered()->skippedVerification()
                ->with(['orgDomains', 'registrant.user']),
            'approved' => $filtered()->whereIn('subscription_status', $this->approvedStatuses())
                ->withCount(['memberships as members_count' => fn ($q) => $q
                    ->where('status', MembershipStatus::Active->value)
                    ->whereIn('role', [MembershipRole::Student->value, MembershipRole::Staff->value, MembershipRole::Employee->value]),
                ]),
            'rejected' => $filtered()->where('subscription_status', OrgStatus::Rejected->value)
                ->with('reviewer'),
        };

        // Waiting tabs sort by when the application came in; the others by when it was decided.
        $dateColumn = $isWaitingTab ? 'COALESCE(submitted_at, created_at)' : 'COALESCE(reviewed_at, created_at)';

        match ($sort) {
            'name' => $query->orderBy('name'),
            'newest' => $query->orderByRaw("$dateColumn desc"),
            default => $query->orderByRaw("$dateColumn asc"),
        };

        $organizations = $query->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('platform.organizations.index', [
            'tab' => $tab,
            'counts' => $counts,
            'organizations' => $organizations,
            'search' => $search,
            'type' => $type,
            'sort' => $sort,
            'types' => collect(OrgType::cases())->reject(fn (OrgType $t) => $t === OrgType::Platform),
            'filtering' => $search !== '' || $type !== null,
        ]);
    }

    public function show(Organization $organization, DomainVerificationService $domains, OrganizationTimeline $timeline, \App\Services\SubscriptionAssignmentService $plans)
    {
        abort_if($organization->org_type === OrgType::Platform, 404);

        $organization->load(['orgDomains', 'registrant.user', 'primaryLocation.locationPhotos', 'reviewer']);

        $registrant = $organization->registrant?->user;

        // Does the registering admin's email belong to a domain the organization claims?
        $emailMatch = null;
        if ($registrant && $organization->orgDomains->isNotEmpty()) {
            $emailMatch = $organization->orgDomains->first(fn ($d) => $domains->matchesEmail($registrant->email, $d));
        }

        // Checklist boxes, with who ticked them.
        $saved = $organization->review_checklist ?? [];
        $names = User::whereIn('id', collect($saved)->pluck('by')->filter())->pluck('name', 'id');
        $checklist = collect(Organization::REVIEW_CHECKLIST)->map(fn ($item, $key) => [
            'key' => $key,
            'label' => $item[0],
            'hint' => $item[1],
            'checked' => isset($saved[$key]),
            'by' => isset($saved[$key]) ? ($names[$saved[$key]['by']] ?? 'Unknown') : null,
            'at' => isset($saved[$key]) ? Carbon::parse($saved[$key]['at'])->diffForHumans() : null,
        ])->values();

        return view('platform.organizations.show', [
            'overview' => in_array($organization->subscription_status, [OrgStatus::Active, OrgStatus::Suspended], true)
                ? $this->overview($organization)
                : null,
            'dns' => $organization->orgDomains->mapWithKeys(fn ($d) => [$d->id => [
                'state' => $domains->state($d),
                'window_ends' => $domains->windowEndsAt($d),
            ]]),
            'checklist' => $checklist,
            'notes' => $organization->reviewNotes()->with('author')->latest()->latest('id')->get(),
            'timeline' => $timeline->for($organization),
            'organization' => $organization,
            'offer' => $plans->offeredPlans($organization),
            'planHistory' => $plans->history($organization),
            'invoices' => \App\Models\Invoice::query()->where('org_id', $organization->getKey())->orderByDesc('id')->limit(5)->get(),
            'memberCount' => $plans->memberCount($organization),
            'registrant' => $registrant,
            'registrantTitle' => $organization->registrant?->title,
            'emailMatch' => $emailMatch,
        ]);
    }

    /** Numbers for the "managing an approved organization" cards. */
    private function overview(Organization $organization): array
    {
        $active = fn ($q) => $q->where('org_id', $organization->id)->where('status', MembershipStatus::Active->value);

        $complaints = Complaint::whereHas('booking', fn ($q) => $q->where('org_id', $organization->id));

        return [
            'members' => Membership::where($active)
                ->whereIn('role', [MembershipRole::Student->value, MembershipRole::Staff->value, MembershipRole::Employee->value])->count(),
            'counselors' => Membership::where($active)->where('role', MembershipRole::Counselor->value)->count(),
            'sessions_this_month' => Booking::where('org_id', $organization->id)->where('created_at', '>=', now()->startOfMonth())->count(),
            'approved_at' => $organization->reviewed_at ?? $organization->created_at,
            'complaints_total' => (clone $complaints)->count(),
            'complaints_open' => (clone $complaints)->whereIn('status', [ComplaintStatus::Open->value, ComplaintStatus::UnderReview->value])->count(),
        ];
    }

    /** @return array<int, string> */
    private function approvedStatuses(): array
    {
        return [OrgStatus::Active->value, OrgStatus::Suspended->value];
    }

    /** Trim, cap the length and drop LIKE wildcards so users can't craft expensive patterns. */
    private function cleanSearch(mixed $value): string
    {
        $value = is_string($value) ? $value : '';

        return trim(mb_substr(preg_replace('/[%_\\\\]/', '', $value), 0, 100));
    }

    /** Match the organization name or the registering admin's email. */
    private function search(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhereHas('memberships', fn ($m) => $m
                    ->where('role', MembershipRole::OrgAdmin->value)
                    ->whereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%")));
        });
    }
}
