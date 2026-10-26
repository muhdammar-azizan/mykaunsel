<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CounselorProfile;
use Illuminate\Support\Collection;

/**
 * The "Activity" list on a counselor's page, newest first. Reads the audit log filtered by this counselor,
 * plus the application's own submit / resubmit times. Never contains client names or session details.
 */
class CounselorTimeline
{
    /** audit action => [title, tone]. Unknown actions are left out on purpose. */
    private const ACTIONS = [
        'counselor.approved' => ['Approved', 'good'],
        'counselor.rejected' => ['Rejected', 'bad'],
        'counselor.resubmitted' => ['Resubmitted documents', 'info'],
        'counselor.suspended' => ['Suspended', 'bad'],
        'counselor.pa_expired_suspended' => ['Suspended automatically: PA expired', 'bad'],
        'counselor.reactivated' => ['Reactivated', 'good'],
        'counselor.note_added' => ['Internal note added', 'neutral'],
        'counselor.document_flagged' => ['Document marked', 'neutral'],
        'counselor.document_viewed' => ['Document viewed', 'neutral'],
        'counselor.document_purged' => ['Document removed (retention)', 'neutral'],
        'counselor.lkm_rechecked' => ['LKM check re-run', 'neutral'],
        'counselor.kb_duplicate_flagged' => ['Someone tried to register this KB number again', 'bad'],
        'counselor.renewal_reminder_sent' => ['PA renewal reminder sent', 'neutral'],
        'lkm.review_requested' => ['Asked for their LKM record to be checked again', 'info'],
        'lkm.review_approved' => ['LKM record updated after their request', 'good'],
        'lkm.review_rejected' => ['LKM re-check request rejected', 'bad'],
        'lkm.evidence_viewed' => ['LKM request evidence viewed', 'neutral'],
        'lkm.evidence_purged' => ['LKM request evidence removed (retention)', 'neutral'],
    ];

    /** @return Collection<int, array{at: \Illuminate\Support\Carbon, title: string, detail: ?string, actor: string, tone: string, seq: int}> */
    public function for(CounselorProfile $profile): Collection
    {
        $profile->loadMissing('user');

        $events = collect();
        $applicant = $profile->user?->name ?? 'Applicant';

        if ($profile->isIndependent()) {
            $events->push([
                'at' => $profile->submittedAtOrCreated(),
                'title' => $profile->submission_count >= 2 ? "Resubmitted (attempt {$profile->submission_count})" : 'Application submitted',
                'detail' => null,
                'actor' => $applicant,
                'tone' => 'info',
            ]);
        } else {
            $events->push(['at' => $profile->created_at, 'title' => 'Added by their organization', 'detail' => null, 'actor' => 'Organization admin', 'tone' => 'info']);
        }

        AuditLog::query()
            ->with('actor')
            ->where('target_type', 'counselor')
            ->where('target_id', $profile->id)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $log) use ($events) {
                [$title, $tone] = self::ACTIONS[$log->action];
                $meta = $log->metadata ?? [];

                $events->push([
                    'at' => $log->created_at,
                    'title' => $title,
                    'detail' => $meta['reason'] ?? $meta['summary'] ?? $meta['document'] ?? null,
                    'actor' => match (true) {
                        $log->isSystem() => 'System',
                        $log->actor_type === 'guest' => 'Someone not signed in',
                        default => $log->actor_name ?? $log->actor?->name ?? ($log->actor_user_id ? 'Deleted user' : 'Unknown'),
                    },
                    'tone' => $tone,
                ]);
            });

        // Newest first; events in the same second keep the order they happened in (later ones first).
        return $events
            ->map(fn ($e, $i) => $e + ['seq' => $i])
            ->sortBy([['at', 'desc'], ['seq', 'desc']])
            ->values();
    }
}
