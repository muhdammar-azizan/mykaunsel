<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\InvalidAnalyticsRange;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\AnalyticsRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform Admin → Analytics. Read-only: it only shows what AnalyticsService works out (aggregates, no person or organization).
 * The range lives in the URL (?range=7|30|90 or ?range=custom&from=…&to=…), so a page can be bookmarked and shared.
 * Opening the page is NOT audited (it is a read of aggregates).
 */
class PlatformAnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsService $analytics): View
    {
        $timezone = $request->user()->display_timezone;
        $presets = array_map('intval', (array) config('analytics.presets'));
        $default = (int) config('analytics.default_days');

        // A query value can be an array (?range[]=x): anything that is not text counts as missing.
        $text = fn (string $key) => is_string($request->query($key)) ? $request->query($key) : null;

        $choice = $text('range') ?? (string) $default;
        $error = null;

        if ($choice === 'custom') {
            try {
                $range = AnalyticsRange::custom($text('from'), $text('to'), $timezone);
            } catch (InvalidAnalyticsRange $e) {
                // A bad range never breaks the page: say why and show the default one instead.
                $error = $e->getMessage();
                $choice = (string) $default;
                $range = AnalyticsRange::preset($default, $timezone);
            }
        } else {
            $days = in_array((int) $choice, $presets, true) ? (int) $choice : $default;
            $choice = (string) $days;
            $range = AnalyticsRange::preset($days, $timezone);
        }

        return view('platform.analytics.index', [
            'report' => $analytics->report($range),
            'range' => $range,
            'choice' => $choice,
            'presets' => $presets,
            'rangeError' => $error,
            'timezone' => $timezone,
            'generatedAt' => now(),
            'today' => now()->setTimezone($timezone)->format('Y-m-d'),
        ]);
    }
}
