<?php

namespace App\Support;

use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns audit rows into the plain arrays the Audit Log page and its drawer show:
 * times in the admin's timezone, the actor, the action badge, the target (linked if it still exists), a one-line summary.
 */
class AuditEntryPresenter
{
    /** badge type => [label, tailwind classes, icon path] — colour + icon + text, never colour alone. */
    private const BADGES = [
        'created' => ['Created', 'bg-brand-blue/10 text-brand-blue', 'M12 5v14M5 12h14'],
        'approved' => ['Approved', 'bg-emerald-50 text-emerald-700', 'M20 6 9 17l-5-5'],
        'rejected' => ['Rejected', 'bg-brand-red/10 text-brand-red', 'M18 6 6 18M6 6l12 12'],
        'suspended' => ['Suspended', 'bg-brand-yellow/25 text-[#7A5410]', 'M10 4H6v16h4zM18 4h-4v16h4z'],
        'reactivated' => ['Reactivated', 'bg-sky-50 text-sky-700', 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5'],
        'updated' => ['Updated', 'bg-slate-100 text-slate-700', 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z'],
        'deleted' => ['Deleted', 'bg-rose-50 text-rose-700', 'M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14'],
        'exported' => ['Exported', 'bg-indigo-50 text-indigo-700', 'M12 3v12M7 10l5 5 5-5M5 21h14'],
        'security' => ['Security', 'bg-orange-50 text-orange-700', 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z'],
        'system' => ['System', 'bg-zinc-100 text-zinc-700', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
    ];

    private const ROLE_LABELS = [
        'platform_admin' => 'Platform admin',
        'org_admin' => 'Organization admin',
        'counselor' => 'Counselor',
    ];

    /** @var array<string, array{exists: ?bool, url: ?string}> */
    private array $targets = [];

    public function __construct(private string $timezone) {}

    /** Look up (in a few queries, not one per row) whether each entry's target still exists and where it lives. */
    public function preload(Collection $logs): self
    {
        $models = array_flip(config('audit.target_types', []));

        foreach ($logs->whereNotNull('target_type')->whereNotNull('target_id')->groupBy('target_type') as $type => $group) {
            $class = $models[$type] ?? null;

            if ($class === null || ! is_subclass_of($class, Model::class)) {
                foreach ($group as $log) {
                    $this->targets["{$type}:{$log->target_id}"] = ['exists' => null, 'url' => null];
                }

                continue;
            }

            $found = $class::query()->whereIn((new $class)->getKeyName(), $group->pluck('target_id')->unique())->get()->keyBy->getKey();

            foreach ($group as $log) {
                $model = $found->get($log->target_id);
                $this->targets["{$type}:{$log->target_id}"] = [
                    'exists' => $model !== null,
                    'url' => $model ? $this->urlFor($type, $model) : null,
                ];
            }
        }

        return $this;
    }

    /** One table row. */
    public function row(AuditLog $log): array
    {
        $def = AuditCatalogue::describe($log->action);
        $badge = self::BADGES[$def['badge']] ?? self::BADGES['updated'];
        $local = $log->created_at->copy()->timezone($this->timezone);

        return [
            'id' => $log->id,
            'time' => $local->format('j M Y, g:i:s A'),
            'time_iso' => $log->created_at->copy()->utc()->toIso8601String(),
            'time_short' => $local->format('j M Y'),
            'time_utc' => $log->created_at->copy()->utc()->format('Y-m-d H:i:s').' UTC',
            'actor' => $this->actor($log),
            'action_key' => $log->action,
            'action_label' => $def['label'],
            'category' => config('audit.categories')[$def['category']]['label'] ?? Str::headline($def['category']),
            'badge' => ['type' => $def['badge'], 'label' => $badge[0], 'classes' => $badge[1], 'icon' => $badge[2]],
            'target' => $this->target($log),
            'summary' => $this->summary($log),
        ];
    }

    /** Everything the detail drawer shows for one entry. */
    public function detail(AuditLog $log, Collection $related): array
    {
        $row = $this->row($log);
        $meta = $log->metadata ?? [];
        $local = $log->created_at->copy()->timezone($this->timezone);

        $organization = null;
        if ($log->organization_id) {
            $org = Organization::find($log->organization_id);
            $organization = [
                'id' => $log->organization_id,
                'name' => $org?->name ?? "Organization #{$log->organization_id}".($org ? '' : ' (deleted)'),
                'url' => $org && $org->org_type !== OrgType::Platform ? route('platform.organizations.show', $org) : null,
                'history_url' => route('platform.audit-log', ['organization' => $log->organization_id]),
            ];
        }

        $viewAll = match (true) {
            $log->actor_type === 'system' => route('platform.audit-log', ['actor' => 'system']),
            $log->actor_user_id !== null => route('platform.audit-log', ['actor' => $log->actor_user_id] + ($log->category === 'auth' ? ['sign_in' => 1] : [])),
            default => null,
        };

        return $row + [
            'time_full' => $local->format('l, j F Y, g:i:s A').' ('.$this->timezone.')',
            'organization' => $organization,
            'reason' => isset($meta['reason']) && is_scalar($meta['reason']) ? (string) $meta['reason'] : null,
            'changes' => $this->changes($meta),
            'details' => $this->otherDetails($meta),
            'seeded' => (bool) ($meta['seeded'] ?? false),
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'request_id' => $log->request_id,
            'view_all_url' => $viewAll,
            'related' => $related->map(fn (AuditLog $other) => [
                'id' => $other->id,
                'time' => $other->created_at->copy()->timezone($this->timezone)->format('g:i:s A'),
                'action_label' => AuditCatalogue::describe($other->action)['label'],
                'target' => $other->target_name ?? ($other->target_type ? ucfirst($other->target_type).' #'.$other->target_id : null),
            ])->values()->all(),
        ];
    }

    // ---- pieces ----------------------------------------------------------------------------

    /** @return array{kind: string, name: string, role: ?string, initials: string, avatar: ?string, user_id: ?int} */
    private function actor(AuditLog $log): array
    {
        if ($log->actor_type === 'system') {
            return ['kind' => 'system', 'name' => 'System', 'role' => 'Scheduled job', 'initials' => '', 'avatar' => null, 'user_id' => null];
        }

        if ($log->actor_type === 'guest' || $log->actor_user_id === null) {
            return ['kind' => 'guest', 'name' => 'Not signed in', 'role' => null, 'initials' => '?', 'avatar' => null, 'user_id' => null];
        }

        $user = $log->relationLoaded('actor') ? $log->actor : null;
        // The saved copy first; for people we never copied (non-staff) fall back to the live account, then to a bare id.
        $name = $log->actor_name ?? $user?->name ?? "User #{$log->actor_user_id}";

        return [
            'kind' => 'user',
            'name' => $name,
            'role' => self::ROLE_LABELS[$log->actor_role] ?? null,
            'initials' => $user?->initials ?? collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode(''),
            'avatar' => $user?->avatar_url,
            'user_id' => $log->actor_user_id,
        ];
    }

    /** @return array{label: string, name: ?string, url: ?string, deleted: bool, has_target: bool} */
    private function target(AuditLog $log): array
    {
        if ($log->target_type === null) {
            return ['label' => '', 'name' => null, 'url' => null, 'deleted' => false, 'has_target' => false];
        }

        $info = $this->targets["{$log->target_type}:{$log->target_id}"] ?? ['exists' => null, 'url' => null];

        return [
            'label' => Str::headline($log->target_type),
            'name' => $log->target_name ?? ($log->target_id ? '#'.$log->target_id : null),
            'url' => $info['url'],
            'deleted' => $info['exists'] === false,
            'has_target' => true,
        ];
    }

    private function urlFor(string $type, Model $model): ?string
    {
        return match ($type) {
            'organization' => $model instanceof Organization && $model->org_type !== OrgType::Platform
                ? route('platform.organizations.show', $model)
                : null,
            default => null, // no page for this kind of record yet
        };
    }

    /** One short line for the table: the reason, or "pending → active", or a summary. */
    public function summary(AuditLog $log): string
    {
        $meta = $log->metadata ?? [];

        if (isset($meta['reason']) && is_scalar($meta['reason'])) {
            return Str::limit((string) $meta['reason'], 160, '…');
        }

        $changes = $this->changes($meta);
        if ($changes !== []) {
            $first = $changes[0];
            $line = $first['field'] === 'Status' ? "{$first['old']} → {$first['new']}" : "{$first['field']}: {$first['old']} → {$first['new']}";

            return Str::limit($line.(count($changes) > 1 ? ' (+'.(count($changes) - 1).' more)' : ''), 160, '…');
        }

        foreach (['summary', 'domain'] as $key) {
            if (isset($meta[$key]) && is_scalar($meta[$key])) {
                return Str::limit((string) $meta[$key], 160, '…');
            }
        }

        return '';
    }

    /** @return list<array{field: string, old: string, new: string}> */
    public function changes(array $meta): array
    {
        $out = [];

        foreach ((array) ($meta['changes'] ?? []) as $field => $pair) {
            if (! is_array($pair) || ! array_key_exists('old', $pair) && ! array_key_exists('new', $pair)) {
                continue;
            }

            $out[] = ['field' => Str::headline((string) $field), 'old' => $this->show($pair['old'] ?? null), 'new' => $this->show($pair['new'] ?? null)];
        }

        return $out;
    }

    /** Remaining metadata as label/value pairs (reason, changes and the sample-data flag are shown elsewhere). */
    private function otherDetails(array $meta): array
    {
        $out = [];

        foreach (collect($meta)->except(['reason', 'changes', 'seeded']) as $key => $value) {
            $out[] = ['label' => Str::headline((string) $key), 'value' => $this->show($value)];
        }

        return $out;
    }

    private function show(mixed $value): string
    {
        return match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
