<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\InvalidCounselorTransition;
use App\Facades\Audit;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Models\CounselorReviewNote;
use App\Models\LkmDirectorySnapshot;
use App\Models\User;
use App\Notifications\CounselorApproved;
use App\Notifications\CounselorRejected;
use App\Support\LkmComparison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Platform Admin's review of an INDEPENDENT counselor application: approve, reject, mark documents, re-run the LKM check, notes.
 *
 * Every change is written in one transaction together with its audit entry (both happen or neither does). The row is
 * locked and its status re-checked first, so two admins cannot approve and reject the same application. Emails go out
 * only after the commit; a mail failure never undoes a decision.
 *
 * The LKM snapshot is only ever read here. The profile's own pa_valid_until column is the source of truth and is
 * refreshed from the record — but only when the record exists, matches the KB number and is Active; otherwise it is
 * never overwritten, the action is blocked and the admin is told why.
 */
class CounselorReviewService
{
    public function __construct(private LkmVerificationService $lkm) {}

    /**
     * What stands in the way of approving, as of right now.
     *
     * @return array{lkm: LkmComparison, blockers: list<string>, needs_acknowledgement: bool}
     */
    public function readiness(CounselorProfile $profile): array
    {
        $profile->load(['user', 'documents']);
        $lkm = $this->lkm->compare($profile);
        $blockers = [];

        if ($lkm->blocksApproval()) {
            $blockers = [...$blockers, ...$lkm->problems];
        }

        $documents = $profile->documents->keyBy('type');
        foreach (CounselorDocument::TYPES as $type => $label) {
            $document = $documents->get($type);

            if ($document === null || ! $document->hasFile()) {
                $blockers[] = "{$label} has not been uploaded.";
            } elseif ($document->needsReplacement()) {
                $blockers[] = "{$label} is marked “Needs replacement”.";
            }
        }

        return ['lkm' => $lkm, 'blockers' => $blockers, 'needs_acknowledgement' => $lkm->needsAcknowledgement()];
    }

    /**
     * @throws InvalidCounselorTransition when someone else already decided
     * @throws CounselorDecisionBlocked when the LKM record or the documents do not allow it
     */
    public function approve(CounselorProfile $profile, User $reviewer, bool $acknowledgedDiscrepancy = false): void
    {
        DB::transaction(function () use ($profile, $reviewer, $acknowledgedDiscrepancy) {
            $locked = $this->lockPending($profile);

            // Re-run the LKM check right now (not the page the admin was looking at a while ago).
            $changes = $this->lkm->refreshPaDate($locked);
            $readiness = $this->readiness($locked);

            if ($readiness['blockers'] !== []) {
                throw new CounselorDecisionBlocked($readiness['blockers']); // rolls the date refresh back too
            }

            if ($readiness['needs_acknowledgement'] && ! $acknowledgedDiscrepancy) {
                throw new CounselorDecisionBlocked([
                    ...$readiness['lkm']->problems,
                    'Tick “I have reviewed this discrepancy” to approve anyway.',
                ]);
            }

            $locked->transitionTo(VerificationStatus::Approved, [
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ], VerificationStatus::Pending);

            $metadata = ['changes' => ['status' => ['old' => 'pending', 'new' => 'approved']] + $changes];
            if ($readiness['needs_acknowledgement']) {
                // The exception is recorded: what did not line up, and that the admin accepted it.
                $metadata['acknowledged_discrepancies'] = $readiness['lkm']->problems;
            }

            Audit::log('counselor.approved', $locked, $metadata, $reviewer);
        });

        DB::afterCommit(fn () => $this->mail($profile, new CounselorApproved));
    }

    /**
     * @param  list<string>  $documentTypes  the documents the applicant must upload again (cert | pa | ic)
     *
     * @throws InvalidCounselorTransition when someone else already decided
     */
    public function reject(CounselorProfile $profile, User $reviewer, string $reason, array $documentTypes = []): void
    {
        $toReplace = [];

        DB::transaction(function () use ($profile, $reviewer, $reason, $documentTypes, &$toReplace) {
            $locked = $this->lockPending($profile);

            foreach ($locked->documents()->whereIn('type', $documentTypes)->get() as $document) {
                if (! $document->hasFile()) {
                    continue; // nothing uploaded, nothing to replace
                }

                $toReplace[] = $document->label();

                if (! $document->needsReplacement()) {
                    $before = $document->status;
                    $document->update(['status' => CounselorDocument::STATUS_NEEDS_REPLACEMENT]);
                    Audit::log('counselor.document_flagged', $locked, [
                        'document' => $document->label(),
                        'document_id' => $document->id,
                        'summary' => "{$document->label()}: needs replacement (rejection)",
                        'changes' => ['document_status' => ['old' => $before, 'new' => CounselorDocument::STATUS_NEEDS_REPLACEMENT]],
                    ], $reviewer);
                }
            }

            $locked->transitionTo(VerificationStatus::Rejected, [
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ], VerificationStatus::Pending);

            Audit::log('counselor.rejected', $locked, [
                'reason' => $reason,
                'documents_to_replace' => $toReplace,
                'changes' => ['status' => ['old' => 'pending', 'new' => 'rejected']],
            ], $reviewer);
        });

        DB::afterCommit(fn () => $this->mail($profile, new CounselorRejected($reason, $toReplace)));
    }

