<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Notifications\TimeRequestAnsweredClient;
use App\Services\BookingRequests;
use App\Services\CounselorRequests;
use App\Services\CounselorSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The counselor's own sessions: what is coming, what still needs marking, what is past; the meeting link of an online session; and marking a
 * finished session completed or no-show. The rules live in CounselorSessions.
 */
class CounselorAppointmentController extends Controller
{
    public function index(Request $request, CounselorSessions $sessions, CounselorRequests $requests)
    {
        $counselor = $request->user();

        return view('counselors.dashboard.appointments', $sessions->open($counselor) + [
            'past' => $sessions->past($counselor, (int) $request->query('page', 1))->withPath(route('counselor.appointments')),
            'providers' => CounselorSessions::PROVIDERS,
            'requests' => $requests->pending($counselor),
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

    /** Approving books the session (see CounselorRequests). The client is told by e-mail, after the commit, and never able to undo it. */
    public function approveRequest(Request $request, string $publicId, CounselorRequests $requests, BookingRequests $asked): RedirectResponse
    {
        try {
            $done = $requests->approve($request->user(), $publicId);
        } catch (BookingRefused $refused) {
            return $this->refused($refused, 'request_not_found');
        }

        if ($done['changed']) {
            $this->tellClient($done['request'], 'approved', null, $asked);
        }

        return back()->with('status', __('Request approved. The session is booked.'));
    }

    public function declineRequest(Request $request, string $publicId, CounselorRequests $requests, BookingRequests $asked): RedirectResponse
    {
        $input = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        try {
            $done = $requests->decline($request->user(), $publicId, $input['reason'] ?? null);
        } catch (BookingRefused $refused) {
            return $this->refused($refused, 'request_not_found');
        }

        if ($done['changed']) {
            $this->tellClient($done['request'], 'declined', $done['request']->response_reason, $asked);
        }

        return back()->with('status', __('Request declined.'));
    }

    private function tellClient($request, string $outcome, ?string $reason, BookingRequests $asked): void
    {
        try {
            $start = $asked->startOf($request);

            app(\App\Services\NotificationCenter::class)->notify($request->user, 'request.'.$outcome, $request, $asked->present($request)['counselor']['display_name'], (string) $request->responded_at?->getTimestamp());

            $request->user->notify(new TimeRequestAnsweredClient([
                'outcome' => $outcome,
                'counselor' => $asked->present($request)['counselor']['display_name'],
                'date' => $start->format('l, j F Y'),
                'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
                'minutes' => (int) $request->duration_minutes,
                'mode' => ($request->session_mode ?? \App\Enums\SessionMode::Online)->value,
                'reason' => $reason,
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function refused(BookingRefused $refused, string $notFound = 'booking_not_found'): RedirectResponse
    {
        abort_if($refused->reason === $notFound, 404);

        return back()->withErrors(['session' => $refused->getMessage()]);
    }
}
