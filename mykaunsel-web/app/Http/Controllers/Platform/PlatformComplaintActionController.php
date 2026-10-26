<?php

namespace App\Http\Controllers\Platform;

use App\Enums\ComplaintStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidComplaintTransition;
use App\Exceptions\InvalidCounselorTransition;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Services\ComplaintActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform Admin → Complaints: moving a complaint along, warning the counselor, suspending the counselor. */
class PlatformComplaintActionController extends Controller
{
    /** Start the review (no reason needed), resolve or dismiss (a written outcome is required). */
    public function status(Request $request, Complaint $complaint, ComplaintActionService $actions): RedirectResponse
    {
        $to = ComplaintStatus::tryFrom((string) $request->input('to'));
        abort_unless($to !== null && $to !== ComplaintStatus::Open, 422, 'Unknown status.');

        $needsReason = $to->isClosed();

        $data = $request->validateWithBag($to->value, [
            'from' => ['nullable', Rule::in(array_map(fn ($s) => $s->value, ComplaintStatus::cases()))],
            'reason' => [$needsReason ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write the outcome — it is kept in the audit log. Do not paste the complaint text.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $actions->changeStatus(
                $complaint,
                $to,
                $request->user(),
                filled($data['reason'] ?? null) ? trim($data['reason']) : null,
                isset($data['from']) ? ComplaintStatus::from($data['from']) : null,
            );
        } catch (InvalidComplaintTransition) {
            return $this->alreadyChanged($complaint);
        }

        return $this->back($complaint)->with('status', match ($to) {
            ComplaintStatus::UnderReview => 'Complaint is now under review.',
            ComplaintStatus::Resolved => 'Complaint marked as resolved.',
            default => 'Complaint dismissed.',
        });
    }

    public function warn(Request $request, Complaint $complaint, ComplaintActionService $actions): RedirectResponse
    {
        $data = $request->validateWithBag('warn', [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write the warning — it is emailed to the counselor.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $actions->warnCounselor($complaint, $request->user(), trim($data['reason']));
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($complaint)->with('error', implode(' ', $e->reasons));
        }

        return $this->back($complaint)->with('status', "Warning emailed to {$complaint->counselor->name}.");
    }

    public function suspendCounselor(Request $request, Complaint $complaint, ComplaintActionService $actions): RedirectResponse
    {
        $data = $request->validateWithBag('suspend', [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'sessions' => ['nullable', Rule::in(['keep', 'cancel_upcoming'])],
        ], [
            'reason.required' => 'Write the reason for suspending — the counselor is told, and it is kept in the audit log.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $actions->suspendCounselor($complaint, $request->user(), trim($data['reason']), ($data['sessions'] ?? 'keep') === 'cancel_upcoming');
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($complaint)->with('error', implode(' ', $e->reasons));
        } catch (InvalidCounselorTransition) {
            return $this->back($complaint)->with('error', 'This counselor is no longer approved (someone else may have just suspended them). Nothing was changed.');
        }

        return $this->back($complaint)->with('status', "{$complaint->counselor->name} suspended. They are no longer listed and have been emailed.");
    }

    private function back(Complaint $complaint): RedirectResponse
    {
        return redirect()->route('platform.complaints.show', $complaint);
    }

    /** Another admin got there first (or the page was stale): say what actually happened. */
    private function alreadyChanged(Complaint $complaint): RedirectResponse
    {
        $complaint->refresh();

        $last = AuditLog::with('actor')
            ->where('target_type', 'complaint')->where('target_id', $complaint->id)->where('action', 'complaint.status_changed')
            ->latest('id')->first();

        $who = $last?->actor?->name ?? 'another admin';

        return $this->back($complaint)->with('error', "This complaint is already {$complaint->status->value} (by {$who}). Nothing was changed.");
    }
}
