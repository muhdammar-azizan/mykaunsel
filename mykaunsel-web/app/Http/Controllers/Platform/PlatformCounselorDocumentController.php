<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\CounselorDocument;
use App\Models\CounselorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Secure viewing of counselor documents (KB / PA certificates, ID).
 *
 * There is no public URL for these files. A Platform Admin who clicks "View" gets a signed link that
 *   - lives for two minutes,
 *   - is tied to their own account (someone else cannot reuse it),
 *   - and only works while they are signed in as a platform admin.
 * Every time a file is actually served it is written to the audit log (counselor.document_viewed).
 */
class PlatformCounselorDocumentController extends Controller
{
    /** Only these are ever served inline; anything else is refused. */
    private const MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public const LINK_MINUTES = 2;

    /** Hands out the short-lived link. Nothing is logged yet — nothing has been viewed. */
    public function link(Request $request, CounselorProfile $counselor, CounselorDocument $document): JsonResponse
    {
        abort_unless($document->counselor_profile_id === $counselor->id, 404);

        if (! $document->hasFile()) {
            return response()->json(['message' => $document->isPurged() ? 'This document was removed under the retention rules.' : 'No file was uploaded.'], 410);
        }

        $mime = $this->mimeFor($document);

        if ($mime === null) {
            return response()->json(['message' => 'This kind of file cannot be shown in the browser.'], 415);
        }

        return response()->json([
            'url' => URL::temporarySignedRoute('platform.counselor-documents.show', now()->addMinutes(self::LINK_MINUTES), [
                'document' => $document->id,
                'u' => $request->user()->id,
            ]),
            'mime' => $mime,
            'label' => $document->label(),
        ]);
    }

    /** Streams the file (signed URL only). */
    public function show(Request $request, CounselorDocument $document): StreamedResponse
    {
        // The `signed` middleware has already checked the signature and expiry. The link is also tied to one admin.
        abort_unless((int) $request->query('u') === (int) $request->user()->id, 403);

        $disk = Storage::disk(config('counselors.documents_disk'));
        $mime = $this->mimeFor($document);

        abort_unless($document->hasFile() && $mime !== null && $disk->exists($document->path), 404);

        // A browser's PDF viewer may ask for the file in pieces (Range requests): only the first, full request counts as a view.
        if (! $request->headers->has('Range')) {
            DB::transaction(fn () => Audit::log('counselor.document_viewed', $document->counselorProfile, [
                'document' => $document->label(),
                'document_id' => $document->id,
            ], $request->user()));
        }

        return $disk->response($document->path, $document->type.'-document.'.pathinfo($document->path, PATHINFO_EXTENSION), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$document->type.'-document.'.pathinfo($document->path, PATHINFO_EXTENSION).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    private function mimeFor(CounselorDocument $document): ?string
    {
        return self::MIME[strtolower(pathinfo((string) $document->path, PATHINFO_EXTENSION))] ?? null;
    }
}
