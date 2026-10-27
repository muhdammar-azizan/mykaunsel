<?php

namespace App\Http\Controllers\Platform;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Exceptions\AdminChangeBlocked;
use App\Http\Controllers\Controller;
use App\Models\AdminInvitation;
use App\Models\Membership;
use App\Models\User;
use App\Services\AdminInvitationService;
use App\Services\PlatformAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Platform Admin → Platform Admins: who has access to this area. Inviting (and sending again / revoking an invitation) and removing. */
class PlatformAdminController extends Controller
{
    private const TABS = ['active' => 'Active', 'invitations' => 'Invitations', 'removed' => 'Removed'];

    public function index(Request $request, PlatformAdminService $admins): View
    {
        $platform = $admins->platformOrganization();
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? $request->query('tab') : 'active';

        $base = Membership::query()
            ->where('org_id', $platform->id)
            ->where('role', MembershipRole::PlatformAdmin->value);

        $counts = [
            'active' => (clone $base)->where('status', MembershipStatus::Active->value)->count(),
            'invitations' => AdminInvitation::query()->live()->count(),
            'removed' => (clone $base)->where('status', '!=', MembershipStatus::Active->value)->count(),
        ];

        $invitations = $tab === 'invitations'
            ? AdminInvitation::query()->with('invitedBy:id,name')->orderByDesc('id')->paginate(20)->withQueryString()
            : null;

        $members = $tab === 'invitations' ? collect() : (clone $base)
            ->with('user:id,name,email,avatar_path,last_login_at,two_factor_secret,two_factor_confirmed_at')
            ->when($tab === 'active',
                fn ($q) => $q->where('status', MembershipStatus::Active->value),
                fn ($q) => $q->where('status', '!=', MembershipStatus::Active->value))
            ->orderBy($tab === 'active' ? 'joined_at' : 'offboarded_at', $tab === 'active' ? 'asc' : 'desc')
            ->orderBy('id')
            ->get();

        return view('platform.admins.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'members' => $members,
            'invitations' => $invitations,
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function store(Request $request, AdminInvitationService $invitations): RedirectResponse
    {
        $data = $request->validateWithBag('invite', [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255'],
        ], [
            'name.required' => 'Write their name.',
            'email.required' => 'Write their email address.',
            'email.email' => 'That does not look like an email address.',
        ]);

        try {
            $invitation = $invitations->invite($request->user(), $data['name'], $data['email']);
        } catch (AdminChangeBlocked $e) {
            return redirect()->route('platform.admins')->withInput()->with('error', implode(' ', $e->reasons));
        }

        return redirect()->route('platform.admins', ['tab' => 'invitations'])
            ->with('status', "An invitation was emailed to {$invitation->email}. It works for ".config('platform-admins.invitation.expires_hours').' hours, and nothing changes for them until they accept.');
    }

    public function resendInvitation(Request $request, AdminInvitation $invitation, AdminInvitationService $invitations): RedirectResponse
    {
        try {
            $invitations->resend($request->user(), $invitation);
        } catch (AdminChangeBlocked $e) {
            return redirect()->route('platform.admins', ['tab' => 'invitations'])->with('error', implode(' ', $e->reasons));
        }

        return redirect()->route('platform.admins', ['tab' => 'invitations'])->with('status', "A new link was emailed to {$invitation->email}. The old link no longer works.");
    }

    public function revokeInvitation(Request $request, AdminInvitation $invitation, AdminInvitationService $invitations): RedirectResponse
    {
        try {
            $invitations->revoke($request->user(), $invitation);
        } catch (AdminChangeBlocked $e) {
            return redirect()->route('platform.admins', ['tab' => 'invitations'])->with('error', implode(' ', $e->reasons));
        }

        return redirect()->route('platform.admins', ['tab' => 'invitations'])->with('status', "The invitation to {$invitation->email} was revoked. Its link no longer works.");
    }

    public function remove(Request $request, User $admin, PlatformAdminService $admins): RedirectResponse
    {
        $data = $request->validateWithBag('remove-'.$admin->id, [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write why their access is removed — it is kept in the audit log.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $admins->remove($request->user(), $admin, trim($data['reason']));
        } catch (AdminChangeBlocked $e) {
            return redirect()->route('platform.admins')->with('error', implode(' ', $e->reasons));
        }

        return redirect()->route('platform.admins', ['tab' => 'removed'])->with('status', "{$admin->name} no longer has Platform Admin access.");
    }
}
