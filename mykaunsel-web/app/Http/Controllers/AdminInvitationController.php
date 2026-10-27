<?php

namespace App\Http\Controllers;

use App\Exceptions\AdminChangeBlocked;
use App\Exceptions\InvitationPasswordRejected;
use App\Exceptions\InvitationUnavailable;
use App\Models\AdminInvitation;
use App\Services\AdminInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * The page behind the link in a Platform Admin invitation (see AdminInvitationService).
 *
 * The link is signed AND carries a random token. Anything wrong with it (bad signature, unknown, used, replaced, revoked,
 * expired, sender removed) shows the same "no longer valid" page, so the page cannot be used to learn which it was. Pages
 * are not cached, not indexed, and do not send the address on as a referrer.
 */
class AdminInvitationController extends Controller
{
    public function show(Request $request, string $token, AdminInvitationService $invitations): BaseResponse
    {
        $invitation = $this->usable($request, $token, $invitations);

        if ($invitation === null) {
            return $this->gone();
        }

        return $this->page('auth.admin-invitation', [
            'invitation' => $invitation,
            'mode' => $invitations->modeOf($invitation),
            'mustSignIn' => $invitations->mustSignInFirst($invitation, $request->user()),
            'locked' => $this->locked($invitation),
            'sender' => $invitation->invitedBy?->name,
            'action' => $request->fullUrl(),
        ]);
    }

    public function accept(Request $request, string $token, AdminInvitationService $invitations): BaseResponse|RedirectResponse
    {
        $invitation = $this->usable($request, $token, $invitations);

        if ($invitation === null) {
            return $this->gone();
        }

        if ($this->locked($invitation)) {
            return back()->withErrors(['password' => 'Too many wrong passwords. Try again in a few minutes.']);
        }

        $mode = $invitations->modeOf($invitation);

        $request->validate([
            'password' => $mode === 'verified'
                ? ['required', 'string']
                : ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'password.required' => $mode === 'verified' ? 'Type your password to confirm it is you.' : 'Choose a password.',
        ]);

        try {
            $invitations->accept($token, (string) $request->input('password'), $request->user());
        } catch (InvitationUnavailable) {
            return $this->gone();
        } catch (InvitationPasswordRejected) {
            RateLimiter::hit($this->limiterKey($invitation), (int) config('platform-admins.invitation.accept_lock_minutes') * 60);

            return back()->withErrors(['password' => 'That password is not correct.']);
        } catch (AdminChangeBlocked $e) {
            return back()->withErrors(['password' => implode(' ', $e->reasons)]);
        }

        RateLimiter::clear($this->limiterKey($invitation));

        return redirect()->route('login')->with('status', 'You are now a Platform Admin. Sign in to start.');
    }

    private function usable(Request $request, string $token, AdminInvitationService $invitations): ?AdminInvitation
    {
        return $request->hasValidSignature() ? $invitations->findUsable($token) : null;
    }

    private function locked(AdminInvitation $invitation): bool
    {
        return RateLimiter::tooManyAttempts($this->limiterKey($invitation), (int) config('platform-admins.invitation.accept_attempts'));
    }

    private function limiterKey(AdminInvitation $invitation): string
    {
        return 'admin-invitation-accept:'.$invitation->getKey();
    }

    private function gone(): Response
    {
        return $this->page('auth.admin-invitation-invalid', [], 410);
    }

    private function page(string $view, array $data, int $status = 200): Response
    {
        return response()->view($view, $data, $status)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
