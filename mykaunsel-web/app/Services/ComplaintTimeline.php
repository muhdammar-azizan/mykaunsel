<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Complaint;
use Illuminate\Support\Collection;

/**
 * The "Activity" list on a complaint, newest first: when it was filed, plus everything the audit log recorded
 * against it. Never contains the complaint text or what the reporter said.
 */
class ComplaintTimeline
{
    /** audit action => [title, tone]. Unknown actions are left out on purpose. */
    private const ACTIONS = [
        'complaint.status_changed' => ['Status changed', 'neutral'],
        'complaint.warning_issued' => ['Warning issued to the counselor', 'bad'],
        'complaint.counselor_suspended' => ['Counselor suspended', 'bad'],
        'complaint.note_added' => ['Internal note added', 'neutral'],
    ];

    /** @return Collection<int, array{at: \Illuminate\Support\Carbon, title: string, detail: ?string, actor: string, tone: string, seq: int}> */
    public function for(Complaint $complaint): Collection
    {
        $events = collect([[
            'at' => $complaint->created_at,
            'title' => 'Complaint filed',
            'detail' => null,
            'actor' => 'Reporter',
            'tone' => 'info',
        ]]);

        AuditLog::query()
            ->with('actor')
            ->where('target_type', 'complaint')
            ->where('target_id', $complaint->id)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $log) use ($events) {
                [$title, $tone] = self::ACTIONS[$log->action];
                $meta = $log->metadata ?? [];
                $change = $meta['changes']['status'] ?? null;

                $events->push([
                    'at' => $log->created_at,
                    'title' => $title,
                    'detail' => $change ? "{$change['old']} → {$change['new']}".(isset($meta['reason']) ? ' — '.$meta['reason'] : '') : ($meta['reason'] ?? $meta['summary'] ?? null),
                    'actor' => $log->isSystem() ? 'System' : ($log->actor_name ?? $log->actor?->name ?? ($log->actor_user_id ? 'Deleted user' : 'Unknown')),
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
