<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counselor\StoreCounselorSignupRequest;
use App\Models\CounselorProfile;
use App\Models\LkmDirectorySnapshot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\CounselorKbAttemptRecorder;
use App\Services\LkmVerificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CounselorSignupController extends Controller
{
    public function create(Request $request)
    {
        return view('counselors.signup.create');
    }

    public function store(StoreCounselorSignupRequest $request, LkmVerificationService $lkmService, CounselorKbAttemptRecorder $kbAttempts)
    {
        $data = $request->validated();
        $kbNumber = Str::upper($data['kb_number']);
        $paNumber = Str::upper($data['pa_number']);

        $failureReason = $lkmService->verificationFailureReason($kbNumber, $paNumber, $data['name']);

        if ($failureReason !== null) {
            // Someone trying a KB number that already has an account: keep a record on the ORIGINAL account.
            // The person trying sees the same message as always — nothing about who owns the number.
            if ($lkmService->isAlreadyRegistered($kbNumber)) {
                $kbAttempts->record($kbNumber, $data['name'], $request->ip());
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['lkm' => $failureReason]);
        }

        $lkmRecord = LkmDirectorySnapshot::where('kb_number', $kbNumber)->firstOrFail();
        $platform = Organization::where('org_type', 'platform')->firstOrFail();

        try {
            $user = DB::transaction(function () use ($data, $kbNumber, $paNumber, $lkmRecord, $platform) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                CounselorProfile::create([
                    'user_id' => $user->id,
                    'kb_number' => $kbNumber,
                    'pa_number' => $paNumber,
                    'pa_valid_until' => $lkmRecord->pa_valid_until,
                    'verification_type' => VerificationType::PlatformVerified,
                    'verification_status' => VerificationStatus::Pending,
                ]);

                Membership::create([
                    'user_id' => $user->id,
                    'org_id' => $platform->id,
                    'role' => MembershipRole::Counselor,
                    'status' => MembershipStatus::Active,
                    'join_source' => JoinSource::Manual,
                    'joined_at' => now(),
                ]);

                Auth::login($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Two sign-ups with the same KB number or e-mail at the same moment: the database let only one through. Nothing was saved for this one.
            Auth::guard('web')->logout();

            if ($lkmService->isAlreadyRegistered($kbNumber)) {
                $kbAttempts->record($kbNumber, $data['name'], $request->ip());
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors($lkmService->isAlreadyRegistered($kbNumber)
                    ? ['lkm' => LkmVerificationService::ALREADY_REGISTERED_MESSAGE]
                    : ['email' => 'That e-mail address is already used. Sign in instead, or use another address.']);
        }

        // A new sign-in gets a new session id (the one from before the sign-up must never carry on as a signed-in session).
        $request->session()->regenerate();

        // The link that confirms the e-mail address, sent now that the account is saved. A failing e-mail never undoes the sign-up:
        // the person can ask for it again ("Resend") from the status page or the verify page.
        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Could not send the verification e-mail after a counselor sign-up.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return redirect()->route('counselors.signup.verified');
    }

    public function verified(Request $request)
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        $lkmRecord = LkmDirectorySnapshot::where('kb_number', $profile->kb_number)->first();

        return view('counselors.signup.verified', [
            'profile' => $profile,
            'lkmRecord' => $lkmRecord,
        ]);
    }
}
