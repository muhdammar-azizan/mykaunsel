<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CounselorSignupController extends Controller
{
    public function create(Request $request)
    {
        return view('counselors.signup.create');
    }
}
