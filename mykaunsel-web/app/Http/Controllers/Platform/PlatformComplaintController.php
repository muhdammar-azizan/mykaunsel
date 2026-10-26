<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ComplaintStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\ComplaintReviewNote;
use App\Models\Organization;
use App\Services\ComplaintTimeline;
use App\Support\ComplaintListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform Admin → Complaints. Only Platform Admins read a complaint's text. The list never shows it, and nothing
 * here copies it anywhere (the audit log only ever holds the complaint's id).
 */
class PlatformComplaintController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = ComplaintListFilters::fromRequest($request, $request->user()->display_timezone);
        $tab = $filters->tab();

        $complaints = $filters->order($filters->applyTab($filters->applyFilters(Complaint::query())))
            // The text (description) is not needed on the list, so it is not even loaded.
            ->select(['complaints.id', 'complaints.reporter_user_id', 'complaints.counselor_user_id', 'complaints.booking_id', 'complaints.status', 'complaints.created_at', 'complaints.updated_at'])
            ->with(['reporter:id,name', 'counselor:id,name', 'counselorProfile:id,user_id,kb_number', 'booking:id,org_id', 'booking.organization:id,name,org_type'])
            ->paginate(ComplaintListFilters::PER_PAGE)
            ->withQueryString();

        if ($complaints->isEmpty() && $complaints->currentPage() > 1) {
            return redirect()->route('platform.complaints', $filters->toQuery(extra: ['page' => $complaints->lastPage()]));
        }

        $organizationIds = Booking::query()->whereIn('id', Complaint::query()->whereNotNull('booking_id')->select('booking_id'))->distinct()->pluck('org_id');

        return view('platform.complaints.index', [
            'filters' => $filters,
            'tab' => $tab,
            'tabs' => ComplaintListFilters::TABS,
            'sorts' => ComplaintListFilters::SORTS,
            'counts' => $filters->counts(),
            'complaints' => $complaints,
            'organizations' => Organization::query()->whereIn('id', $organizationIds)->orderBy('name')->get()
                ->mapWithKeys(fn ($o) => [$o->id => $o->org_type === OrgType::Platform ? 'Independent practice' : $o->name]),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function show(Request $request, Complaint $complaint, ComplaintTimeline $timeline): View
    {
        $complaint->load(['reporter:id,name', 'counselor:id,name', 'counselorProfile', 'booking.calendarEntry', 'booking.organization', 'reviewNotes.author']);

        $profile = $complaint->counselorProfile;

        // How it was closed: who and when from the audit log, and the outcome the admin wrote from the complaint itself (it is not in
        // the audit log). Once the retention rule has run the outcome is gone.
        $closing = $complaint->status->isClosed()
            ? AuditLog::with('actor')->where('target_type', 'complaint')->where('target_id', $complaint->id)->where('action', 'complaint.status_changed')->latest('id')->first()
            : null;

        return view('platform.complaints.show', [
            'closing' => $closing ? [
                'by' => $closing->isSystem() ? 'System' : ($closing->actor_name ?? $closing->actor?->name ?? 'Unknown'),
                'at' => $complaint->closed_at ?? $closing->created_at,
                'outcome' => $complaint->closing_reason,
            ] : null,
            'upcoming' => $profile?->upcomingSessionsCount() ?? 0,
            'pendingRequests' => $profile?->pendingRequestsCount() ?? 0,
            'complaint' => $complaint,
            'history' => $this->history($complaint),
            'profile' => $profile,
            'booking' => $complaint->booking,
            // A count only: how many other complaints this counselor has.
            'otherComplaints' => Complaint::query()->where('counselor_user_id', $complaint->counselor_user_id)->where('id', '!=', $complaint->id)->count(),
            'notes' => $complaint->reviewNotes->sortByDesc(fn ($n) => [$n->created_at, $n->id])->values(),
            'timeline' => $timeline->for($complaint),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    /**
     * Everything the platform has on this counselor, WITHOUT any text: how many complaints, from how many different people, how
     * each ended, and whether there are several in a short time. Information for the admin only; nothing happens automatically.
     *
     * @return array{total: int, reporters: int, by_status: array<string, int>, recent: int, window_days: int, threshold: int, pattern: bool, others: \Illuminate\Support\Collection}
     */
    private function history(Complaint $complaint): array
    {
        $all = Complaint::query()->where('counselor_user_id', $complaint->counselor_user_id)->orderByDesc('created_at')->orderByDesc('id')
            ->get(['id', 'reporter_user_id', 'status', 'created_at']);

        $days = (int) config('complaints.pattern.days');
        $threshold = (int) config('complaints.pattern.count');
        $recent = $all->filter(fn ($c) => $c->created_at->gte(now()->subDays($days)))->count();

        return [
            'total' => $all->count(),
            // Different people: a complaint whose reporter has deleted their account still counts as one person.
            'reporters' => $all->whereNotNull('reporter_user_id')->pluck('reporter_user_id')->unique()->count() + $all->whereNull('reporter_user_id')->count(),
            'by_status' => collect(ComplaintStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $all->where('status', $s)->count()])->all(),
            'recent' => $recent,
            'window_days' => $days,
            'threshold' => $threshold,
            'pattern' => $recent >= $threshold,
            'others' => $all->where('id', '!=', $complaint->id)->values(),
        ];
    }

    /** Internal note between admins. Its text is not copied into the audit log, only its id. */
    public function storeNote(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validateWithBag('note', [
            'body' => ['required', 'string', 'max:2000'],
        ], ['body.required' => 'Write something before adding the note.']);

        DB::transaction(function () use ($complaint, $request, $data) {
            $note = ComplaintReviewNote::create([
                'complaint_id' => $complaint->id,
                'user_id' => $request->user()->id,
                'body' => trim($data['body']),
            ]);

            Audit::log('complaint.note_added', $complaint, ['note_id' => $note->id]);
        });

        return redirect()->to(route('platform.complaints.show', $complaint).'#notes')->with('status', 'Note added.');
    }
}
