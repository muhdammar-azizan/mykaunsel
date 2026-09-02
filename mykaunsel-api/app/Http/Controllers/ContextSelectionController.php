<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\Request;

class ContextSelectionController extends Controller
{
    public function index(Request $request)
    {
        $memberships = $request->user()->memberships()->with('organization')->get();

        if ($memberships->count() === 1) {
            return $this->selectContext($request, $memberships->first());
        }

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

    private function selectContext(Request $request, Membership $membership)
    {
        $request->session()->put('current_org_id', $membership->org_id);
        $request->session()->put('current_role', $membership->role->value);

        return redirect()->route('dashboard');
    }
}
