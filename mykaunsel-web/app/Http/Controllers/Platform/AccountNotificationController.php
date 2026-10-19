<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountNotificationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $preferences = collect(config('account.notifications'))
            ->map(fn ($item, $key) => $item + ['key' => $key, 'enabled' => $user->wantsNotification($key)])
            ->values();

        return view('platform.account.notifications', ['user' => $user, 'preferences' => $preferences]);
    }

    /**
     * Save ONE toggle (auto-save). Only the keys the user explicitly changed are stored,
     * so defaults in config/account.php keep applying to everything else.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(array_keys(config('account.notifications')))],
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $preferences = $user->notification_preferences ?? [];
        $preferences[$data['key']] = (bool) $data['enabled'];

        $user->forceFill(['notification_preferences' => $preferences])->save();

        if ($request->expectsJson()) {
            return response()->json(['saved' => true, 'key' => $data['key'], 'enabled' => (bool) $data['enabled']]);
        }

        return redirect()->route('platform.account.notifications')->with('status', 'Preference saved.');
    }
}
