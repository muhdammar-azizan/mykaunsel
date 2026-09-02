<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counselor\StoreCounselorDocumentsRequest;
use Illuminate\Http\Request;

class CounselorDocumentController extends Controller
{
    private const DOCUMENT_FIELDS = [
        'cert' => 'cert_document_path',
        'pa' => 'pa_document_path',
        'ic' => 'ic_document_path',
    ];

    public function create(Request $request)
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        return view('counselors.signup.documents', ['profile' => $profile]);
    }

    public function store(StoreCounselorDocumentsRequest $request)
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        $updates = [];

        foreach (self::DOCUMENT_FIELDS as $inputKey => $column) {
            if ($request->hasFile($inputKey)) {
                $updates[$column] = $request->file($inputKey)->store('counselor-documents', 'public');
            }
        }

        if ($updates !== [] && $profile->verification_status === VerificationStatus::Rejected) {
            $updates['verification_status'] = VerificationStatus::Pending;
            $updates['rejection_reason'] = null;
        }

        if ($updates !== []) {
            $profile->update($updates);
        }

        return redirect()->route('counselors.signup.waiting-approval');
    }
}
