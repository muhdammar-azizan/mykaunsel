<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FeedController extends Controller
{
    public function index(Request $request)
    {
        return view('feed.index');
    }
}
