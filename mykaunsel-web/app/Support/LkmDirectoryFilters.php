<?php

namespace App\Support;

use App\Models\LkmDirectorySnapshot;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The LKM Directory list: search and filters, validated on the server and kept in the URL. */
class LkmDirectoryFilters
{
    public const PER_PAGE = 15;

    /** sort value => label. The first is the default and is left out of the URL. */
    public const SORTS = ['name' => 'Name', 'kb' => 'KB number', 'pa' => 'PA expiring first'];

    /** @param  array<string, mixed>  $values  valid, non-empty filters only (the sort is kept apart: it is not a filter) */
    private function __construct(private array $values, private MessageBag $errors, private string $sort = 'name') {}

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(LkmDirectorySnapshot::STATUSES))],
            'pa' => ['nullable', Rule::in(['valid', 'expiring', 'expired'])],
            'state' => ['nullable', 'string', 'max:100'],
            'registered' => ['nullable', Rule::in(['yes', 'no'])],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
        ], [
            'status.in' => 'Unknown status.',
            'pa.in' => 'Unknown PA status.',
            'registered.in' => 'Unknown value for "on MyKaunsel".',
            'sort.in' => 'Unknown sort order.',
        ]);

        $values = collect($validator->valid())
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->reject(fn ($v) => $v === null || $v === '')
            ->all();

        $sort = (string) ($values['sort'] ?? array_key_first(self::SORTS));
        unset($values['sort']);

        return new self($values, $validator->errors(), $sort);
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isFiltering(): bool
    {
        return $this->values !== [];
    }

    /** The URL parameters that rebuild this view (the default sort is not written). */
    public function toQuery(array $extra = []): array
    {
        $sort = $this->sort === array_key_first(self::SORTS) ? [] : ['sort' => $this->sort];

        return array_merge($this->values, $sort, $extra);
    }

    public function apply(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['q'])) {
            $query->search($v['q']);
        }
        if (isset($v['status'])) {
            $query->where('status', $v['status']);
        }
        if (isset($v['pa'])) {
            $query->paStatus($v['pa']);
        }
        if (isset($v['state'])) {
            $query->where('state', $v['state']);
        }
        if (isset($v['registered'])) {
            $query->registered($v['registered'] === 'yes');
        }

        // The last two keys make every order stable, so a record never jumps between pages.
        return match ($this->sort) {
            'kb' => $query->orderBy('kb_number')->orderBy('id'),
            'pa' => $query->orderBy('pa_valid_until')->orderBy('full_name')->orderBy('id'),
            default => $query->orderBy('full_name')->orderBy('id'),
        };
    }
}
