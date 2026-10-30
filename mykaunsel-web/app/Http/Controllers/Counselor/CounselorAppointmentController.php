<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Services\CounselorSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The counselor's own sessions: what is coming, what still needs marking, what is past; the meeting link of an online session; and marking a
 * finished session completed or no-show. The rules live in CounselorSessions.
 */
class CounselorAppointmentController extends Controller
{
    public function index(Request $request, CounselorSessions $sessions)
    {
        $counselor = $request->user();

        return view('counselors.dashboard.appointments', $sessions->open($counselor) + [
            'past' => $sessions->past($counselor, (int) $request->query('page', 1))->withPath(route('counselor.appointments')),
            'providers' => CounselorSessions::PROVIDERS,
        ]);
    }

    public function meeting(Request $request, string $publicId, CounselorSessions $sessions): RedirectResponse
    {
        $input = $request->validate([
            'meeting_provider' => ['nullable', Rule::in(CounselorSessions::PROVIDERS)],
            'meeting_url' => ['nullable', 'string', 'max:255', 'url:https'],
        ]);

        // A link needs a provider name; without one it is "Other".
        $url = trim((string) ($input['meeting_url'] ?? ''));

        try {
            $sessions->setMeeting($request->user(), $publicId, $input['meeting_provider'] ?? 'Other', $url === '' ? null : $url);
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        return back()->with('status', $url === '' ? __('Meeting link removed.') : __('Meeting link saved.'));
    }

    public function outcome(Request $request, string $publicId, CounselorSessions $sessions): RedirectResponse
    {
        $input = $request->validate(['outcome' => ['required', Rule::in([BookingStatus::Completed->value, BookingStatus::NoShow->value])]]);

        try {
            $sessions->mark($request->user(), $publicId, BookingStatus::from($input['outcome']));
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        return back()->with('status', __('Session marked.'));
    }

    private function refused(BookingRefused $refused): RedirectResponse
    {
        abort_if($refused->reason === 'booking_not_found', 404);

        return back()->withErrors(['session' => $refused->getMessage()]);
    }
}
