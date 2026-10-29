<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The Audit Log page's filters: validated on the server, applied to a query, and turned back into
 * URL parameters / removable chips. The same object drives the table and the CSV export,
 * so what you see is exactly what you export.
 */
class AuditLogFilters
{
    public const RANGES = ['today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'custom' => 'Custom'];

    /** @param  array<string, mixed>  $values  only the valid, non-empty filters */
    private function __construct(
        private array $values,
        private MessageBag $errors,
        private string $timezone,
    ) {}

    /** Read the filters from the query string. Invalid values are dropped and reported, never trusted. */
    public static function fromRequest(Request $request, string $timezone): self
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::in(array_keys(config('audit.categories', [])))],
            'action' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_.]+$/'],
            // Several actions at once, written as a comma list of known action keys (the Dashboard's "View all approvals" link uses it).
            'actions' => ['nullable', 'string', 'max:400', 'regex:/^[a-z0-9_.]+(,[a-z0-9_.]+){0,9}$/'],
            'actor' => ['nullable', 'string', 'regex:/^(system|[1-9][0-9]{0,18})$/'],
            'target_type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'organization' => ['nullable', 'integer', 'min:1'],
            'range' => ['nullable', Rule::in(array_keys(self::RANGES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sign_in' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', Rule::in(config('audit.per_page_options', [25, 50, 100]))],
        ], [
            'category.in' => 'Unknown category.',
            'action.regex' => 'Unknown action.',
            'actions.regex' => 'Unknown actions.',
            'actor.regex' => 'Unknown admin.',
            'range.in' => 'Unknown date range.',
            'from.date_format' => 'The start date must look like 2026-10-22.',
            'to.date_format' => 'The end date must look like 2026-10-22.',
            'to.after_or_equal' => 'The end date cannot be before the start date.',
            'per_page.in' => 'Choose 25, 50 or 100 rows per page.',
        ]);

        $values = collect($validator->valid())
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->reject(fn ($v) => $v === null || $v === '')
            ->all();

        // Dates without a preset mean a custom range.
        if (! isset($values['range']) && (isset($values['from']) || isset($values['to']))) {
            $values['range'] = 'custom';
        }

        // The sign-in toggle is a boolean: "0" / false are simply "off".
        if (isset($values['sign_in']) && ! filter_var($values['sign_in'], FILTER_VALIDATE_BOOLEAN)) {
            unset($values['sign_in']);
        }

        return new self($values, $validator->errors(), $timezone);
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isFiltering(): bool
    {
        return $this->active() !== [];
    }

    public function perPage(): int
    {
        return (int) ($this->values['per_page'] ?? config('audit.per_page_options.0', 25));
    }

    public function includesSignIn(): bool
    {
        return isset($this->values['sign_in']) || ($this->values['category'] ?? null) === 'auth';
    }

    /** Filters that narrow the results (everything except paging). */
    public function active(): array
    {
        return collect($this->values)->except('per_page')->all();
    }

    /** URL parameters for this filter set (add/remove keys as needed). */
    public function toQuery(array $without = [], array $extra = []): array
    {
        $values = collect($this->values)->except($without)->all();

        // from/to only make sense with the custom preset.
        if (($values['range'] ?? null) !== 'custom') {
            unset($values['from'], $values['to']);
        }

        return array_merge($values, $extra);
    }

    /** @return list<array{label: string, value: string, remove: array}> */
    public function chips(array $actorNames = [], array $organizationNames = []): array
    {
        $chips = [];
        $add = function (string $label, string $value, array $without) use (&$chips) {
            $chips[] = ['label' => $label, 'value' => $value, 'remove' => $this->toQuery($without)];
        };

        if (isset($this->values['q'])) {
            $add('Search', '“'.$this->values['q'].'”', ['q']);
        }
        if (isset($this->values['category'])) {
            $add('Category', config('audit.categories')[$this->values['category']]['label'] ?? $this->values['category'], ['category']);
        }
        if (isset($this->values['action'])) {
            $add('Action', AuditCatalogue::describe($this->values['action'])['label'], ['action']);
        }
        if (isset($this->values['actions'])) {
            $add('Actions', collect(explode(',', $this->values['actions']))->map(fn ($key) => AuditCatalogue::describe($key)['label'])->implode(', '), ['actions']);
        }
        if (isset($this->values['actor'])) {
            $actor = $this->values['actor'];
            $add('Admin', $actor === 'system' ? 'System' : ($actorNames[$actor] ?? "User #{$actor}"), ['actor']);
        }
        if (isset($this->values['target_type'])) {
            $add('Target', ucfirst(str_replace('_', ' ', $this->values['target_type'])), ['target_type']);
        }
        if (isset($this->values['organization'])) {
            $id = (int) $this->values['organization'];
            $add('Organization', $organizationNames[$id] ?? "Organization #{$id}", ['organization']);
        }
        if (isset($this->values['range'])) {
            $range = $this->values['range'];
            $value = $range === 'custom'
                ? trim(($this->values['from'] ?? '…').' → '.($this->values['to'] ?? '…'))
                : self::RANGES[$range];
            $add('Date', $value, ['range', 'from', 'to']);
        }
        if (isset($this->values['sign_in'])) {
            $add('Sign-in activity', 'Included', ['sign_in']);
        }

        return $chips;
    }

    /** Narrow the query. Always newest first. */
    public function apply(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['q'])) {
            // LIKE wildcards typed by the user are not wildcards. "!" is the escape character
            // because a backslash means different things to SQLite and MySQL.
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $v['q']).'%';
            $query->where(function (Builder $w) use ($term) {
                $w->whereRaw("actor_name like ? escape '!'", [$term])
                    ->orWhereRaw("target_name like ? escape '!'", [$term])
                    ->orWhereRaw("action like ? escape '!'", [$term]);
            });
        }

        if (isset($v['category'])) {
            $query->where('category', $v['category']);
        }

        // Sign-in noise stays out of the way unless asked for.
        if (! $this->includesSignIn()) {
            $query->whereNotIn('category', AuditCatalogue::hiddenByDefault());
        }

        if (isset($v['action'])) {
            $query->where('action', $v['action']);
        }

        if (isset($v['actions'])) {
            $query->whereIn('action', explode(',', $v['actions']));
        }

        if (isset($v['actor'])) {
            $v['actor'] === 'system'
                ? $query->where('actor_type', 'system')
                : $query->where('actor_user_id', (int) $v['actor']);
        }

        if (isset($v['target_type'])) {
            $query->where('target_type', $v['target_type']);
        }

        if (isset($v['organization'])) {
            $query->where('organization_id', (int) $v['organization']);
        }

        [$from, $to] = $this->dateBounds();
        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * The date range as UTC bounds. "Today", "7 days" and custom dates are days in the ADMIN's timezone
     * (the one set on their Account page), not UTC.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function dateBounds(): array
    {
        $range = $this->values['range'] ?? null;
        $now = Carbon::now($this->timezone);

        return match ($range) {
            'today' => [$now->copy()->startOfDay()->utc(), null],
            '7d' => [$now->copy()->subDays(6)->startOfDay()->utc(), null],
            '30d' => [$now->copy()->subDays(29)->startOfDay()->utc(), null],
            'custom' => [
                isset($this->values['from']) ? Carbon::createFromFormat('Y-m-d', $this->values['from'], $this->timezone)->startOfDay()->utc() : null,
                isset($this->values['to']) ? Carbon::createFromFormat('Y-m-d', $this->values['to'], $this->timezone)->endOfDay()->utc() : null,
            ],
            default => [null, null],
        };
    }

    /** Names for the organization filter / chip: only organizations that appear in the log. */
    public static function organizationNames(array $ids): array
    {
        return Organization::whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}
