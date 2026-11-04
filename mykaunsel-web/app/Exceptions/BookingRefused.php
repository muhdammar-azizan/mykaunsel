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
        'planner_place_invalid' => [422, 'An in-person time needs one of that organization\'s places, or one of your own places for your independent practice (add it on your Profile, under My places).'],
        'planner_overlap' => [409, 'That time overlaps something else on your calendar (including the buffer around booked sessions).'],
        'block_invalid' => [422, 'Choose the first day, the last day (the same or later, at most 60 days in all), and either "all day" or a start and end on 5-minute marks with the end after the start.'],
        'block_has_session' => [409, 'You have a booked session in that time. Cancel the session first, then block the time.'],
        'block_limit' => [409, 'You have reached the most blocked days. Remove some that are over or not needed.'],
        'block_not_found' => [404, 'This blocked time was not found.'],
        'review_not_found' => [404, 'This review was not found.'],
        'review_not_open' => [409, 'This session can be reviewed from the day after it, once the counselor has marked it completed (or after 48 hours).'],
        'review_closed' => [409, 'The time to review this session has passed.'],
        'review_exists' => [409, 'You have already reviewed this session.'],
        'review_edit_closed' => [409, 'This review can no longer be changed.'],
        'review_invalid' => [422, 'Choose 1 to 5 stars and only the tags in the list.'],
        'reply_exists' => [409, 'You have already replied to this review.'],
        'reply_closed' => [409, 'You can reply once the review has been checked.'],
        'flag_exists' => [409, 'You have already sent this review to MyKaunsel.'],
        'planner_no_plan' => [409, "Your organization has no subscription plan yet, so a time can not be opened for it. MyKaunsel sets the plan up with your organization."],
        'planner_after_plan' => [409, "That day is after your organization's subscription ends, so a time can not be opened. It can be opened again once the subscription is renewed."],
        'planner_not_found' => [404, 'This time was not found.'],
        'planner_has_booking' => [409, 'This time is booked, or has had a booking, so it can\'t be removed.'],
        'weekly_time_invalid' => [422, 'Choose a weekday, a start and end on 5-minute marks (the end after the start, with room for at least one time) and an allowed length.'],
        'weekly_overlap' => [409, 'That overlaps another line of your weekly hours on the same day.'],
        'weekly_limit' => [409, 'You have reached the most lines of weekly hours.'],
        'weekly_fill_too_many' => [422, 'That would open more than 200 times at once. Choose fewer weeks, or shorten your weekly hours, then try again. Nothing was opened.'],
        'requests_closed' => [409, "This counselor isn't taking time requests right now."],
        'request_time_invalid' => [422, "Choose a time inside the counselor's working hours, far enough ahead."],
        'request_limit_reached' => [409, 'You have reached the limit of requests waiting for an answer. Wait for an answer or withdraw one.'],
        'request_not_found' => [404, 'This request was not found.'],
        'request_not_pending' => [409, 'This request was already answered, withdrawn or has expired.'],
        'request_cannot_approve' => [409, "This request can't be approved any more: the time no longer fits."],
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
