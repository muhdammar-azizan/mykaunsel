<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidCounselorTransition;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\User;
use App\Services\CounselorStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Platform Admin → Counselors: suspending, reactivating and reminding an approved counselor (independent or organization). */
class PlatformCounselorStatusController extends Controller
{
    public function suspend(Request $request, CounselorProfile $counselor, CounselorStatusService $status): RedirectResponse
    {
        $data = $request->validateWithBag('suspend', [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'sessions' => ['nullable', Rule::in(['keep', 'cancel_upcoming'])],
        ], [
            'reason.required' => 'Write the reason for suspending — the counselor is told, and it is kept in the audit log.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $status->suspend($counselor, $request->user(), trim($data['reason']), ($data['sessions'] ?? 'keep') === 'cancel_upcoming');
        } catch (InvalidCounselorTransition) {
            return $this->alreadyChanged($counselor);
        }

        return $this->back($counselor)->with('status', "{$counselor->user->name} suspended. They are no longer listed and have been emailed.");
    }

    public function reactivate(Request $request, CounselorProfile $counselor, CounselorStatusService $status): RedirectResponse
    {
        try {
            $status->reactivate($counselor, $request->user());
        } catch (InvalidCounselorTransition) {
            return $this->alreadyChanged($counselor);
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($counselor)->with('error', 'Cannot reactivate yet. '.implode(' ', $e->reasons));
        }

        return $this->back($counselor)->with('status', "{$counselor->user->name} reactivated. They are listed again and have been emailed.");
    }

    public function remindRenewal(Request $request, CounselorProfile $counselor, CounselorStatusService $status): RedirectResponse
    {
        try {
            $status->sendRenewalReminder($counselor, $request->user());
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($counselor)->with('error', implode(' ', $e->reasons));
        }

        return $this->back($counselor)->with('status', "Reminder emailed to {$counselor->user->email}.");
    }

    /**
     * DEVELOPMENT ONLY — registered in the local and testing environments and nowhere else (see routes/web.php).
     * Stands in for the LKM review-request flow, which is not built yet: sets the PA valid-until date on the counselor's
     * LKM snapshot record so reactivation can be tried. Platform admins only, like everything here.
     */
    public function devSetLkmDate(Request $request, CounselorProfile $counselor): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $record = LkmDirectorySnapshot::where('kb_number', $counselor->kb_number)->first();

        if ($record === null) {
            return $this->back($counselor)->with('error', 'There is no LKM record for this KB number to change.');
        }

        $record->update(['pa_valid_until' => $data['date']]);

        return $this->back($counselor)->with('status', "(Dev) LKM record PA valid-until set to {$data['date']}.");
    }

    private function back(CounselorProfile $counselor): RedirectResponse
    {
        return redirect()->route('platform.counselors.show', $counselor);
    }

    private function alreadyChanged(CounselorProfile $counselor): RedirectResponse
    {
        $counselor->refresh();

        $last = AuditLog::with('actor')
            ->where('target_type', 'counselor')
            ->where('target_id', $counselor->id)
            ->whereIn('action', ['counselor.approved', 'counselor.suspended', 'counselor.reactivated', 'counselor.pa_expired_suspended'])
            ->latest('id')
            ->first();

        $who = $last?->isSystem() ? 'the system' : ($last?->actor?->name ?? User::find($counselor->suspended_by ?? $counselor->reviewed_by)?->name ?? 'another admin');

        return $this->back($counselor)->with('error', "This counselor is already {$counselor->verification_status->value} (by {$who}). Nothing was changed.");
    }
}
