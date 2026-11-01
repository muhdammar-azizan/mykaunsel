<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgReports;
use Illuminate\Http\Request;

/**
 * An organization admin's Reviews page: the stars clients gave after sessions with the organization's counselors, never a comment (docs/REVIEWS.md).
 * The figures and the hiding rule are the Reports' (OrgReports, ReportFigures). Always the admin's CURRENT organization; not cached by the browser.
 */
class OrgReviewController extends Controller
{
    public function index(Request $request, OrgReports $reports)
    {
        $organization = Organization::query()->findOrFail($request->session()->get('current_org_id'));
        $period = $reports->period($request->query('range', '12months'));

        return response()->view('organizations.dashboard.reviews', [
            'organization' => $organization,
            'period' => $period,
            'report' => $reports->reviews($organization, $period),
            'minGroup' => (int) config('org-reports.min_group'),
        ])->header('Cache-Control', 'no-store, private');
    }
}
