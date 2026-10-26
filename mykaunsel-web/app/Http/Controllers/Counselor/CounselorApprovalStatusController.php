<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CounselorApprovalStatusController extends Controller
{
    public function show(Request $request): View
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        // All three documents (KB certificate, PA certificate, ID) are on file.
        $documentsSubmitted = $profile->documents()->count() === count(\App\Models\CounselorDocument::TYPES);

        return view('counselors.signup.waiting-approval', [
            'profile' => $profile,
            'status' => $profile->verification_status,
            'documentsSubmitted' => $documentsSubmitted,
        ]);
    }
}
