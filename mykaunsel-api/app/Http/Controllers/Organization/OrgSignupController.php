<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrgSignupController extends Controller
{
    public function create(Request $request)
    {
        return view('organizations.signup.create');
    }
}
