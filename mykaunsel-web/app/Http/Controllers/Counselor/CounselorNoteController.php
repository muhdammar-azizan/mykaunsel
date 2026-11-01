<?php

namespace App\Http\Controllers\Counselor;

use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Services\SessionNotes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The counselor's private session notes (docs/SESSION_NOTES.md). Always the signed-in counselor's own sessions; the rules are in SessionNotes.
 * The pages are never cached by the browser.
 */
class CounselorNoteController extends Controller
{
    public function index(Request $request, SessionNotes $notes)
    {
        return view('counselors.dashboard.notes', ['sessions' => $notes->sessions($request->user(), (int) $request->query('page', 1))->withPath(route('counselor.notes'))]);
    }

    public function show(Request $request, string $publicId, SessionNotes $notes)
    {
        try {
            $session = $notes->find($request->user(), $publicId);
        } catch (BookingRefused) {
            abort(404);
        }

        return response()->view('counselors.dashboard.note', ['s' => $session, 'max' => (int) config('session-notes.max_length')])
            ->header('Cache-Control', 'no-store, private');
    }

    public function save(Request $request, string $publicId, SessionNotes $notes): RedirectResponse
    {
        $input = $request->validate(
            ['body' => ['required', 'string', 'min:1', 'max:'.(int) config('session-notes.max_length')]],
            ['body.required' => __('Write the note, or use Delete note to remove it.'), 'body.max' => __('The note is too long.')],
        );

        try {
            $notes->save($request->user(), $publicId, $input['body']);
        } catch (BookingRefused) {
            abort(404);
        }

        return redirect()->route('counselor.notes.show', $publicId)->with('status', __('Note saved.'));
    }

    public function destroy(Request $request, string $publicId, SessionNotes $notes): RedirectResponse
    {
        try {
            $notes->delete($request->user(), $publicId);
        } catch (BookingRefused) {
            abort(404);
        }

        return redirect()->route('counselor.notes')->with('status', __('Note deleted.'));
    }
}
