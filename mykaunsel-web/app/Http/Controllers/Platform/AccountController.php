<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
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
        $changed = [];

        // One transaction with its audit entry: which fields changed, never the values.
        DB::transaction(function () use ($user, $data, &$changed) {
            $user->fill($data);
            $changed = array_keys($user->getDirty());
            $user->save();

            if ($changed !== []) {
                Audit::log('account.profile_updated', $user, ['fields' => $changed]);
            }
        });

        // A counselor's name was checked against the LKM record when they applied. Changing it is allowed, but the platform admins hear when it
        // no longer matches that record (after the commit; a failure never undoes the change).
        if (in_array('name', $changed, true)) {
            $this->tellAdminsIfNameDiffers($user);
        }

        return redirect()->to(\App\Support\AccountArea::route('account'))->with('status', 'Profile saved.');
    }

    private function tellAdminsIfNameDiffers(\App\Models\User $user): void
    {
        try {
            $profile = $user->counselorProfile;

            if ($profile === null) {
                return;
            }

            $comparison = app(\App\Services\LkmVerificationService::class)->compare($profile->load('user'));
            $nameRow = collect($comparison->fields)->firstWhere('label', 'Name');

            // Only when a record exists and the name no longer matches it (no record at all: nothing to compare with).
            if ($comparison->record !== null && ($nameRow['state'] ?? null) === 'mismatch') {
                app(\App\Services\NotificationCenter::class)->publish('counselor.name_changed', $profile, $user->name, (string) now()->getTimestamp(), $user);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Timezones offered in the select: Asia + UTC (the platform serves Malaysia). */
    public static function timezones(): array
    {
        return array_merge(['UTC'], DateTimeZone::listIdentifiers(DateTimeZone::ASIA));
    }
}
