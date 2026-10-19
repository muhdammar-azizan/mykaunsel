<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Where each role lands after login / context selection.
     */
    private const ROLE_HOME = [
        'platform_admin' => 'platform.dashboard',
        'org_admin' => 'org.dashboard',
        'counselor' => 'counselor.dashboard',
    ];

    public function index(Request $request)
    {
        $home = self::ROLE_HOME[$request->session()->get('current_role')] ?? null;

        if ($home) {
            return redirect()->route($home);
        }

        return view('dashboard');
    }
}
