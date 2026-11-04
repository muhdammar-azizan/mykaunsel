<?php

namespace App\Http\Controllers\Counselor;

use App\Enums\BookingStatus;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Notifications\SessionCancelledByCounselor;
use App\Notifications\TimeRequestAnsweredClient;
use App\Services\BookingRequests;
use App\Services\CounselorPlanner;
use App\Services\CounselorRequests;
use App\Services\CounselorSessions;
use App\Services\NotificationCenter;
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
    public function index(Request $request, CounselorSessions $sessions, CounselorRequests $requests, CounselorPlanner $planner)
    {
        $counselor = $request->user();

        // The filters belong to the Past list: status, first and last day, and (only for someone who works for several organizations) which one.
        $contexts = collect($planner->contexts($counselor))->pluck('name', 'id')->all();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in([BookingStatus::Completed->value, BookingStatus::NoShow->value, BookingStatus::Cancelled->value])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'context' => ['nullable', 'integer', Rule::in(array_keys($contexts))],
        ]);

        $lists = $sessions->lists($counselor, (int) $request->query('up_page', 1), (int) $request->query('mark_page', 1));

        return view('counselors.dashboard.appointments', [
            'upcoming' => $lists['upcoming']->withPath(route('counselor.appointments'))->withQueryString(),
            'to_mark' => $lists['to_mark']->withPath(route('counselor.appointments'))->withQueryString(),
            'past' => $sessions->past($counselor, (int) $request->query('page', 1), $filters)->withPath(route('counselor.appointments'))->withQueryString(),
            'filters' => $filters,
            'contexts' => $contexts,
            'providers' => CounselorSessions::PROVIDERS,
            'requests' => $requests->pending($counselor),
        ]);
    }

    /** The people who asked for a time inside the weekly hours, waiting for this counselor's answer. */
    public function requests(Request $request, CounselorRequests $requests)
    {
        return view('counselors.dashboard.requests', ['requests' => $requests->pending($request->user())]);
    }

    public function show(Request $request, string $publicId, CounselorSessions $sessions)
    {
        try {
            $session = $sessions->find($request->user(), $publicId);
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        return view('counselors.dashboard.appointment', ['s' => $session, 'providers' => CounselorSessions::PROVIDERS]);
    }

    /** The counselor cancels one of their own sessions with a reason for the client. The client is told by e-mail and in the app, after the commit. */
    public function cancel(Request $request, string $publicId, CounselorSessions $sessions): RedirectResponse
    {
        $input = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']], ['reason.required' => __('Write a short reason for the client.')]);

        try {
            $done = $sessions->cancel($request->user(), $publicId, $input['reason'], $request->boolean('keep_closed'));
        } catch (BookingRefused $refused) {
            return $this->refused($refused);
        }

        if ($done !== null) {
            $counselor = $request->user();

            try {
                $done['client']->notify(new SessionCancelledByCounselor($counselor->name, $done['when'], $input['reason']));
            } catch (Throwable $e) {
                report($e);
            }

            app(NotificationCenter::class)->notify($done['client'], 'booking.cancelled', $done['booking'], $counselor->name);
        }

        return redirect()->route('counselor.appointments.show', $publicId)->with('status', __('Session cancelled. The client has been told.'));
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
