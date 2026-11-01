<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgDashboard;
use Illuminate\Http\Request;

class OrgDashboardController extends Controller
{
    public function index(Request $request, OrgDashboard $dashboard)
    {
        $organization = Organization::query()->findOrFail($request->session()->get('current_org_id'));

        return view('organizations.dashboard.index', $dashboard->for($organization));
    }
}
