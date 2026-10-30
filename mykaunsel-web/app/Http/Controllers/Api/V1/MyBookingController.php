<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Notifications\BookingCancelledCounselor;
use App\Notifications\BookingRescheduledCounselor;
use App\Services\AvailabilityService;
use App\Services\BookingCancellation;
use App\Services\BookingRescheduling;
use App\Services\MyBookings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * GET /api/v1/bookings?scope=upcoming|past&page=   the signed-in person's OWN bookings (docs/AVAILABILITY.md, "My bookings").
 * GET /api/v1/bookings/{id}                        one of them, with the meeting link (inside its window) or the full address.
 *
 * Read only. Another person's booking and one that does not exist are the same `404 booking_not_found`.
 */
class MyBookingController extends Controller
{
    public function index(Request $request, MyBookings $bookings): JsonResponse
    {
        $query = $request->validate([
            'scope' => ['required', Rule::in(['upcoming', 'past'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $user = $request->user();

        $body = $query['scope'] === 'upcoming'
            ? ['data' => $bookings->upcoming($user), 'page' => 1, 'last_page' => 1]
            : $bookings->past($user, (int) ($query['page'] ?? 1));

        return $this->private(response()->json($body));
    }

    public function show(Request $request, string $publicId, MyBookings $bookings): JsonResponse
    {
        $found = $bookings->find($request->user(), $publicId);

        if ($found === null) {
            return (new BookingRefused('booking_not_found'))->toResponse();
        }

        return $this->private(response()->json(['booking' => $found]));
    }

    /**
     * POST /api/v1/bookings/{id}/cancel: cancels the person's own booking (before its deadline) and gives the time back, in one transaction.
     * Safe to repeat: a booking that is already cancelled answers the same booking again and changes nothing (no second e-mail).
     */
    public function cancel(Request $request, string $publicId, BookingCancellation $cancellation, MyBookings $bookings, AvailabilityService $availability): JsonResponse
    {
        $user = $request->user();

        try {
            $done = $cancellation->cancel($user, $publicId);
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }

        if ($done['cancelled_now']) {
            $this->tellCounselor($done['booking'], $availability);
        }

        return $this->private(response()->json(['booking' => $bookings->find($user, $publicId)]));
    }

    /**
     * POST /api/v1/bookings/{id}/reschedule: moves the person's own booking to another time of the same counselor (same time limit as
     * cancelling), claiming the new time and giving the old one back in one transaction. Safe to repeat: asking for the time the booking
     * already has answers the same booking again and changes nothing (no second count, no second e-mail).
     */
    public function reschedule(Request $request, string $publicId, BookingRescheduling $rescheduling, MyBookings $bookings, AvailabilityService $availability): JsonResponse
    {
        $input = $request->validate([
            'slot' => ['required', 'string', 'max:64'],
            'expected_starts_at' => ['required', 'date'],
            'expected_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'expected_mode' => ['required', Rule::in(array_map(fn (SessionMode $m) => $m->value, SessionMode::cases()))],
        ]);

        $user = $request->user();

        try {
            $done = $rescheduling->reschedule($user, $publicId, $input);
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }

        if ($done['moved_now']) {
            $this->tellCounselorMoved($done['booking'], $done['from'], $done['to'], $availability);
        }

        return $this->private(response()->json(['booking' => $bookings->find($user, $publicId)]));
    }

    /** The e-mail: queued, after commit, and never able to undo or fail the move. */
    private function tellCounselorMoved(Booking $booking, CalendarEntry $from, CalendarEntry $to, AvailabilityService $availability): void
    {
        try {
            $old = $availability->startOf($from);
            $new = $availability->startOf($to);

            $booking->counselor->notify(new BookingRescheduledCounselor([
                'client' => $booking->user->name,
                'old_date' => $old->format('l, j F Y'),
                'old_time' => $old->format('g:i A').' – '.$old->addMinutes((int) $from->duration_minutes)->format('g:i A'),
                'date' => $new->format('l, j F Y'),
                'time' => $new->format('g:i A').' – '.$new->addMinutes((int) $to->duration_minutes)->format('g:i A'),
                'minutes' => (int) $to->duration_minutes,
                'mode' => $booking->session_mode->value,
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** The e-mail: queued, after commit, and never able to undo or fail the cancellation. */
    private function tellCounselor(Booking $booking, AvailabilityService $availability): void
    {
        try {
            $entry = $booking->calendarEntry;
            $start = $availability->startOf($entry);

            $booking->counselor->notify(new BookingCancelledCounselor([
                'client' => $booking->user->name,
                'date' => $start->format('l, j F Y'),
                'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
                'minutes' => (int) $entry->duration_minutes,
                'mode' => $booking->session_mode->value,
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Different for every person and holds a meeting link: no shared cache, and the phone does not keep it either. */
    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
