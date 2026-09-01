<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CounselorPlannerController extends Controller
{
    public function index(Request $request)
    {
        return view('counselors.dashboard.planner');
    }
}
