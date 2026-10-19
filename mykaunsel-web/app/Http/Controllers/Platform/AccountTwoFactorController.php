<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Turn two-factor authentication on/off for the signed-in admin.
 * Every sensitive step needs the current password; the code is only checked at confirm time.
 */
class AccountTwoFactorController extends Controller
{
    public function __construct(private TwoFactorAuthenticator $twoFactor) {}

    /** Step 1: password → generate a secret and show the QR (not enabled until a code is confirmed). */
    public function begin(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($failure = $this->passwordFailure($request, 'enable')) {
            return $failure;
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->done('Two-factor authentication is already on.');
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        return $this->done();
    }

    /** Step 2: a valid code from the authenticator turns it on and shows recovery codes once. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasPendingTwoFactor(), 404);

        $request->validateWithBag('twoFactorCode', ['code' => ['required', 'string']]);

        if (! $this->twoFactor->verify($user, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is not correct. Check the time on your phone and try the newest code.'], 'twoFactorCode');
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $codes = $this->twoFactor->issueRecoveryCodes($user);

        AuditLog::record('two_factor_enabled', $user);

        return $this->done('Two-factor authentication is on.')->with('recovery_codes', $codes);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasPendingTwoFactor()) {
            $this->clear($user);
        }

        return $this->done('Setup cancelled.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($failure = $this->passwordFailure($request, 'regenerate')) {
            return $failure;
        }

        abort_unless($user->hasTwoFactorEnabled(), 404);

        $codes = $this->twoFactor->issueRecoveryCodes($user);

        AuditLog::record('recovery_codes_regenerated', $user);

        return $this->done('New recovery codes generated. The old ones no longer work.')->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($failure = $this->passwordFailure($request, 'disable')) {
            return $failure;
        }

        if ($user->hasTwoFactorEnabled() || $user->hasPendingTwoFactor()) {
            $wasEnabled = $user->hasTwoFactorEnabled();
            $this->clear($user);

            if ($wasEnabled) {
                AuditLog::record('two_factor_disabled', $user);
            }
        }

        return $this->done('Two-factor authentication is off.');
    }

    /** Returns a redirect (with the error) when the password is missing/wrong, otherwise null. */
    private function passwordFailure(Request $request, string $modal): ?RedirectResponse
    {
        $request->validateWithBag('twoFactor', ['current_password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('current_password'), $request->user()->password)) {
            return back()
                ->withErrors(['current_password' => 'That password is not correct.'], 'twoFactor')
                ->with('two_factor_modal', $modal);
        }

        return null;
    }

    private function clear($user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();
    }

    private function done(?string $status = null): RedirectResponse
    {
        $redirect = redirect()->to(route('platform.account.security').'#two-factor');

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
