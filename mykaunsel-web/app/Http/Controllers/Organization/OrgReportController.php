<?php

namespace App\Http\Controllers\Organization;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgReports;
use App\Support\ReportCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * An organization admin's Reports: a glance page and detail pages for a chosen period, an optional comparison with the period before, and a CSV of
 * any page. Counts only, and always about the admin's CURRENT organization. Nothing is cached by the browser. The rules are in OrgReports and
 * ReportFigures; a CSV is built from the same figures the page shows (ReportCsv).
 */
class OrgReportController extends Controller
{
    /** The pages: key => the OrgReports method. Only these can be asked for. */
    private const PAGES = ['overview' => 'overview', 'use' => 'use', 'members' => 'members', 'counselors' => 'counselors', 'quality' => 'quality'];

    /** Pages whose figures can sit next to the period before (the counselors and the quality pages are about people and ratings, so no). */
    private const COMPARABLE = ['overview', 'use', 'members'];

    public function overview(Request $request, OrgReports $reports)
    {
        return $this->show($request, $reports, 'overview');
    }

    public function use(Request $request, OrgReports $reports)
    {
        return $this->show($request, $reports, 'use');
    }

    public function members(Request $request, OrgReports $reports)
    {
        return $this->show($request, $reports, 'members');
    }

    public function counselors(Request $request, OrgReports $reports)
    {
        return $this->show($request, $reports, 'counselors');
    }

    public function quality(Request $request, OrgReports $reports)
    {
        return $this->show($request, $reports, 'quality');
    }

    /** The CSV of one page for the chosen period. Safe to ask again; each download is audited (which page and period, never the figures). */
    public function export(Request $request, OrgReports $reports, string $page)
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        $organization = $this->organization($request);
        $period = $reports->period($request->query('range'));
        $method = self::PAGES[$page];

        Audit::log('organization.report_exported', $organization, ['page' => $page, 'range' => $period['key']], $request->user());

        $csv = ReportCsv::render(ReportCsv::rows($organization->name, $page, $reports->{$method}($organization, $period), $period));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($organization->name).'-report-'.$page.'-'.$period['key'].'.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function show(Request $request, OrgReports $reports, string $page)
    {
        $organization = $this->organization($request);
        $period = $reports->period($request->query('range'));
        $method = self::PAGES[$page];

        $compare = $request->boolean('compare') && in_array($page, self::COMPARABLE, true);
        $previousPeriod = $compare ? $reports->previous($period) : null;

        return response()->view('organizations.reports.'.$page, [
            'report' => $reports->{$method}($organization, $period),
            'previous' => $compare ? $reports->{$method}($organization, $previousPeriod) : null,
            'previousPeriod' => $previousPeriod,
            'comparable' => in_array($page, self::COMPARABLE, true),
            'organization' => $organization,
            'period' => $period,
            'tab' => $page,
            'minGroup' => (int) config('org-reports.min_group'),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->session()->get('current_org_id'));
    }
}
