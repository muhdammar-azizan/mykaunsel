<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\VerificationStatus;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counselor\StoreCounselorDocumentsRequest;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use App\Services\PlatformAdminNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CounselorDocumentController extends Controller
{
    /** Form field => document type */
    private const DOCUMENT_FIELDS = [
        'cert' => 'cert',
        'pa' => 'pa',
        'ic' => 'ic',
    ];

    public function create(Request $request)
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        return view('counselors.signup.documents', ['profile' => $profile]);
    }

    public function store(StoreCounselorDocumentsRequest $request, PlatformAdminNotifier $notifier)
    {
        $profile = $request->user()->counselorProfile;
        abort_unless($profile, 404);

        $disk = config('counselors.documents_disk');
        $replaced = [];
        $notify = null; // null = no email to admins; false = first submission; true = resubmission

        DB::transaction(function () use ($request, $profile, $disk, &$replaced, &$notify) {
            // Lock the row: the status and attempt count we act on are the current ones.
            $locked = CounselorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $changed = false;

            foreach (self::DOCUMENT_FIELDS as $inputKey => $type) {
                if (! $request->hasFile($inputKey)) {
                    continue;
                }

                $file = $request->file($inputKey);
                $existing = $locked->documents()->where('type', $type)->first();

                if (filled($existing?->path)) {
                    $replaced[] = $existing->path;
                }

                // Private disk, random file name. Nothing here is ever reachable by a public URL.
                // A fresh upload starts un-reviewed again (any "needs replacement" mark is cleared).
                $locked->documents()->updateOrCreate(['type' => $type], [
                    'path' => $file->store('', $disk),
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'size_bytes' => $file->getSize(),
                    'status' => CounselorDocument::STATUS_UNREVIEWED,
                    'note' => null,
                    'uploaded_at' => now(),
                    'purged_at' => null,
                ]);
                $changed = true;
            }

            if (! $changed) {
                return;
            }

            $complete = $locked->documents()->whereNotNull('path')->count() === count(CounselorDocument::TYPES);

            if ($locked->verification_status === VerificationStatus::Rejected) {
                // Sending documents again after a rejection is a resubmission: one more attempt, back to the review queue.
                $attempt = $locked->submission_count + 1;

                $locked->transitionTo(VerificationStatus::Pending, [
                    'rejection_reason' => null,
                    'submitted_at' => now(),
                    'submission_count' => $attempt,
                ], VerificationStatus::Rejected);

                Audit::log('counselor.resubmitted', $locked, [
                    'attempt' => $attempt,
                    'changes' => ['status' => ['old' => 'rejected', 'new' => 'pending']],
                ], $request->user());

                $notify = true;
            } elseif ($complete && $locked->submitted_at === null) {
                // The application counts as submitted once all three documents are in — admins hear about it once.
                $locked->update(['submitted_at' => now()]);
                $notify = false;
            }
        });

        // The new copies are saved; drop the files they replace.
        if ($replaced !== []) {
            Storage::disk($disk)->delete($replaced);
        }

        if ($notify !== null) {
            $profile->refresh();
            DB::afterCommit(fn () => $notifier->newCounselorApplication($profile, resubmitted: $notify));
        }

        return redirect()->route('counselors.signup.waiting-approval');
    }
}
