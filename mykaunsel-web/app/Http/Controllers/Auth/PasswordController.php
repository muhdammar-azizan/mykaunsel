<?php

namespace App\Http\Controllers\Auth;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password (students, staff, counselors, org admins).
     * Platform Admin accounts are blocked here by the legacy.account middleware.
     */
    public function update(Request $request, SessionManager $sessions): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $validated['password']])->save();

        // Same protection as the Account page: every other device is signed out.
        Auth::logoutOtherDevices($validated['password']);
        $sessions->forgetOthers($request);

        Audit::log('account.password_changed', $user);

        return back()->with('status', 'password-updated');
    }
}
