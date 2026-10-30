<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MyBookings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            return $this->private(response()->json(['message' => 'This booking was not found.', 'code' => 'booking_not_found'], 404));
        }

        return $this->private(response()->json(['booking' => $found]));
    }

    /** Different for every person and holds a meeting link: no shared cache, and the phone does not keep it either. */
    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
