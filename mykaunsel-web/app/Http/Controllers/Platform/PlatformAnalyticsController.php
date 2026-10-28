<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Facades\Audit;
use App\Services\AnalyticsReports;
use App\Support\AnalyticsCsv;
use App\Support\AnalyticsRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform Admin → Analytics. Read-only: it only shows what AnalyticsService works out (aggregates, no person or organization).
 * The range lives in the URL (?range=7|30|90 or ?range=custom&from=…&to=…), so a page can be bookmarked and shared.
 *
 * Opening the page is NOT audited (it is a read of aggregates). The CSV export IS audited: the range and the sections, nothing else.
 */
class PlatformAnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsReports $reports): View
    {
        $timezone = $request->user()->display_timezone;
        [$range, $choice, $error] = AnalyticsRange::fromQuery($request->query(), $timezone);
        $result = $reports->get($range);

        return view('platform.analytics.index', [
            'report' => $result['report'],
            'range' => $range,
            'choice' => $choice,
            'presets' => array_map('intval', (array) config('analytics.presets')),
            'rangeError' => $error,
            'timezone' => $timezone,
            'generatedAt' => $result['generated_at'],
            'today' => now()->setTimezone($timezone)->format('Y-m-d'),
            'rangeQuery' => $this->rangeQuery($request),
        ]);
    }

    /** Refresh: forget the kept result for this range and go back to the page, which works everything out again. */
    public function refresh(Request $request, AnalyticsReports $reports): RedirectResponse
    {
        [$range] = AnalyticsRange::fromQuery($request->query(), $request->user()->display_timezone);
        $reports->forget($range);

        return redirect()->route('platform.analytics', $this->rangeQuery($request));
    }

    /**
     * The shown figures as a CSV (long format: section, label, period, value). A range that is not valid is NOT replaced by the default
     * here (a download of a different range than asked for would mislead): the admin goes back to the page, which says what is wrong.
     */
    public function export(Request $request, AnalyticsReports $reports): StreamedResponse|RedirectResponse
    {
        $timezone = $request->user()->display_timezone;
        [$range, , $error] = AnalyticsRange::fromQuery($request->query(), $timezone);

        if ($error !== null) {
            return redirect()->route('platform.analytics', $this->rangeQuery($request));
        }

        $result = $reports->get($range);
        $rows = AnalyticsCsv::rows($result['report'], $result['generated_at']->setTimezone($timezone)->format('Y-m-d H:i:s'));

        // Recorded before the download starts. Only the range and the names of the sections go in: no figures, no names.
        DB::transaction(fn () => Audit::log('analytics.exported', null, [
            'format' => 'csv',
            'from' => $range->from->format('Y-m-d'),
            'to' => $range->to->format('Y-m-d'),
            'timezone' => $timezone,
            'sections' => AnalyticsCsv::sections($rows),
        ], $request->user()));

        $filename = 'analytics_'.$range->from->format('Ymd').'-'.$range->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'label', 'period', 'value'], ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map([AnalyticsCsv::class, 'neutralise'], $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Only the range parts of the address are carried from one page to the next. */
    private function rangeQuery(Request $request): array
    {
        return array_filter($request->only('range', 'from', 'to'), 'is_string');
    }
}
