<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Builds the "Activity" list on the organization detail page, newest first.
 * Sources: the application itself (submit / resubmit), domain verification times, and the audit log.
 */
class OrganizationTimeline
{
    /** audit action => [title, tone]. Unknown actions are left out on purpose. */
    private const ACTIONS = [
        'organization_approved' => ['Approved', 'good'],
        'organization_rejected' => ['Rejected', 'bad'],
        'organization_note_added' => ['Internal note added', 'neutral'],
        'organization_suspended' => ['Suspended', 'bad'],
        'organization_reactivated' => ['Reactivated', 'good'],
        'organization_dns_checked' => ['DNS checked', 'neutral'],
        'organization_dns_reminder_sent' => ['DNS reminder emailed', 'neutral'],
    ];

    /** @return Collection<int, array{at: \Illuminate\Support\Carbon, title: string, detail: ?string, actor: string, tone: string, seq: int}> */
    public function for(Organization $organization): Collection
    {
        $organization->loadMissing(['orgDomains', 'registrant.user']);

        $registrant = $organization->registrant?->user?->name ?? 'Applicant';
        $events = collect();

        $submittedAt = $organization->submitted_at ?? $organization->created_at;
        $resubmitted = $organization->submission_count >= 2;

        $events->push([
            'at' => $submittedAt,
            'title' => $resubmitted ? "Resubmitted (attempt {$organization->submission_count})" : 'Application submitted',
            'detail' => null,
            'actor' => $registrant,
            'tone' => 'info',
        ]);

        foreach ($organization->orgDomains->whereNotNull('verified_at') as $domain) {
            $events->push([
                'at' => $domain->verified_at,
                'title' => 'Domain verified',
                'detail' => $domain->domain,
                'actor' => 'DNS check',
                'tone' => 'good',
            ]);
        }

        AuditLog::query()
            ->with('actor')
            ->where('target_type', Organization::class)
            ->where('target_id', $organization->id)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $log) use ($events) {
                [$title, $tone] = self::ACTIONS[$log->action];

                $events->push([
                    'at' => $log->created_at,
                    'title' => $title,
                    'detail' => $log->metadata['reason'] ?? null,
                    'actor' => $log->actor?->name ?? ($log->actor_user_id ? 'Deleted user' : 'System'),
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
