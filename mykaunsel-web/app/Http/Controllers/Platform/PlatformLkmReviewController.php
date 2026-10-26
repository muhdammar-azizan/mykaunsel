<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\CounselorDecisionBlocked;
use App\Exceptions\LkmReviewAlreadyDecided;
use App\Http\Controllers\Controller;
use App\Models\LkmDirectorySnapshot;
use App\Models\LkmReviewRequest;
use App\Services\LkmReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform Admin → LKM Directory → Review Requests: counselors asking for their LKM record to be checked again, with evidence.
 *
 * The evidence has no public URL. "View evidence" hands out a signed link that lives two minutes and works only for the admin
 * who asked for it; every time the file is actually served it is written to the audit log (lkm.evidence_viewed).
 */
class PlatformLkmReviewController extends Controller
{
    /** Only these are ever served inline; anything else is refused. */
    private const MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public function index(Request $request): View|RedirectResponse
    {
        $view = $request->query('view') === 'resolved' ? 'resolved' : 'pending';

        $query = LkmReviewRequest::query()->with(['counselorProfile.user', 'reviewer']);
        $reviews = ($view === 'pending'
            ? $query->pending()->orderBy('created_at')->orderBy('id') // the longest wait first
            : $query->resolved()->orderByDesc('reviewed_at')->orderByDesc('id'))
            ->paginate((int) config('lkm.review.per_page'))
            ->withQueryString();

        // A page number past the end (e.g. after the last request was decided): go to the last real page.
        if ($reviews->isEmpty() && $reviews->currentPage() > 1) {
            return redirect()->route('platform.lkm.reviews', ['view' => $view, 'page' => $reviews->lastPage()]);
        }

        return view('platform.lkm.reviews', [
            'view' => $view,
            'reviews' => $reviews,
            'records' => LkmDirectorySnapshot::whereIn('kb_number', $reviews->getCollection()->map(fn ($r) => $r->counselorProfile->kb_number)->unique())->get()->keyBy('kb_number'),
            'counts' => ['pending' => LkmReviewRequest::pendingCount(), 'resolved' => LkmReviewRequest::query()->resolved()->count()],
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    /** Update the LKM record with the values the admin confirms. */
    public function approve(Request $request, LkmReviewRequest $review, LkmReviewService $service): RedirectResponse
    {
        $values = $request->validate([
            'pa_valid_until' => ['required', 'date_format:Y-m-d'],
            'pa_number' => ['nullable', 'string', 'max:20'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $service->approve($review, $request->user(), $values);
        } catch (LkmReviewAlreadyDecided $e) {
            return $this->alreadyDecided($e->review);
        } catch (CounselorDecisionBlocked $e) {
            return $this->back()->with('error', implode(' ', $e->reasons));
        } catch (ValidationException $e) {
            return $this->back()->with('error', collect($e->errors())->flatten()->first());
        }

        $name = $result['profile']->user->name;

        return $this->back()->with('status', "{$result['profile']->kb_number} updated: PA valid until ".Carbon::parse($result['valid_until'])->format('j M Y').". {$name} has been emailed."
            .($result['still_suspended'] ? ' They are still suspended: lifting that is a separate decision on the Counselors page.' : ''));
    }

    public function reject(Request $request, LkmReviewRequest $review, LkmReviewService $service): RedirectResponse
    {
        $data = $request->validateWithBag('reject-'.$review->id, [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'reason.required' => 'Write the reason — the counselor is told, and it is kept in the audit log.',
            'reason.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        try {
            $service->reject($review, $request->user(), $data['reason']);
        } catch (LkmReviewAlreadyDecided $e) {
            return $this->alreadyDecided($e->review);
        }

        return $this->back()->with('status', "Request rejected. {$review->counselorProfile->user->name} has been emailed the reason.");
    }

    // ---- evidence --------------------------------------------------------------------------

    /** The "View evidence" link: checks there is a file, then sends the browser on to a short-lived signed link. Nothing is logged yet. */
    public function evidenceOpen(Request $request, LkmReviewRequest $review): RedirectResponse
    {
        abort_if($review->evidence_purged_at !== null, 410, 'This evidence was removed under the retention rules.');
        abort_unless($review->hasEvidence() && $this->mimeFor($review) !== null, 404);

        return redirect()->to(URL::temporarySignedRoute('platform.lkm.reviews.evidence', now()->addMinutes((int) config('lkm.review.link_minutes')), [
            'review' => $review->id,
            'u' => $request->user()->id,
        ]));
    }

    /** Streams the file (signed URL only). */
    public function evidenceShow(Request $request, LkmReviewRequest $review, LkmReviewService $service): StreamedResponse
    {
        // The `signed` middleware has already checked the signature and expiry. The link is also tied to one admin.
        abort_unless((int) $request->query('u') === (int) $request->user()->id, 403);

        $disk = Storage::disk(config('lkm.review.disk'));
        $mime = $this->mimeFor($review);

        abort_unless($review->hasEvidence() && $mime !== null && $disk->exists($review->evidence_path), 404);

        // A browser's PDF viewer may ask for the file in pieces (Range requests): only the first, full request counts as a view.
        if (! $request->headers->has('Range')) {
            $service->recordEvidenceView($review, $request->user());
        }

        $filename = 'lkm-evidence-'.$review->id.'.'.pathinfo($review->evidence_path, PATHINFO_EXTENSION);

        return $disk->response($review->evidence_path, $filename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function mimeFor(LkmReviewRequest $review): ?string
    {
        return self::MIME[strtolower(pathinfo((string) $review->evidence_path, PATHINFO_EXTENSION))] ?? null;
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('platform.lkm.reviews');
    }

    private function alreadyDecided(LkmReviewRequest $review): RedirectResponse
    {
        $who = $review->reviewer?->name ?? 'another admin';

        return $this->back()->with('error', "This request was already {$review->status} by {$who}. Nothing was changed.");
    }
}
