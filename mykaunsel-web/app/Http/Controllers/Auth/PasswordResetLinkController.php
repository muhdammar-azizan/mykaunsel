<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // The answer is the same whether or not an account has this e-mail address (and whether or not the last link was sent
        // a moment ago): this page must not tell anyone which addresses have an account.
        Password::sendResetLink($request->only('email'));

        return back()->withInput($request->only('email'))
            ->with('status', __('If an account uses that e-mail address, we have sent a link to reset the password. Check your inbox and your spam folder.'));
    }
}
