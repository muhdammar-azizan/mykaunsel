<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlatformOrganizationController extends Controller
{
    public function index(Request $request)
    {
        return view('platform.organizations');
    }
}
