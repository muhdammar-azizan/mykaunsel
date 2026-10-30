<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A booking request that is refused for a reason the person can be told. Carries the machine-readable `code` the app switches on.
 *
 * Several private reasons deliberately share ONE code (see BookingService): `counselor_unavailable` for every counselor-related "no" and
 * `slot_taken` for every reason a time can no longer be booked, so a private schedule or a hidden counselor is never revealed.
 */
class BookingRefused extends RuntimeException
{
    private const STATUS = [
        'counselor_unavailable' => [404, 'This counselor is no longer available for booking.'],
        'slot_taken' => [409, 'That time was just taken. Please choose another.'],
        'slot_changed' => [409, 'The details of this session changed. Please review and confirm again.'],
        'time_conflict' => [409, 'You already have a session at this time.'],
        'upcoming_limit_reached' => [409, 'You have reached the limit of upcoming sessions. Cancel or complete one to book another.'],
        'request_in_progress' => [409, 'This request is still being processed.'],
        'booking_not_found' => [404, 'This booking was not found.'],
        'cancellation_closed' => [409, "This session starts too soon, so it can't be cancelled."],
        'not_cancellable' => [409, "This booking can't be cancelled."],
        'not_reschedulable' => [409, "This booking can't be moved."],
        'reschedule_closed' => [409, "This session starts too soon, so it can't be moved."],
        'reschedule_limit_reached' => [409, "This booking has already been moved the most times it can be."],
        'different_counselor' => [422, 'A booking can only be moved to another time of the same counselor.'],
        'meeting_closed' => [409, "The meeting link can't be changed for this session."],
        'session_not_ended' => [409, "This session hasn't ended yet."],
        'outcome_set' => [409, 'This session was already marked differently.'],
        'planner_time_invalid' => [422, 'Choose a start time on a 5-minute mark and one of the allowed lengths.'],
        'planner_in_past' => [422, 'That time has already passed.'],
        'planner_too_far' => [422, 'That is too far ahead to open yet.'],
        'planner_context_invalid' => [422, 'Choose where you work from the list.'],
        'planner_place_invalid' => [422, 'An in-person time needs one of that organization\'s places.'],
        'planner_overlap' => [409, 'That time overlaps something else on your calendar (including the buffer around booked sessions).'],
        'planner_not_found' => [404, 'This time was not found.'],
        'planner_has_booking' => [409, 'This time is booked, or has had a booking, so it can\'t be removed.'],
        'idempotency_key_reuse' => [422, 'This Idempotency-Key was already used for a different request.'],
    ];

    /** @param  array<string, mixed>  $extra  more keys for the body (for example the fresh details of a changed slot) */
    public function __construct(public readonly string $reason, public readonly array $extra = [], public readonly int $retryAfter = 0)
    {
        parent::__construct(self::STATUS[$reason][1]);
    }

    public function status(): int
    {
        return self::STATUS[$this->reason][0];
    }

    public function toResponse(): JsonResponse
    {
        $response = response()->json(['message' => $this->getMessage(), 'code' => $this->reason] + $this->extra, $this->status())
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Authorization');

        return $this->retryAfter > 0 ? $response->header('Retry-After', (string) $this->retryAfter) : $response;
    }
}
