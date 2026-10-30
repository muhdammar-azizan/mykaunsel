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
