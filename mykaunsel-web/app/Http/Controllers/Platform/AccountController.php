<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    /** Profile tab. Always operates on the signed-in user — no user id in the URL. */
    public function show(Request $request): View
    {
        return view('platform.account.profile', [
            'user' => $request->user(),
            'timezones' => self::timezones(),
            'roleLabel' => Str::headline($request->session()->get('current_role', 'platform_admin')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', Rule::in(self::timezones())],
        ]);

        $user = $request->user();
        $user->fill($data)->save();

        return redirect()->route('platform.account')->with('status', 'Profile saved.');
    }

    /** Timezones offered in the select: Asia + UTC (the platform serves Malaysia). */
    public static function timezones(): array
    {
        return array_merge(['UTC'], DateTimeZone::listIdentifiers(DateTimeZone::ASIA));
    }
}
