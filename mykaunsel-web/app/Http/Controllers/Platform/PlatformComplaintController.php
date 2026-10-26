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
        $filters = ComplaintListFilters::fromRequest($request);
        $tab = $filters->tab();

        $complaints = ComplaintListFilters::order(
            $filters->applyFilters(Complaint::query())->withStatus(ComplaintStatus::from($tab)),
            $tab,
        )
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

        // How it was closed, from the audit log (the outcome text is what the admin wrote, never the complaint).
        $closing = $complaint->status->isClosed()
            ? AuditLog::with('actor')->where('target_type', 'complaint')->where('target_id', $complaint->id)->where('action', 'complaint.status_changed')->latest('id')->first()
            : null;

        return view('platform.complaints.show', [
            'closing' => $closing ? [
                'by' => $closing->isSystem() ? 'System' : ($closing->actor_name ?? $closing->actor?->name ?? 'Unknown'),
                'at' => $closing->created_at,
                'outcome' => $closing->metadata['reason'] ?? null,
            ] : null,
            'upcoming' => $profile?->upcomingSessionsCount() ?? 0,
            'pendingRequests' => $profile?->pendingRequestsCount() ?? 0,
            'complaint' => $complaint,
            'profile' => $profile,
            'booking' => $complaint->booking,
            // A count only: how many other complaints this counselor has.
            'otherComplaints' => Complaint::query()->where('counselor_user_id', $complaint->counselor_user_id)->where('id', '!=', $complaint->id)->count(),
            'notes' => $complaint->reviewNotes->sortByDesc(fn ($n) => [$n->created_at, $n->id])->values(),
            'timeline' => $timeline->for($complaint),
            'timezone' => $request->user()->display_timezone,
        ]);
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
