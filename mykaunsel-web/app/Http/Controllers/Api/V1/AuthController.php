<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailForApp;
use App\Services\ApiAuthService;
use Illuminate\Http\JsonResponse;
use App\Notifications\WebSignupLink;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

/** The app's account endpoints: sign up, sign in (with the two-factor step), sign out, "who am I", email and password help. */
class AuthController extends Controller
{
    public function __construct(private ApiAuthService $auth) {}

    /** Create an individual account. No token yet: the email address has to be confirmed first. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // The sign-up form asks for it and the chat needs it (docs/CHAT.md). It can be withdrawn later in Account.
            'ai_consent' => ['accepted'],
        ], ['ai_consent.accepted' => 'You need to agree to AI processing to create an account.']);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->except('ai_consent')->all());

            $user->forceFill(['ai_consent_at' => now()])->save();
            DB::table('ai_consent_events')->insert(['user_id' => $user->getKey(), 'granted' => true, 'source' => 'signup', 'created_at' => now()]);

            return $user;
        });

        // Sent straight away (not through the "Registered" event, which would send the web version of this email).
        $user->notify(new VerifyEmailForApp);

        return response()->json([
            'message' => 'Account created. Check your email and confirm your address, then sign in.',
            'email' => $user->email,
        ], 201);
    }

    /**
     * "Email me the link to sign up on a computer" for an Organization or a Counselor (those accounts are made on the web). It answers the SAME
     * way whether or not the address is anyone's, and whether or not it was just sent to: this must not tell anyone which addresses exist, and
     * one address can only be sent a link a few times an hour. A closed kind of sign-up (Registration Control) is refused with the admin's words.
     */
    public function signupLink(Request $request, PlatformSettings $settings): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['organization', 'counselor'])],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if (! $settings->registrationOpen($data['type'])) {
            return response()->json(['message' => $settings->registrationClosedMessage($data['type'])], 403);
        }

        $key = 'signup-link|'.sha1($data['type'].'|'.mb_strtolower(trim($data['email'])));

        // The first few in an hour are sent; the rest are answered exactly the same and sent nowhere.
        if (RateLimiter::attempt($key, 3, fn () => true, 3600)) {
            Notification::route('mail', trim($data['email']))->notify(new WebSignupLink($data['type']));
        }

        return response()->json(['message' => 'If that address can receive email, the link is on its way.'], 202);
    }

    /** Password step. 200 = signed in. 202 = a two-factor code is needed next (send the challenge back with the code). */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $step = $this->auth->attempt($request, $data['email'], $data['password']);

        if (isset($step['challenge'])) {
            return response()->json([
                'message' => 'Enter the 6-digit code from your authenticator app.',
                'code' => 'two_factor_required',
                'challenge' => $step['challenge'],
                'expires_in' => 600,
            ], 202);
        }

        return $this->signedIn($request, $step['user'], $data['device_name']);
    }

    /** Second step for accounts with two-factor on. */
    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:32', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = $this->auth->completeTwoFactor($request, $data['challenge'], $data['code'] ?? null, $data['recovery_code'] ?? null);

        return $this->signedIn($request, $user, $data['device_name']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->auth->present($request->user())]);
    }

    /** Sign out of this device only. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** Sign out of every device. */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out of all devices.']);
    }

    /** Send the confirmation email again. Always the same answer, so it cannot be used to find out who has an account. */
    public function resendVerification(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'string', 'email']])['email'];

        $user = User::query()->where('email', strtolower(trim($email)))->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->notify(new VerifyEmailForApp);
        }

        return response()->json(['message' => 'If that address needs confirming, we have sent the email again.'], 202);
    }

    /** Email a password-reset link (the reset page itself is on the web). Always the same answer. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => ['required', 'string', 'email']])['email'];

        Password::sendResetLink(['email' => strtolower(trim($email))]);

        return response()->json(['message' => 'If that address has an account, we have emailed a link to reset the password.'], 202);
    }

    private function signedIn(Request $request, User $user, string $deviceName): JsonResponse
    {
        return response()->json([
            'token' => $this->auth->issueToken($request, $user, $deviceName),
            'token_type' => 'Bearer',
            'user' => $this->auth->present($user),
        ]);
    }
}
