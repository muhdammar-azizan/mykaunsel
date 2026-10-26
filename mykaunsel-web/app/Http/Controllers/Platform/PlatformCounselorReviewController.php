<?php

namespace App\Http\Controllers\Platform;

use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidCounselorTransition;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Services\CounselorReviewService;
use App\Support\LkmComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Platform Admin → Counselors: reviewing an independent counselor's application.
 * Organization counselors have no approval flow, so none of these routes exist for them (404).
 */
class PlatformCounselorReviewController extends Controller
{
    public function approve(Request $request, CounselorProfile $counselor, CounselorReviewService $review): RedirectResponse
    {
        $this->independentOnly($counselor);

        try {
            $review->approve($counselor, $request->user(), $request->boolean('acknowledge'));
        } catch (InvalidCounselorTransition) {
            return $this->alreadyDecided($counselor);
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($counselor)->with('error', 'Cannot approve yet. '.implode(' ', $e->reasons));
        }

        return $this->back($counselor)->with('status', "{$counselor->user->name} approved. They have been emailed and are now listed.");
    }

    public function reject(Request $request, CounselorProfile $counselor, CounselorReviewService $review): RedirectResponse
    {
        $this->independentOnly($counselor);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['string', Rule::in(array_keys(CounselorDocument::TYPES))],
        ], [
            'reason.required' => 'Write a reason — it is sent to the applicant.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $review->reject($counselor, $request->user(), trim($data['reason']), array_values(array_unique($data['documents'] ?? [])));
        } catch (InvalidCounselorTransition) {
            return $this->alreadyDecided($counselor);
        }

        return $this->back($counselor)->with('status', "{$counselor->user->name} rejected. They have been emailed the reason and which documents to replace.");
    }

    public function markDocument(Request $request, CounselorProfile $counselor, CounselorDocument $document, CounselorReviewService $review): RedirectResponse
    {
        $this->independentOnly($counselor);
        abort_unless($document->counselor_profile_id === $counselor->id, 404);

        $data = $request->validateWithBag('document', [
            'status' => ['required', Rule::in([CounselorDocument::STATUS_OK, CounselorDocument::STATUS_NEEDS_REPLACEMENT])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $review->markDocument($counselor, $document, $data['status'], $data['note'] ?? null, $request->user());
        } catch (CounselorDecisionBlocked $e) {
            return redirect()->to(route('platform.counselors.show', $counselor).'#documents')->with('error', implode(' ', $e->reasons));
        }

        return redirect()->to(route('platform.counselors.show', $counselor).'#documents')->with('status', "{$document->label()} marked.");
    }

    public function recheckLkm(Request $request, CounselorProfile $counselor, CounselorReviewService $review): RedirectResponse
    {
        $this->independentOnly($counselor);

        try {
            $result = $review->recheckLkm($counselor, $request->user());
        } catch (CounselorDecisionBlocked $e) {
            return $this->back($counselor)->with('error', implode(' ', $e->reasons));
        }

        $lkm = $result['lkm'];
        $moved = isset($result['changed']['pa_valid_until'])
            ? ' PA valid-until updated from LKM: '.$result['changed']['pa_valid_until']['old'].' → '.$result['changed']['pa_valid_until']['new'].'.'
            : '';

        if ($lkm->outcome === LkmComparison::MATCH) {
            return redirect()->to(route('platform.counselors.show', $counselor).'#lkm')->with('status', 'LKM check re-run: the record matches.'.$moved);
        }

        $headline = $lkm->blocksApproval() ? 'The LKM number could not be verified.' : 'The LKM record does not fully match.';

        return redirect()->to(route('platform.counselors.show', $counselor).'#lkm')->with('error', "LKM check re-run. {$headline} ".implode(' ', $lkm->problems).$moved);
    }

    /** Tick / untick one checklist box (saved at once, shared with other admins; reference only, never blocks a decision). */
    public function checklist(Request $request, CounselorProfile $counselor): JsonResponse
    {
        $this->independentOnly($counselor);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(CounselorProfile::REVIEW_CHECKLIST))],
            'checked' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $saved = false;

        DB::transaction(function () use ($counselor, $data, $user, &$saved) {
            // Lock so two admins ticking different boxes at once don't overwrite each other.
            $fresh = CounselorProfile::query()->lockForUpdate()->findOrFail($counselor->id);

            if ($fresh->verification_status !== VerificationStatus::Pending) {
                return;
            }

            $state = $fresh->review_checklist ?? [];

            if ($data['checked']) {
                $state[$data['key']] = ['by' => $user->id, 'at' => now()->toIso8601String()];
            } else {
                unset($state[$data['key']]);
            }

            $fresh->update(['review_checklist' => $state ?: null]);
            $saved = true;
        });

        if (! $saved) {
            return response()->json(['message' => 'This application is no longer pending, so its checklist is closed.'], 409);
        }

        return response()->json([
            'checked' => $data['checked'],
            'by' => $data['checked'] ? $user->name : null,
            'at' => $data['checked'] ? 'just now' : null,
        ]);
    }

    public function storeNote(Request $request, CounselorProfile $counselor, CounselorReviewService $review): RedirectResponse
    {
        $this->independentOnly($counselor);

        $data = $request->validateWithBag('note', [
            'body' => ['required', 'string', 'max:2000'],
        ], ['body.required' => 'Write something before adding the note.']);

        $review->addNote($counselor, $data['body'], $request->user());

        return redirect()->to(route('platform.counselors.show', $counselor).'#notes')->with('status', 'Note added.');
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function independentOnly(CounselorProfile $counselor): void
    {
        abort_unless($counselor->isIndependent(), 404);
    }

    private function back(CounselorProfile $counselor): RedirectResponse
    {
        return redirect()->route('platform.counselors.show', $counselor);
    }

    /** Another admin got there first (or the page was stale): say what actually happened. */
    private function alreadyDecided(CounselorProfile $counselor): RedirectResponse
    {
        $counselor->refresh();

        $last = AuditLog::with('actor')
            ->where('target_type', 'counselor')
            ->where('target_id', $counselor->id)
            ->whereIn('action', ['counselor.approved', 'counselor.rejected', 'counselor.suspended', 'counselor.reactivated', 'counselor.pa_expired_suspended'])
            ->latest('id')
            ->first();

        $who = $last?->isSystem() ? 'the system' : ($last?->actor?->name ?? User::find($counselor->reviewed_by)?->name ?? 'another admin');

        return $this->back($counselor)->with('error', "This application is already {$counselor->verification_status->value} (by {$who}). Nothing was changed.");
    }
}
