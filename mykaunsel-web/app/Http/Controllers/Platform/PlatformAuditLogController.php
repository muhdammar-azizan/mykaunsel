<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AuditCatalogue;
use App\Support\AuditEntryPresenter;
use App\Support\AuditLogCsv;
use App\Support\AuditLogFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only view of the audit log (Platform Admin → Audit Log). Nothing here can change an entry. */
class PlatformAuditLogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $timezone = $request->user()->display_timezone;
        $filters = AuditLogFilters::fromRequest($request, $timezone);

        $logs = $filters->apply(AuditLog::query()->with('actor'))
            ->paginate($filters->perPage())
            ->withQueryString();

        // A page number past the end (e.g. after narrowing the filters): go to the last real page.
        if ($logs->isEmpty() && $logs->currentPage() > 1) {
            return redirect()->route('platform.audit-log', $filters->toQuery(extra: ['per_page' => $filters->perPage(), 'page' => $logs->lastPage()]));
        }

        $presenter = (new AuditEntryPresenter($timezone))->preload($logs->getCollection());
        $rows = $logs->getCollection()->map(fn (AuditLog $log) => $presenter->row($log));

        $actors = AuditLog::query()
            ->whereNotNull('actor_user_id')->whereNotNull('actor_name')
            ->selectRaw('actor_user_id, MAX(actor_name) as name')
            ->groupBy('actor_user_id')->orderBy('name')->limit(200)
            ->pluck('name', 'actor_user_id')->all();

        $organizationIds = AuditLog::query()->whereNotNull('organization_id')->distinct()->pluck('organization_id')->all();
        $organizations = AuditLogFilters::organizationNames($organizationIds);

        // The organization in the URL may not be in the log yet (a link from its page) — still name it in the chip.
        if ($filters->get('organization')) {
            $organizations += AuditLogFilters::organizationNames([(int) $filters->get('organization')]);
        }

        return view('platform.audit-log.index', [
            'filters' => $filters,
            'logs' => $logs,
            'rows' => $rows,
            'chips' => $filters->chips($actors, $organizations),
            'categories' => collect(config('audit.categories'))->map(fn ($c) => $c['label'])->all(),
            'actionOptions' => collect(config('audit.actions'))
                ->map(fn ($def, $key) => ['value' => $key, 'label' => $def['label'], 'category' => $def['category']])
                ->values()->all(),
            'actors' => $actors,
            'organizations' => $organizations,
            'targetTypes' => AuditLog::query()->whereNotNull('target_type')->distinct()->orderBy('target_type')->pluck('target_type')->all(),
            'ranges' => AuditLogFilters::RANGES,
            'perPageOptions' => config('audit.per_page_options'),
            'hasAnyEntries' => AuditLog::query()->exists(),
            'timezone' => $timezone,
            'hiddenCategories' => AuditCatalogue::hiddenByDefault(),
        ]);
    }

    /** JSON for the detail drawer. */
    public function show(Request $request, AuditLog $entry): JsonResponse
    {
        $entry->load('actor');
        $presenter = (new AuditEntryPresenter($request->user()->display_timezone))->preload(collect([$entry]));

        $related = $entry->request_id
            ? AuditLog::query()->where('request_id', $entry->request_id)->where('id', '!=', $entry->id)->orderBy('id')->limit(20)->get()
            : collect();

        return response()->json($presenter->detail($entry, $related));
    }

    /** CSV of whatever the current filters show. The export is itself recorded in the audit log. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $timezone = $request->user()->display_timezone;
        $filters = AuditLogFilters::fromRequest($request, $timezone);
        $query = $filters->apply(AuditLog::query());

        // Count BEFORE streaming: once a download has started there is no way to say "too many".
        $total = (clone $query)->count();
        $max = (int) config('audit.export_max_rows', 10000);

        if ($total > $max) {
            return redirect()->route('platform.audit-log', $filters->toQuery())
                ->with('error', 'That is '.number_format($total).' entries — more than the '.number_format($max).' an export can hold. Narrow the filters (for example a shorter date range) and try again.');
        }

        $maxId = (int) AuditLog::query()->max('id');

        DB::transaction(fn () => Audit::log('audit.exported', null, [
            'format' => 'csv',
            'rows' => $total,
            'filters' => $filters->active() ?: 'none',
        ], $request->user()));

        $csv = new AuditLogCsv($timezone);
        $filename = 'audit-log_'.now($timezone)->format('Ymd_Hi').'.csv';

        return response()->streamDownload(function () use ($csv, $query, $maxId) {
            $out = fopen('php://output', 'w');
            $csv->write($out, $query, $maxId, (int) config('audit.export_chunk', 500));
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
