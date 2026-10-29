<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;

/**
 * Platform Admin → Dashboard. It only shows what DashboardService works out: that class composes the methods the owning pages use
 * (see docs/DASHBOARD.md), so a number here and the same number on its page cannot disagree. Nothing is kept between requests.
 */
class PlatformDashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        return view('platform.dashboard', ['data' => $dashboard->build($request->user())]);
    }
}
