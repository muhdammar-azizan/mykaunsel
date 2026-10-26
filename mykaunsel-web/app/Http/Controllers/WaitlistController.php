<?php

namespace App\Http\Controllers;

use App\Services\PlatformSettings;
use App\Services\WaitlistHasher;
use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * The public side of the waitlist for closed sign-ups (docs/WAITLIST.md): the form on the "sign-up closed" page, and the
 * opt-out link in the email.
 *
 * The form always gives the same answer for the same kind of request, whether the address is new, already listed, opted
 * out, or notified before, and for the same status code: it must not be possible to find out who is on the list.
 */
class WaitlistController extends Controller
{
    public function store(Request $request, string $type, WaitlistService $waitlist, PlatformSettings $settings): RedirectResponse|Response
    {
        abort_unless($waitlist->validType($type) && $waitlist->enabled(), 404);

        $page = $this->signUpPage($type);

        $validator = Validator::make($request->all(), ['email' => ['required', 'string', 'email', 'max:254']], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'That email address is too long.',
        ]);

        if ($validator->fails()) {
            return redirect($page)->withErrors($validator, 'waitlist')->withInput($request->only('email'));
        }

        // Already open: nobody needs a waitlist, there is a real sign-up form. Nothing is stored.
        if ($settings->registrationOpen($type)) {
            return response()->view('waitlist.open', ['signUp' => $page, 'word' => config("platform-settings.registration_roles.{$type}.word")])
                ->header('Cache-Control', 'no-store, private');
        }

        $email = (string) $request->input('email');

        if (! $waitlist->allowAttempt($type, $email, (string) $request->ip())) {
            return redirect($page)->with('waitlist_error', 'Too many attempts. Please try again later.');
        }

        // A bot filled the hidden field: it gets the same thank-you, and nothing is stored.
        if (blank($request->input('company_website'))) {
            try {
                $waitlist->signUp($type, $email);
            } catch (Throwable $e) {
                report($e); // never let a failure show a different answer
            }
        }

        $word = config("platform-settings.registration_roles.{$type}.word");

        return redirect($page)->with('waitlist_status', "Thank you. If this address can be added, we will email you once when {$word} sign-up reopens.");
    }

    /** "Stop these emails?" with a button. A page you only look at changes nothing, so mail scanners that open links cannot opt anyone out. */
    public function confirm(string $type, string $hash, string $signature, WaitlistHasher $hasher): View
    {
        abort_unless($hasher->valid($type, $hash, $signature), 404);

        return view('waitlist.unsubscribe', ['action' => route('waitlist.unsubscribe.store', compact('type', 'hash', 'signature')), 'word' => config("platform-settings.registration_roles.{$type}.word")]);
    }

    /** The opt-out itself: from the button above, or one-click from the mail program (List-Unsubscribe-Post). Idempotent. */
    public function optOut(string $type, string $hash, string $signature, WaitlistHasher $hasher, WaitlistService $waitlist): View
    {
        abort_unless($hasher->valid($type, $hash, $signature), 404);

        $waitlist->optOut($type, $hash);

        return view('waitlist.unsubscribed', ['word' => config("platform-settings.registration_roles.{$type}.word")]);
    }

    private function signUpPage(string $type): string
    {
        return $type === 'counselor' ? route('counselors.signup.create') : route('organizations.signup.create');
    }
}
