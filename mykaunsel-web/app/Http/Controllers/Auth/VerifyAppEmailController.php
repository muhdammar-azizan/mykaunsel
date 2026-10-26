<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;

/** Opens from the email sent to someone who signed up in the mobile app (signed link, no web sign-in needed). */
class VerifyAppEmailController extends Controller
{
    public function __invoke(string $id, string $hash): View
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return view('auth.email-verified-app');
    }
}
