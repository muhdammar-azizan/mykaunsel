<?php

namespace App\Support;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The Complaints list: tab, search, filters and order, validated on the server and kept in the URL. */
class ComplaintListFilters
{
    /** The first tab (All) is every status together; the rest are one status each. Open is the one shown first by default. */
    public const TABS = ['all' => 'All', 'open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    public const DEFAULT_TAB = 'open';

    /** The first is the default and is left out of the URL. */
    public const SORTS = ['urgent' => 'Most urgent', 'newest' => 'Newest', 'oldest' => 'Oldest'];

    public const PER_PAGE = 10;

    private const OPEN_STATUSES = "'open', 'under_review'";

    /** @param  array<string, mixed>  $values  valid, non-empty filters only (the tab and the order are kept apart: they are not filters) */
    private function __construct(private array $values, private MessageBag $errors, private string $tab, private string $sort, private string $timezone) {}

    /** @param  string  $timezone  the admin's own time zone: "from" and "to" are days on their calendar */
    public static function fromRequest(Request $request, string $timezone = 'UTC'): self
    {
        $validator = Validator::make($request->query(), [
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'q' => ['nullable', 'string', 'max:100'],
            'counselor' => ['nullable', 'integer', 'min:1'],
            'organization' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'tab.in' => 'Unknown tab.',
            'sort.in' => 'Unknown order.',
            'from.date_format' => 'The "from" date must look like 2026-10-27.',
            'to.date_format' => 'The "to" date must look like 2026-10-27.',
            'to.after_or_equal' => 'The "to" date cannot be before the "from" date.',
        ]);

        $values = collect($validator->valid())
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->reject(fn ($v) => $v === null || $v === '')
            ->all();

        $tab = $values['tab'] ?? self::DEFAULT_TAB;
        $sort = $values['sort'] ?? array_key_first(self::SORTS);
        unset($values['tab'], $values['sort']);

        return new self($values, $validator->errors(), $tab, $sort, $timezone);
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    public function tab(): string
    {
        return $this->tab;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isFiltering(): bool
    {
        return $this->values !== [];
    }

    /** URL parameters for this view (the default tab and the default order are left out). */
    public function toQuery(array $without = [], array $extra = []): array
    {
        $values = collect($this->values)->all();

        if ($this->tab !== self::DEFAULT_TAB) {
            $values['tab'] = $this->tab;
        }
        if ($this->sort !== array_key_first(self::SORTS)) {
            $values['sort'] = $this->sort;
        }

        return array_merge(collect($values)->except($without)->all(), $extra);
    }

    /** Search + counselor + organization + dates. The tab (status) is applied separately so the tab counts can reuse this. */
    public function applyFilters(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['q'])) {
            $query->search($v['q']);
        }
        if (isset($v['counselor'])) {
            $query->aboutCounselorProfile((int) $v['counselor']);
        }
        if (isset($v['organization'])) {
            $query->throughOrganization((int) $v['organization']);
        }
        // The days are the admin's own days (a complaint filed at 01:00 on the 27th in Kuala Lumpur is on the 27th).
        if (isset($v['from'])) {
            $query->where('complaints.created_at', '>=', Carbon::createFromFormat('Y-m-d', $v['from'], $this->timezone)->startOfDay()->utc());
        }
        if (isset($v['to'])) {
            $query->where('complaints.created_at', '<=', Carbon::createFromFormat('Y-m-d', $v['to'], $this->timezone)->endOfDay()->utc());
        }

        return $query;
    }

    /** Only the complaints of this tab's status (every status for All). */
    public function applyTab(Builder $query): Builder
    {
        return $this->tab === 'all' ? $query : $query->withStatus(ComplaintStatus::from($this->tab));
    }

    /**
     * The order:
     *  - Most urgent (the default): what still needs a decision first, the one that has waited longest at the top; then
     *    the closed ones, most recently touched first. On a single tab that is simply "longest waiting first" for open and
     *    under-review, and "most recently touched first" for resolved and dismissed.
     *  - Newest / Oldest: by the day it was filed.
     * Every order ends with the id, so a complaint never jumps between pages.
     */
    public function order(Builder $query): Builder
    {
        $open = self::OPEN_STATUSES;

        return match ($this->sort) {
            'newest' => $query->orderByDesc('complaints.created_at')->orderByDesc('complaints.id'),
            'oldest' => $query->orderBy('complaints.created_at')->orderBy('complaints.id'),
            default => match ($this->tab) {
                'open', 'under_review' => $query->orderBy('complaints.created_at')->orderBy('complaints.id'),
                'resolved', 'dismissed' => $query->orderByDesc('complaints.updated_at')->orderByDesc('complaints.id'),
                default => $query
                    ->orderByRaw("case when complaints.status in ({$open}) then 0 else 1 end")
                    ->orderByRaw("case when complaints.status in ({$open}) then complaints.created_at end asc")
                    ->orderByRaw("case when complaints.status in ({$open}) then null else complaints.updated_at end desc")
                    ->orderBy('complaints.id'),
            },
        };
    }

    /** @return array<string, int> live counts for every tab, with the search / filters applied */
    public function counts(): array
    {
        $counts = [];

        foreach (self::TABS as $tab => $_) {
            $query = $this->applyFilters(Complaint::query());
            $counts[$tab] = ($tab === 'all' ? $query : $query->withStatus(ComplaintStatus::from($tab)))->count();
        }

        return $counts;
    }
}
