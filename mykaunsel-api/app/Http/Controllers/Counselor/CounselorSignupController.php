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
use App\Services\LkmVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CounselorSignupController extends Controller
{
    public function create(Request $request)
    {
        return view('counselors.signup.create');
    }

    public function store(StoreCounselorSignupRequest $request, LkmVerificationService $lkmService)
    {
        $data = $request->validated();
        $kbNumber = Str::upper($data['kb_number']);
        $paNumber = Str::upper($data['pa_number']);

        $failureReason = $lkmService->verificationFailureReason($kbNumber, $paNumber, $data['name']);

        if ($failureReason !== null) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['lkm' => $failureReason]);
        }

        $lkmRecord = LkmDirectorySnapshot::where('kb_number', $kbNumber)->firstOrFail();
        $platform = Organization::where('org_type', 'platform')->firstOrFail();

        DB::transaction(function () use ($data, $kbNumber, $paNumber, $lkmRecord, $platform) {
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
        });

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
