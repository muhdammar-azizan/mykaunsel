<?php

namespace App\Support;

use App\Enums\VerificationStatus;
use App\Models\CounselorProfile;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** The Counselors list: tab, search and filters, validated on the server and kept in the URL. */
class CounselorListFilters
{
    public const TABS = ['pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected'];

    public const PER_PAGE = 10;

    /** tab => the status it shows */
    private const STATUS = [
        'pending' => VerificationStatus::Pending,
        'active' => VerificationStatus::Approved,
        'suspended' => VerificationStatus::Suspended,
        'rejected' => VerificationStatus::Rejected,
    ];

    /** @param  array<string, mixed>  $values  valid, non-empty filters only */
    private function __construct(private array $values, private MessageBag $errors) {}

    public static function fromRequest(Request $request): self
    {
        $validator = Validator::make($request->query(), [
            'tab' => ['nullable', Rule::in(array_keys(self::TABS))],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'regex:/^(independent|organization(:[1-9][0-9]{0,18})?)$/'],
            'pa' => ['nullable', Rule::in(['valid', 'expiring', 'expired'])],
            'flagged' => ['nullable', 'boolean'],
        ], [
            'tab.in' => 'Unknown tab.',
            'type.regex' => 'Unknown counselor type.',
            'pa.in' => 'Unknown PA status.',
        ]);

        $values = collect($validator->valid())
            ->map(fn ($v) => is_string($v) ? trim($v) : $v)
            ->reject(fn ($v) => $v === null || $v === '')
            ->all();

        if (isset($values['flagged']) && ! filter_var($values['flagged'], FILTER_VALIDATE_BOOLEAN)) {
            unset($values['flagged']);
        }

        return new self($values, $validator->errors());
    }

    public function errors(): MessageBag
    {
        return $this->errors;
    }

    public function tab(): string
    {
        return $this->values['tab'] ?? 'pending';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function isFiltering(): bool
    {
        return collect($this->values)->except('tab')->isNotEmpty();
    }

    /** Organization id picked in the type filter, if any. */
    public function organizationId(): ?int
    {
        return preg_match('/^organization:(\d+)$/', (string) $this->get('type'), $m) ? (int) $m[1] : null;
    }

    /** URL parameters for these filters (tab left out when it is the default). */
    public function toQuery(array $without = [], array $extra = []): array
    {
        $values = collect($this->values)->except($without)->all();

        if (($values['tab'] ?? null) === 'pending') {
            unset($values['tab']);
        }

        return array_merge($values, $extra);
    }

    /** Search + type + PA + flagged. The tab (status) is applied separately so the tab counts can reuse this. */
    public function applyFilters(Builder $query): Builder
    {
        $v = $this->values;

        if (isset($v['q'])) {
            $query->search($v['q']);
        }

        if (isset($v['type'])) {
            if ($v['type'] === 'independent') {
                $query->where('verification_type', 'platform_verified');
            } else {
                $query->where('verification_type', 'org_assigned');

                if ($organizationId = $this->organizationId()) {
                    $query->whereHas('memberships', fn (Builder $m) => $m->where('org_id', $organizationId)->where('role', 'counselor'));
                }
            }
        }

        if (isset($v['pa'])) {
            $query->paStatus($v['pa']);
        }

        if (isset($v['flagged'])) {
            $query->flagged();
        }

        return $query;
    }

    /** The profiles of one tab. Pending / rejected are independent counselors only (organization counselors have no approval flow). */
    public static function forTab(Builder $query, string $tab): Builder
    {
        $query->withStatus(self::STATUS[$tab]);

        if (in_array($tab, ['pending', 'rejected'], true)) {
            $query->independent();
        }

        return $query;
    }

    /** Each tab's own order: pending oldest first, active by PA expiry soonest first, the rest newest first. */
    public static function order(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'pending' => $query->orderByRaw('COALESCE(submitted_at, created_at) asc')->orderBy('id'),
            'active' => $query->orderBy('pa_valid_until')->orderBy('id'),
            'suspended' => $query->orderByRaw('COALESCE(suspended_at, updated_at) desc')->orderByDesc('id'),
            'rejected' => $query->orderByRaw('COALESCE(reviewed_at, updated_at) desc')->orderByDesc('id'),
        };
    }

    /** @return array<string, int> live counts for every tab, with the search / filters applied */
    public function counts(): array
    {
        $counts = [];

        foreach (array_keys(self::TABS) as $tab) {
            $counts[$tab] = self::forTab($this->applyFilters(CounselorProfile::query()), $tab)->count();
        }

        return $counts;
    }
}
