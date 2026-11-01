<?php

namespace App\Http\Controllers\Organization;

use App\Enums\AccessModel;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\OrgCounselors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * An organization admin's Counselors page: who works for the organization, inviting a counselor, removing one, and the two settings about who may
 * be booked. Always about the admin's CURRENT organization; an id from another organization is a 404. The rules are in OrgCounselors.
 */
class OrgCounselorController extends Controller
{
    public function index(Request $request, OrgCounselors $counselors)
    {
        $organization = $this->organization($request);

        return view('organizations.dashboard.counselors', [
            'organization' => $organization,
            'counselors' => $counselors->counselors($organization),
            'invitations' => $counselors->invitations($organization),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function invite(Request $request, OrgCounselors $counselors): RedirectResponse
    {
        $input = $request->validate(['email' => ['required', 'email:rfc', 'max:255']], ['email.required' => 'Write the counselor\'s e-mail address.']);

        try {
            $counselors->invite($this->organization($request), $request->user(), $input['email']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        }

        return back()->with('status', 'Invitation sent. The counselor answers on their Dashboard, signed in with that address.');
    }

    public function revokeInvitation(Request $request, int $id, OrgCounselors $counselors): RedirectResponse
    {
        try {
            $counselors->revokeInvitation($this->organization($request), $id, $request->user());
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return back()->with('status', 'Invitation cancelled.');
    }

    public function remove(Request $request, int $id, OrgCounselors $counselors): RedirectResponse
    {
        try {
            $done = $counselors->remove($this->organization($request), $id, $request->user());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['counselor' => $e->getMessage()]);
        }

        return back()->with('status', $done['counselor'].' no longer works for your organization. '.($done['cancelled'] > 0 ? $done['cancelled'].' later '.($done['cancelled'] === 1 ? 'session was' : 'sessions were').' cancelled and the clients were told.' : 'There were no later sessions to cancel.'));
    }

    public function settings(Request $request, OrgCounselors $counselors): RedirectResponse
    {
        $input = $request->validate(['access_model' => ['required', Rule::in([AccessModel::Open->value, AccessModel::Closed->value])]]);

        $counselors->updateSettings($this->organization($request), $request->user(), AccessModel::from($input['access_model']), $request->boolean('counselors_may_practise_independently'));

        return back()->with('status', 'Settings saved.');
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->session()->get('current_org_id'));
    }
}
