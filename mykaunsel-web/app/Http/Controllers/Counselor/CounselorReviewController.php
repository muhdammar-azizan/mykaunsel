<?php

namespace App\Http\Controllers\Counselor;

use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The counselor's reviews (docs/REVIEWS.md): read them without the client's name, reply once, flag an unfair one. The rules are in ReviewService. */
class CounselorReviewController extends Controller
{
    public function index(Request $request, ReviewService $reviews)
    {
        return response()->view('counselors.dashboard.reviews', [
            'summary' => $reviews->summary($request->user()),
            'reviews' => $reviews->forCounselor($request->user(), (int) $request->query('page', 1))->withPath(route('counselor.reviews')),
            'flagReasons' => config('reviews.flag_reasons'),
            'replyMax' => (int) config('reviews.reply_max'),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function reply(Request $request, string $publicId, ReviewService $reviews): RedirectResponse
    {
        $input = $request->validate(['reply' => ['required', 'string', 'min:2', 'max:'.(int) config('reviews.reply_max')]], ['reply.required' => __('Write your reply.')]);

        try {
            $reviews->reply($request->user(), $publicId, $input['reply']);
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        return back()->with('status', __('Your reply is published. It can not be changed.'));
    }

    public function flag(Request $request, string $publicId, ReviewService $reviews): RedirectResponse
    {
        $input = $request->validate(['reason' => ['required', Rule::in(array_keys(config('reviews.flag_reasons')))]], ['reason.required' => __('Choose why.')]);

        try {
            $reviews->flag($request->user(), $publicId, $input['reason']);
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        return back()->with('status', __('Sent to MyKaunsel. The team looks at both sides. The review stays until they decide.'));
    }

    private function refused(BookingRefused $refused): RedirectResponse
    {
        abort_if($refused->reason === 'review_not_found', 404);

        return back()->withErrors(['review' => $refused->getMessage()]);
    }
}
