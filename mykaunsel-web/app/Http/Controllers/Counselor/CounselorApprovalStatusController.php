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

        $documentsSubmitted = filled($profile->cert_document_path)
            && filled($profile->pa_document_path)
            && filled($profile->ic_document_path);

        return view('counselors.signup.waiting-approval', [
            'profile' => $profile,
            'status' => $profile->verification_status,
            'documentsSubmitted' => $documentsSubmitted,
        ]);
    }
}