    /**
     * Mark one document OK or Needs replacement, with a short note. What the applicant's resubmit screen reads.
     *
     * @throws CounselorDecisionBlocked when the application is no longer pending or there is no file to mark
     */
    public function markDocument(CounselorProfile $profile, CounselorDocument $document, string $status, ?string $note, User $reviewer): void
    {
        DB::transaction(function () use ($profile, $document, $status, $note, $reviewer) {
            $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);

            if ($locked->verification_status !== VerificationStatus::Pending) {
                throw new CounselorDecisionBlocked(['This application is no longer pending, so its documents can no longer be marked.']);
            }

            $document = $locked->documents()->findOrFail($document->id);

            if (! $document->hasFile()) {
                throw new CounselorDecisionBlocked(["{$document->label()} has no file to mark."]);
            }

            $old = $document->status;
            $note = filled($note) ? trim($note) : null;
            $document->update(['status' => $status, 'note' => $note]);

            $label = $status === CounselorDocument::STATUS_OK ? 'OK' : 'needs replacement';
            Audit::log('counselor.document_flagged', $locked, [
                'document' => $document->label(),
                'document_id' => $document->id,
                'summary' => "{$document->label()}: {$label}".($note ? " — {$note}" : ''),
                'changes' => ['document_status' => ['old' => $old, 'new' => $status]],
            ], $reviewer);
        });
    }

    /**
     * Re-run the LKM check and show the result. Refreshes the profile's PA date from the record when (and only when)
     * the record exists and is Active; the change is audited old → new.
     *
     * @return array{lkm: LkmComparison, changed: array<string, array{old: ?string, new: ?string}>}
     *
     * @throws CounselorDecisionBlocked when the application is no longer pending
     */
    public function recheckLkm(CounselorProfile $profile, User $reviewer): array
    {
        $changed = [];

        DB::transaction(function () use ($profile, $reviewer, &$changed) {
            $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);

            if ($locked->verification_status !== VerificationStatus::Pending) {
                throw new CounselorDecisionBlocked(['This application is no longer pending.']);
            }

            $locked->load('user');
            $changed = $this->lkm->refreshPaDate($locked);
            $outcome = $this->lkm->compare($locked);

            Audit::log('counselor.lkm_rechecked', $locked, [
                'outcome' => $outcome->outcome,
                'summary' => $outcome->label().($outcome->problems ? ': '.implode(' ', $outcome->problems) : ''),
                'changes' => $changed,
            ], $reviewer);
        });

        return ['lkm' => $this->lkm->compare($profile->refresh()->load('user')), 'changed' => $changed];
    }

    /** Internal note between admins (the body stays out of the audit log; only its id is recorded). */
    public function addNote(CounselorProfile $profile, string $body, User $author): CounselorReviewNote
    {
        return DB::transaction(function () use ($profile, $body, $author) {
            $note = CounselorReviewNote::create([
                'counselor_profile_id' => $profile->id,
                'user_id' => $author->id,
                'body' => trim($body),
            ]);

            Audit::log('counselor.note_added', $profile, ['note_id' => $note->id], $author);

            return $note;
        });
    }

    // ---- internals -------------------------------------------------------------------------

    /**
     * Lock the profile row and make sure it is still an independent, pending application.
     *
     * @throws InvalidCounselorTransition
     */
    private function lockPending(CounselorProfile $profile): CounselorProfile
    {
        $locked = $profile->newQuery()->lockForUpdate()->findOrFail($profile->id);

        if ($locked->verification_status !== VerificationStatus::Pending) {
            throw InvalidCounselorTransition::alreadyChanged(VerificationStatus::Pending, $locked->verification_status);
        }

        return $locked->load(['user', 'documents']);
    }

    private function mail(CounselorProfile $profile, object $notification): void
    {
        $user = $profile->user;

        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Could not email the counselor about a decision.', ['profile_id' => $profile->id, 'error' => $e->getMessage()]);
        }
    }
}
