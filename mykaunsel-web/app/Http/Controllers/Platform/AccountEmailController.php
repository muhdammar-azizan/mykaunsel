<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyNewEmailAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Email change flow: the old address stays active (users.email) until the new one is
 * confirmed through a signed link (users.pending_email holds the new one meanwhile).
 */
class AccountEmailController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validateWithBag('emailChange', [
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email'),
                Rule::notIn([$user->email]),
            ],
            'current_password' => ['required', 'current_password'],
        ], [
            'email.not_in' => 'That is already your current email.',
            'email.unique' => 'That email is already used by another account.',
            'current_password.current_password' => 'That password is not correct.',
        ]);

        $new = strtolower($data['email']);
        $minutes = (int) config('account.email_verification_minutes');

        $user->forceFill(['pending_email' => $new])->save();

        $url = URL::temporarySignedRoute(
            'platform.account.email.verify',
            now()->addMinutes($minutes),
            ['user' => $user->id, 'hash' => sha1($new)],
        );

        Notification::route('mail', $new)->notify(new VerifyNewEmailAddress($url, $minutes));
        $user->notify(new EmailChangeRequested($this->mask($new)));

        AuditLog::record('email_change_requested', $user, ['new_email_domain' => substr(strrchr($new, '@'), 1)]);

        return redirect()->route('platform.account')
            ->with('status', "Verification link sent to {$new}. Your current email stays active until you confirm it.");
    }

    public function verify(Request $request, int $user, string $hash): RedirectResponse
    {
        $account = $request->user();

        // The link is only valid for the signed-in owner of the request.
        abort_unless($account->id === $user, 403);

        if (! $account->pending_email || ! hash_equals(sha1($account->pending_email), $hash)) {
            return redirect()->route('platform.account')->withErrors(['email' => 'That confirmation link is no longer valid.'], 'emailChange');
        }

        // Re-check uniqueness: someone may have taken the address while it was pending.
        if (User::where('email', $account->pending_email)->whereKeyNot($account->id)->exists()) {
            $account->forceFill(['pending_email' => null])->save();

            return redirect()->route('platform.account')->withErrors(['email' => 'That email was taken in the meantime.'], 'emailChange');
        }

        $old = $account->email;
        $account->forceFill([
            'email' => $account->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        AuditLog::record('email_changed', $account, ['previous_email_domain' => substr(strrchr($old, '@'), 1)]);

        return redirect()->route('platform.account')->with('status', 'Your email address has been updated.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['pending_email' => null])->save();

        return redirect()->route('platform.account')->with('status', 'Email change cancelled.');
    }

    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email);

        return substr($local, 0, 2).str_repeat('*', max(strlen($local) - 2, 1)).'@'.$domain;
    }
}
