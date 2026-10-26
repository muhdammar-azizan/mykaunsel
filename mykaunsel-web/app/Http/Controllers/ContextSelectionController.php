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

        // The role in the session decides what the user may open (including the Platform Admin area), so it must
        // be one the user really has in that organization, never just something sent by the browser.
        $owns = $request->user()->memberships()
            ->where('org_id', $validated['org_id'])
            ->where('role', $validated['role'])
            ->exists();

        abort_unless($owns, 403);

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
