<?php

namespace App\Services;

use App\Support\AnalyticsRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * The report of a range, kept for a few minutes (config analytics.cache_minutes; 0 = never kept). The key is the range AND the
 * timezone, so two admins in different zones never share a result. The Refresh button forgets the key. The page and the export both
 * read through here, so they always show the same numbers and the same "generated at".
 *
 * Only aggregates are stored (counts, percentages, labels; a small count is already hidden), never a person or an organization.
 */
class AnalyticsReports
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /** @return array{report: array<string, mixed>, generated_at: CarbonImmutable} */
    public function get(AnalyticsRange $range): array
    {
        $minutes = (int) config('analytics.cache_minutes');

        if ($minutes <= 0) {
            return $this->build($range);
        }

        $stored = Cache::remember($range->cacheKey(), now()->addMinutes($minutes), function () use ($range) {
            $built = $this->build($range);

            return ['report' => $built['report'], 'generated_at' => $built['generated_at']->toIso8601String()];
        });

        return ['report' => $stored['report'], 'generated_at' => CarbonImmutable::parse($stored['generated_at'])];
    }

    /** Refresh: the next read works everything out again. */
    public function forget(AnalyticsRange $range): void
    {
        Cache::forget($range->cacheKey());
    }

    /** @return array{report: array<string, mixed>, generated_at: CarbonImmutable} */
    private function build(AnalyticsRange $range): array
    {
        return ['report' => $this->analytics->report($range), 'generated_at' => CarbonImmutable::now()];
    }
}
