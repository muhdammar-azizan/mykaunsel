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
        'organization.approved' => ['Approved', 'good'],
        'organization.rejected' => ['Rejected', 'bad'],
        'organization.note_added' => ['Internal note added', 'neutral'],
        'organization.suspended' => ['Suspended', 'bad'],
        'organization.reactivated' => ['Reactivated', 'good'],
        'organization.dns_checked' => ['DNS checked', 'neutral'],
        'organization.dns_reminder_sent' => ['DNS reminder emailed', 'neutral'],
        'organization.dns_verified' => ['Domain verified', 'good'],
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

        $logs = AuditLog::query()
            ->with('actor')
            ->where('target_type', 'organization')
            ->where('target_id', $organization->id)
            ->whereIn('action', array_keys(self::ACTIONS))
            ->orderBy('id')
            ->get();

        // Domains verified before the audit log existed (or by the applicant) have no audit entry: show them from the domain row.
        $verifiedInLog = $logs->where('action', 'organization.dns_verified')->map(fn ($l) => $l->metadata['domain'] ?? null)->filter()->all();

        foreach ($organization->orgDomains->whereNotNull('verified_at') as $domain) {
            if (in_array($domain->domain, $verifiedInLog, true)) {
                continue;
            }

            $events->push([
                'at' => $domain->verified_at,
                'title' => 'Domain verified',
                'detail' => $domain->domain,
                'actor' => 'DNS check',
                'tone' => 'good',
            ]);
        }

        $logs->each(function (AuditLog $log) use ($events) {
            [$title, $tone] = self::ACTIONS[$log->action];

            $events->push([
                'at' => $log->created_at,
                'title' => $title,
                'detail' => $log->metadata['reason'] ?? $log->metadata['summary'] ?? ($log->action === 'organization.dns_verified' ? ($log->metadata['domain'] ?? null) : null),
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
