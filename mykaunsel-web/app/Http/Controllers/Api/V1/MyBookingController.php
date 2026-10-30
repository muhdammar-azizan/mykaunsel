<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Notifications\BookingCancelledCounselor;
use App\Services\AvailabilityService;
use App\Services\BookingCancellation;
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
