<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrgCounselorController extends Controller
{
    public function index(Request $request)
    {
        return view('organizations.dashboard.counselors');
    }
}
