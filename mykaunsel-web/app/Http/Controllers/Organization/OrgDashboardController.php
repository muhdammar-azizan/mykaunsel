<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrgDashboardController extends Controller
{
    public function index(Request $request)
    {
        return view('organizations.dashboard.index');
    }
}
