<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\OrgCounselors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The counselor's own contexts: the organizations they work for (and the independent practice), when they joined, and leaving an organization.
 * Only the counselor's own memberships; someone else's is a 404. The rules are in OrgCounselors::leave.
 */
class CounselorOrganizationController extends Controller
{
    public function index(Request $request, OrgCounselors $counselors)
    {
        $user = $request->user();

        $memberships = Membership::query()
            ->where('user_id', $user->getKey())->where('role', MembershipRole::Counselor->value)
            ->with('organization:id,name,org_type,subscription_status')
            ->orderByRaw("case when status = 'active' then 0 else 1 end")->orderBy('id')->get()
            ->filter(fn (Membership $m) => $m->organization !== null);

        $active = $memberships->filter(fn (Membership $m) => $m->status === MembershipStatus::Active)->count();

        return view('counselors.dashboard.organizations', [
            'rows' => $memberships->map(fn (Membership $m) => [
                'id' => $m->id,
                'name' => $m->organization->org_type === OrgType::Platform ? __('Independent practice (MyKaunsel)') : $m->organization->name,
                'independent' => $m->organization->org_type === OrgType::Platform,
                'status' => $m->status->value,
                'organization_status' => $m->organization->subscription_status->value,
                'joined' => $m->joined_at?->format('j M Y'),
                'left' => $m->offboarded_at?->format('j M Y'),
                'can_leave' => $m->status === MembershipStatus::Active && $m->organization->org_type !== OrgType::Platform,
            ])->values()->all(),
            'onlyOne' => $active <= 1,
            'invitations' => $counselors->invitationsFor($user)->count(),
        ]);
    }

    public function leave(Request $request, int $id, OrgCounselors $counselors): RedirectResponse
    {
        $input = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], ['reason.required' => __('Write a short reason.')]);

        try {
            $done = $counselors->leave($request->user(), $id, trim($input['reason']));
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (RuntimeException $e) {
            return back()->withErrors(['organization' => $e->getMessage()]);
        }

        // If this was the organization the counselor was working in, move to one they still have, so the portal never points at one they left.
        $still = $request->user()->memberships()->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->orderBy('id')->first();

        if ((int) $request->session()->get('current_org_id') === (int) Membership::query()->whereKey($id)->value('org_id')) {
            if ($still !== null) {
                $request->session()->put('current_org_id', $still->org_id);
            } else {
                $request->session()->forget(['current_org_id', 'current_role']);

                return redirect()->route('dashboard')->with('status', __('You left the organization. You do not work for any organization now.'));
            }
        }

        return redirect()->route('counselor.organizations')->with('status', $done['cancelled'] > 0
            ? __('You left the organization. :count later session(s) with it were cancelled and the clients were told.', ['count' => $done['cancelled']])
            : __('You left the organization. No later session was affected.'));
    }
}
