<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** An organization admin's Settings page. Always the admin's CURRENT organization. The rules are in OrgSettings. */
class OrgSettingController extends Controller
{
    public function index(Request $request, OrgSettings $settings)
    {
        return view('organizations.dashboard.settings', $settings->for($this->organization($request)));
    }

    public function update(Request $request, OrgSettings $settings): RedirectResponse
    {
        $organization = $this->organization($request);
        $allowed = $settings->for($organization)['deadline_choices'];

        $input = $request->validate(['cancellation_deadline_hours' => ['required', 'integer', Rule::in($allowed)]]);

        $changed = $settings->update($organization, $request->user(), (int) $input['cancellation_deadline_hours']);

        return back()->with('status', $changed ? 'Settings saved.' : 'Nothing changed.');
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->session()->get('current_org_id'));
    }
}
