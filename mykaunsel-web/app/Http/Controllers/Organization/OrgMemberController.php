<?php

namespace App\Http\Controllers\Organization;

use App\Enums\MembershipStatus;
use App\Exceptions\MembershipNotEndable;
use App\Exceptions\MembershipNotPending;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\Organization;
use App\Services\MembershipEnding;
use App\Services\MembershipService;
use App\Services\OrgMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * An organization admin's Members page: the people waiting for approval (they asked to join with a reference code), the reference codes, and the
 * people who belong. Always about the admin's CURRENT organization; an id from another organization is a 404.
 */
class OrgMemberController extends Controller
{
    /** Days a new code works, as offered on the form. */
    public const EXPIRY_CHOICES = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'never' => 'No expiry'];

    public function index(Request $request)
    {
        $organization = $this->organization($request);

        $clientRoles = array_map(fn ($role) => $role->value, MembershipService::CLIENT_ROLES);
        $members = fn (MembershipStatus $status) => Membership::query()
            ->where('org_id', $organization->getKey())->whereIn('role', $clientRoles)->where('status', $status->value)
            ->with('user:id,name,email');

        return view('organizations.dashboard.members', [
            'organization' => $organization,
            'waiting' => $members(MembershipStatus::PendingApproval)->orderBy('joined_at')->get(),
            'members' => $members(MembershipStatus::Active)->orderBy('joined_at')->get(),
            'codes' => OrgInviteCode::query()->where('org_id', $organization->getKey())->orderByDesc('id')->limit(50)->get(),
            'expiryChoices' => self::EXPIRY_CHOICES,
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function createCode(Request $request, OrgMemberService $service): RedirectResponse
    {
        $input = $request->validate([
            'expires' => ['required', Rule::in(array_keys(self::EXPIRY_CHOICES))],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], ['max_uses.integer' => 'Write the number of people as a whole number, or leave it empty.']);

        try {
            $code = $service->createCode(
                $this->organization($request),
                $request->user(),
                filled($input['max_uses'] ?? null) ? (int) $input['max_uses'] : null,
                $input['expires'] === 'never' ? null : Carbon::now()->addDays((int) $input['expires']),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Code '.$code->display().' is ready. Give it to the employees who should join.');
    }

    public function revokeCode(Request $request, int $id, OrgMemberService $service): RedirectResponse
    {
        $service->revokeCode($this->organization($request), $id, $request->user());

        return back()->with('status', 'The code is switched off. People already waiting stay on the list.');
    }

    public function approve(Request $request, int $id, OrgMemberService $service): RedirectResponse
    {
        return $this->answer(fn () => $service->approve($this->organization($request), $id, $request->user()), 'Approved. The person can now book your counselors, and has been told by e-mail.');
    }

    public function decline(Request $request, int $id, OrgMemberService $service): RedirectResponse
    {
        return $this->answer(fn () => $service->decline($this->organization($request), $id, $request->user()), 'Declined. The person has been told by e-mail.');
    }

    /** Takes a member out of the organization. Their later sessions with its counselors are cancelled, except the next few days. */
    public function remove(Request $request, int $id, MembershipEnding $ending): RedirectResponse
    {
        try {
            $done = $ending->remove($this->organization($request), $id, $request->user());
        } catch (MembershipNotEndable $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        return back()->with('status', 'Removed. The person has been told by e-mail'.($done['cancelled'] > 0 ? " and {$done['cancelled']} later ".($done['cancelled'] === 1 ? 'session was' : 'sessions were').' cancelled' : '').'.');
    }

    private function answer(callable $do, string $done): RedirectResponse
    {
        try {
            $do();
        } catch (MembershipNotPending $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        return back()->with('status', $done);
    }

    private function organization(Request $request): Organization
    {
        return Organization::query()->findOrFail($request->session()->get('current_org_id'));
    }
}
