<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SessionMode;
use App\Http\Controllers\Controller;
use App\Services\AvailabilityService;
use App\Services\CounselorDirectory;
use App\Support\CounselorPublicPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/counselors/{publicId}/availability — the times of one counselor that can be picked (docs/AVAILABILITY.md).
 *
 * READ ONLY: it never creates a booking and never holds a time. Who may see the counselor is decided in CounselorDirectory, which times can
 * be booked in AvailabilityService; this class only validates the question and shapes the answer. There is no meeting link, no street
 * address, no client, no booking, no private block and no organization name in the answer.
 *
 * Everything that is not "a counselor this person may book" gets ONE answer (404 `counselor_unavailable`, the same status and body): unknown
 * id, not listed (unapproved, suspended, PA expired, organization inactive), a closed organization's counselor for a non-member, and the
 * person themselves.
 */
class AvailabilityController extends Controller
{
    public function __invoke(Request $request, string $publicId, CounselorDirectory $directory, AvailabilityService $availability): JsonResponse
    {
        $query = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('booking.availability_days_per_request')],
            'mode' => ['nullable', Rule::in(array_map(fn (SessionMode $m) => $m->value, SessionMode::cases()))],
        ]);

        $found = preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $publicId) === 1
            ? $directory->resolveBookable($request->user(), strtolower($publicId))
            : null;

        if ($found === null) {
            return $this->unavailable();
        }

        /** @var \App\Models\CounselorProfile $profile */
        $profile = $found['profile'];

        $answer = $availability->present(
            $directory->windowSlots($found['orgIds'], $profile->user_id),
            $query['from'] ?? null,
            (int) ($query['days'] ?? config('booking.availability_days_per_request')),
            $query['mode'] ?? null,
        );

        return response()->json([
            'counselor' => [
                'id' => $profile->public_id,
                'display_name' => CounselorPublicPresenter::displayName($profile, $found['orgIds']),
                // No photos are shown until there is an approval step; the field stays so adding them later breaks nothing.
                'avatar_url' => null,
                'verified' => true,
                'modes_offered' => $answer['modes_offered'],
                'accepts_requests' => (bool) $profile->accepts_requests,
            ],
            'time_zone' => $availability->timezone(),
            'booking_window' => $answer['booking_window'],
            'days' => $answer['days'],
            'next_from' => $answer['next_from'],
        ])
            // Different for every person, so no shared cache may keep it; the phone itself may keep it for 30 seconds.
            ->header('Cache-Control', 'private, max-age=30')
            ->header('Vary', 'Authorization');
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'This counselor is no longer available for booking.', 'code' => 'counselor_unavailable'], 404)
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Authorization');
    }
}
