<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BookingRefused;
use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Notifications\NewTimeRequestCounselor;
use App\Services\AvailabilityService;
use App\Services\BookingRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Mode B, the client's side (docs/AVAILABILITY.md, "Time requests").
 *
 * GET  /api/v1/counselors/{id}/working-hours   the counselor's weekly hours the person may see, to pick a time from
 * POST /api/v1/counselors/{id}/requests        ask for a time (201; the same request again while it waits answers 200 with the same one)
 * GET  /api/v1/requests, /requests/{id}        the person's own requests (another person's is the same 404 as an unknown one)
 * POST /api/v1/requests/{id}/withdraw          take back a request that still waits (safe to repeat)
 *
 * The rules live in BookingRequests. A counselor the person may not see is the same `404 counselor_unavailable` as everywhere.
 */
class TimeRequestController extends Controller
{
    public function hours(Request $request, string $publicId, BookingRequests $requests): JsonResponse
    {
        try {
            return $this->private(response()->json($requests->workingHours($request->user(), $this->id($publicId))));
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }
    }

    public function store(Request $request, string $publicId, BookingRequests $requests, AvailabilityService $availability): JsonResponse
    {
        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $made = $requests->create($request->user(), $this->id($publicId), $input);
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }

        if ($made['created']) {
            $this->tellCounselor($made['request'], $requests, $availability);
        }

        return $this->private(response()->json(['request' => $requests->present($made['request']->load(['counselor.counselorProfile', 'location:id,name,city', 'booking:id,public_id']))], $made['created'] ? 201 : 200));
    }

    public function index(Request $request, BookingRequests $requests): JsonResponse
    {
        $query = $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:10000']]);

        return $this->private(response()->json($requests->list($request->user(), (int) ($query['page'] ?? 1))));
    }

    public function show(Request $request, string $publicId, BookingRequests $requests): JsonResponse
    {
        $found = $requests->find($request->user(), $publicId);

        return $found === null ? (new BookingRefused('request_not_found'))->toResponse() : $this->private(response()->json(['request' => $found]));
    }

    public function withdraw(Request $request, string $publicId, BookingRequests $requests): JsonResponse
    {
        try {
            $requests->withdraw($request->user(), $publicId);
        } catch (BookingRefused $refused) {
            return $refused->toResponse();
        }

        return $this->private(response()->json(['request' => $requests->find($request->user(), $publicId)]));
    }

    /** The e-mail: queued, after commit, and never able to undo or fail the request. */
    private function tellCounselor(BookingRequest $request, BookingRequests $requests, AvailabilityService $availability): void
    {
        try {
            $start = $requests->startOf($request);

            $request->counselor->notify(new NewTimeRequestCounselor([
                'client' => $request->user->name,
                'date' => $start->format('l, j F Y'),
                'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $request->duration_minutes)->format('g:i A'),
                'minutes' => (int) $request->duration_minutes,
                'mode' => ($request->session_mode ?? \App\Enums\SessionMode::Online)->value,
                'until' => $request->expires_at->setTimezone($availability->timezone())->format('l, j F, g:i A'),
            ]));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function id(string $publicId): string
    {
        return strtolower($publicId);
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
