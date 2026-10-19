<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CounselorSearchController extends Controller
{
    public function index(Request $request)
    {
        return view('counselors.search');
    }
}
