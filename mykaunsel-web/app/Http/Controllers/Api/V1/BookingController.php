<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Notifications\BookingConfirmedClient;
use App\Notifications\NewBookingCounselor;
use App\Services\AvailabilityService;
use App\Services\BookingIdempotency;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * GET  /api/v1/bookings/preview?slot={public id}  what the confirm screen shows, and whether a booking would be accepted. Claims nothing.
 * POST /api/v1/bookings                           the booking itself (Idempotency-Key required). The only place a Mode A booking is made.
 *
 * The rules live in BookingService / CounselorDirectory / AvailabilityService, shared by both calls. This class validates, remembers the
 * request by its key, shapes the answer and sends the two e-mails. See docs/AVAILABILITY.md ("The confirm step").
 */
class BookingController extends Controller
{
    public function preview(Request $request, BookingService $bookings): JsonResponse
    {
        $query = $request->validate(['slot' => ['required', 'string', 'max:64']]);

        try {
            return $this->private(response()->json($bookings->preview($request->user(), $query['slot'])));
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }
    }

    public function store(Request $request, BookingService $bookings, BookingIdempotency $idempotency, AvailabilityService $availability): JsonResponse
    {
        $key = Validator::make(['key' => (string) $request->header('Idempotency-Key')], ['key' => ['required', 'uuid']], [], ['key' => 'Idempotency-Key header'])->validate()['key'];

        $input = $request->validate([
            'slot' => ['required', 'string', 'max:64'],
            'acknowledged_policy' => ['required', 'boolean', fn ($attribute, $value, $fail) => $value === true || $fail('You must acknowledge the no-recording policy to book.')],
            'expected_starts_at' => ['required', 'date'],
            'expected_duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'expected_mode' => ['required', Rule::in(array_map(fn (SessionMode $m) => $m->value, SessionMode::cases()))],
        ]);

        $user = $request->user();

        try {
            $reservation = $idempotency->reserve(
                $user->getKey(),
                $key,
                $idempotency->fingerprint($input['slot'], true, $input['expected_starts_at'], (int) $input['expected_duration_minutes'], $input['expected_mode']),
            );

            if (is_array($reservation)) {
                return $this->private(response()->json($reservation['body'], $reservation['status']))->header('Idempotent-Replayed', 'true');
            }

            try {
                $made = $bookings->create($user, $input);
            } catch (Throwable $e) {
                // Nothing was booked (the transaction rolled back): the key is free to be used again.
                $idempotency->release($reservation);

                throw $e;
            }
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }

        $body = $bookings->created($made);

        try {
            $idempotency->complete($reservation, 201, $body);
        } catch (Throwable $e) {
            report($e); // the booking stands; the key simply expires
        }

        $this->tell($made, $availability);

        return $this->private(response()->json($body, 201));
    }

    /** The two e-mails: queued, after commit, and never able to undo or fail the booking. */
    private function tell(array $made, AvailabilityService $availability): void
    {
        try {
            $slot = $made['slot'];
            $booking = $made['booking'];
            $start = $availability->startOf($slot);
            $details = app(BookingService::class)->created($made)['booking'];
            $deadline = $start->subHours(app(BookingService::class)->cancellationHours($slot));
            $place = $details['location'] === null ? null : "{$details['location']['name']}, {$details['location']['city']}";
            $base = ['date' => $start->format('l, j F Y'), 'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $slot->duration_minutes)->format('g:i A'), 'minutes' => (int) $slot->duration_minutes];

            $booking->user->notify(new BookingConfirmedClient($base + [
                'counselor' => $details['counselor']['display_name'],
                'mode' => $details['mode'],
                'place' => $place,
                'deadline' => $deadline->gt($availability->now()) ? $deadline->format('l, j F Y, g:i A') : null,
            ]));

            $booking->counselor->notify(new NewBookingCounselor($base + ['client' => $booking->user->name, 'mode' => $details['mode']]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
