<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContextSelectionController extends Controller
{
    public function index(Request $request)
    {
        $memberships = $request->user()->memberships()->with('organization')->get();

        return view('auth.select-context', compact('memberships'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'org_id' => ['required', 'integer'],
            'role' => ['required', 'string'],
        ]);

        $request->session()->put('current_org_id', $validated['org_id']);
        $request->session()->put('current_role', $validated['role']);

        return redirect()->route('dashboard');
    }
}
