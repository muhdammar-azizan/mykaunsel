<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AccountTypeController extends Controller
{
    public function index(Request $request, PlatformSettings $settings): View|Response
    {
        // Only the two kinds of account that sign up on the web are offered here.
        $open = [
            'organization' => $settings->registrationOpen('organization'),
            'counselor' => $settings->registrationOpen('counselor'),
        ];

        if (! in_array(true, $open, true)) {
            return response()->view('registration-closed', ['message' => $settings->registrationClosedMessage()], 403);
        }

        return view('auth.select-account-type', [
            'open' => $open,
            'closedMessages' => collect($open)->reject()->map(fn ($v, $role) => $settings->registrationClosedMessage($role))->all(),
        ]);
    }
}
