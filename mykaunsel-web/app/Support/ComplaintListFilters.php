<?php

namespace App\Support;

use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The Complaints list: tab, search and filters, validated on the server and kept in the URL. */
class ComplaintListFilters
{
    public const TABS = ['open' => 'Open', 'under_review' => 'Under review', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'];

    public const PER_PAGE = 10;

    /** @param  array<string, mixed>  $values  valid, non-empty filters only */
    private function __construct(private array $values, private MessageBag $errors) {}

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->query(), [
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'q' => ['nullable', 'string', 'max:100'],
            'counselor' => ['nullable', 'integer', 'min:1'],
            'organization' => ['nullable', 'integer', 'min:1'],
        ], ['tab.in' => 'Unknown tab.']);

        $values = collect($validator->valid())
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->reject(fn ($v) => $v === null || $v === '')
            ->all();

        return new self($values, $validator->errors());
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    public function tab(): string
    {
        return $this->values['tab'] ?? 'open';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isFiltering(): bool
    {
        return collect($this->values)->except('tab')->isNotEmpty();
    }

    /** URL parameters for these filters (the tab is left out when it is the default). */
    public function toQuery(array $without = [], array $extra = []): array
    {
        $values = collect($this->values)->except($without)->all();

        if (($values['tab'] ?? null) === 'open') {
            unset($values['tab']);
        }

        return array_merge($values, $extra);
    }

    /** Search + counselor + organization. The tab (status) is applied separately so the tab counts can reuse this. */
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

        return $query;
    }

    /** Open and under-review complaints: oldest first (they should not be buried). Closed ones: most recently touched first. */
    public static function order(Builder $query, string $tab): Builder
    {
        return in_array($tab, ['open', 'under_review'], true)
            ? $query->orderBy('created_at')->orderBy('id')
            : $query->orderByDesc('updated_at')->orderByDesc('id');
    }

    /** @return array<string, int> live counts for every tab, with the search / filters applied */
    public function counts(): array
    {
        $counts = [];

        foreach (self::TABS as $tab => $_) {
            $counts[$tab] = $this->applyFilters(Complaint::query())->withStatus(ComplaintStatus::from($tab))->count();
        }

        return $counts;
    }
}
