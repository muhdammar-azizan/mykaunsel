<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counselor\StoreCounselorDocumentsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $disk = config('counselors.documents_disk');
        $updates = [];
        $replaced = [];

        foreach (self::DOCUMENT_FIELDS as $inputKey => $column) {
            if ($request->hasFile($inputKey)) {
                // Private disk, random file name. Nothing here is ever reachable by a public URL.
                $updates[$column] = $request->file($inputKey)->store('', $disk);

                if (filled($profile->{$column})) {
                    $replaced[] = $profile->{$column};
                }
            }
        }

        if ($updates !== [] && $profile->verification_status === VerificationStatus::Rejected) {
            $updates['verification_status'] = VerificationStatus::Pending;
            $updates['rejection_reason'] = null;
        }

        if ($updates !== []) {
            $profile->update($updates);

            // The new copies are saved; drop the files they replace.
            Storage::disk($disk)->delete($replaced);
        }

        return redirect()->route('counselors.signup.waiting-approval');
    }
}
