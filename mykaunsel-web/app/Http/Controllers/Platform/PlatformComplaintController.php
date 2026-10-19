<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlatformComplaintController extends Controller
{
    public function index(Request $request)
    {
        return view('platform.complaints');
    }
}
