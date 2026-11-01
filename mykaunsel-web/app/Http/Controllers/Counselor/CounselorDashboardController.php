<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Services\CounselorDashboard;
use Illuminate\Http\Request;

class CounselorDashboardController extends Controller
{
    public function index(Request $request, CounselorDashboard $dashboard)
    {
        return view('counselors.dashboard.index', $dashboard->for($request->user()));
    }
}
