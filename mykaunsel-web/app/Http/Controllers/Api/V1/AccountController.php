<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AccountNotDeletable;
use App\Facades\Audit;
use App\Services\AccountDeletion;
use App\Http\Controllers\Controller;
use App\Services\ApiAuthService;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The signed-in person's own account in the app: profile, password and the phones signed in to it.
 *
 * Always about the signed-in person: there is no user id anywhere in these routes. Anything that proves who is
 * holding the phone (changing the password, signing another phone out) asks for the current password, and is
 * limited to a handful of tries a minute, because a stolen token must not become a way to guess the password.
 */
class AccountController extends Controller
{
    public function __construct(private ApiAuthService $auth) {}

    /** Name and time zone. The email address is changed on the web (it needs a confirmation step of its own). */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'timezone' => ['required', 'string', Rule::in(self::timezones())],
        ]);

        $user = $request->user();
        $user->forceFill(['name' => trim($data['name']), 'timezone' => $data['timezone']])->save();

        return response()->json(['user' => $this->auth->present($user->fresh())]);
    }

    /**
     * Agreeing to (or withdrawing) AI processing, which the chatbot needs. Every real change is recorded (`ai_consent_events`); asking for the state
     * the person already has changes nothing and records nothing. Withdrawing turns the chat off at once; the history stays readable.
     */
    public function updateAiConsent(Request $request): JsonResponse
    {
        $data = $request->validate(['consent' => ['required', 'boolean']]);

        $user = $request->user();
        $grant = (bool) $data['consent'];

        DB::transaction(function () use ($user, $grant) {
            $locked = \App\Models\User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (($locked->ai_consent_at !== null) === $grant) {
                return;
            }

            $locked->forceFill(['ai_consent_at' => $grant ? now() : null])->save();
            DB::table('ai_consent_events')->insert(['user_id' => $locked->getKey(), 'granted' => $grant, 'source' => 'app', 'created_at' => now()]);
        });

        return response()->json(['user' => $this->auth->present($user->fresh())]);
    }

    /** The e-mails the person can switch off (Account > Notifications in the app). E-mail only: there is no push and no in-app notice. */
    public function notificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['preferences' => collect(config('account.client_notifications'))
            ->map(fn (array $item, string $key) => ['key' => $key, 'label' => $item['label'], 'description' => $item['description'], 'enabled' => $user->wantsNotification($key)])
            ->values()->all()]);
    }

    /** Saves ONE choice. Only the keys in `account.client_notifications` may be set here (never the platform admins' own). */
    public function updateNotificationPreference(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(config('account.client_notifications')))],
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->notification_preferences ?? [];
        $preferences[$data['key']] = (bool) $data['enabled'];

        $user->forceFill(['notification_preferences' => $preferences])->save();

        return $this->notificationPreferences($request->setUserResolver(fn () => $user->fresh()));
    }

    /** New password: needs the current one; every OTHER phone (and web session) is signed out, this one stays. */
    /**
     * Deleting the account (docs/ACCOUNT_DELETION.md). Needs the current password and the word DELETE typed, because it can not be undone.
     * Counselors and administrators are refused (403 `staff_account`). On success every sign-in of the person is gone, this one included.
     */
    public function destroy(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'confirm' => ['required', 'in:DELETE'],
        ], [
            'current_password.current_password' => 'That password is not correct.',
            'confirm.in' => 'Type DELETE in capitals to confirm.',
        ]);

        try {
            $done = $deletion->delete($request->user());
        } catch (AccountNotDeletable $refused) {
            return $refused->toResponse();
        }

        return response()->json(['deleted' => true, 'cancelled' => $done['cancelled']])->header('Cache-Control', 'private, no-store');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ], [
            'current_password.current_password' => 'That password is not correct.',
            'password.different' => 'Choose a password you have not used just now.',
        ]);

        $user = $request->user();
        $current = $user->currentAccessToken();

        DB::transaction(function () use ($user, $data, $current) {
            // Saving a different password also ends the person's web sessions (the web checks the password on every request).
            $user->forceFill(['password' => $data['password']])->save();

            $others = $user->tokens()->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->id));
            $count = $others->count();
            $others->delete();

            Audit::log('account.password_changed', $user, ['source' => 'app', 'other_devices_signed_out' => $count]);
        });

        return response()->json(['message' => 'Password updated. Your other devices were signed out.']);
    }

    /** The phones signed in to this account (never anyone else's), this one first. */
    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->id : null;

        $devices = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'last_used_at', 'created_at'])
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'current' => $token->id === $currentId,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->sortByDesc('current')
            ->values();

        return response()->json(['data' => $devices]);
    }

    /** Sign ANOTHER phone out. This phone signs out with /auth/logout; somebody else's phone is simply "not found". */
    public function revokeDevice(Request $request, int $device): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'current_password:sanctum']], [
            'current_password.current_password' => 'That password is not correct.',
        ]);

        $user = $request->user();
        $current = $user->currentAccessToken();

        $token = $user->tokens()->whereKey($device)->first();

        abort_if($token === null, 404);

        if ($current instanceof PersonalAccessToken && $token->id === $current->id) {
            return response()->json(['message' => 'This is the phone you are using. Use "Sign out" for it.', 'code' => 'current_device'], 422);
        }

        DB::transaction(function () use ($token, $user) {
            $token->delete();

            Audit::log('account.other_sessions_revoked', $user, ['sessions' => 1, 'source' => 'app']);
        });

        return response()->json(['message' => 'That device was signed out.']);
    }

    /** Asia plus UTC, the same as the web account page (the platform serves Malaysia). */
    public static function timezones(): array
    {
        return array_merge(['UTC'], DateTimeZone::listIdentifiers(DateTimeZone::ASIA));
    }
}
