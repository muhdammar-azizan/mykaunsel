<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgReports;
use Illuminate\Http\Request;

/**
 * An organization admin's Reports: a glance page and detail pages, for a chosen period. Counts only, and always about the admin's CURRENT organization.
 * The pages are not cached by the browser. The rules are in OrgReports and ReportFigures.
 */
class OrgReportController extends Controller
{
    public function overview(Request $request, OrgReports $reports)
    {
        [$organization, $period] = $this->context($request, $reports);

        return $this->page('organizations.reports.overview', ['report' => $reports->overview($organization, $period)], $organization, $period, 'overview');
    }

    public function use(Request $request, OrgReports $reports)
    {
        [$organization, $period] = $this->context($request, $reports);

        return $this->page('organizations.reports.use', ['report' => $reports->use($organization, $period)], $organization, $period, 'use');
    }

    public function members(Request $request, OrgReports $reports)
    {
        [$organization, $period] = $this->context($request, $reports);

        return $this->page('organizations.reports.members', ['report' => $reports->members($organization, $period)], $organization, $period, 'members');
    }

    public function counselors(Request $request, OrgReports $reports)
    {
        [$organization, $period] = $this->context($request, $reports);

        return $this->page('organizations.reports.counselors', ['report' => $reports->counselors($organization, $period)], $organization, $period, 'counselors');
    }

    public function quality(Request $request, OrgReports $reports)
    {
        [$organization, $period] = $this->context($request, $reports);

        return $this->page('organizations.reports.quality', ['report' => $reports->quality($organization, $period)], $organization, $period, 'quality');
    }

    /** @return array{0: Organization, 1: array<string, mixed>} */
    private function context(Request $request, OrgReports $reports): array
    {
        return [Organization::query()->findOrFail($request->session()->get('current_org_id')), $reports->period($request->query('range'))];
    }

    private function page(string $view, array $data, Organization $organization, array $period, string $tab)
    {
        return response()->view($view, $data + ['organization' => $organization, 'period' => $period, 'tab' => $tab, 'minGroup' => (int) config('org-reports.min_group')])
            ->header('Cache-Control', 'no-store, private');
    }
}
