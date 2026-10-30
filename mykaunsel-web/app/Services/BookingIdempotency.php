<?php

namespace App\Services;

use App\Exceptions\BookingRefused;
use App\Models\BookingIdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Makes "book this" safe to repeat. The app sends the same Idempotency-Key when it is not sure the first try arrived.
 *
 * - The key is RESERVED in its own short transaction BEFORE the booking transaction, so a booking that rolls back cannot erase the
 *   reservation logic, and two requests with the same key cannot both start.
 * - Only a successful booking (201) is kept; the same key + same request later gets that very answer back.
 * - A refusal (a 4xx) or a crash before the booking committed RELEASES the key, so the person can try again with it.
 * - A reservation left "processing" (the server died) stops blocking after `booking.idempotency_processing_seconds`.
 * - The same key with a DIFFERENT request is refused (422), never answered with the other request's result.
 *
 * Only a hash of the normalised request is stored, never the request itself, a token or a header.
 */
class BookingIdempotency
{
    /**
     * @return array{status: int, body: array<string, mixed>}|BookingIdempotencyKey  a stored answer to replay, or the reservation just made
     *
     * @throws BookingRefused request_in_progress | idempotency_key_reuse
     */
    public function reserve(int $userId, string $key, string $fingerprint): array|BookingIdempotencyKey
    {
        $key = strtolower($key);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                return BookingIdempotencyKey::create([
                    'user_id' => $userId, 'key' => $key, 'fingerprint' => $fingerprint, 'status' => 'processing',
                    'expires_at' => Carbon::now()->addHours((int) config('booking.idempotency_ttl_hours')),
                ]);
            } catch (UniqueConstraintViolationException) {
                // The key is known: look at what it says.
            }

            $existing = BookingIdempotencyKey::query()->where('user_id', $userId)->where('key', $key)->first();

            if ($existing === null) {
                continue; // released in the meantime: try to reserve again
            }

            if (! hash_equals($existing->fingerprint, $fingerprint)) {
                throw new BookingRefused('idempotency_key_reuse');
            }

            if ($existing->status === 'completed' && $existing->expires_at?->isFuture()) {
                return ['status' => (int) $existing->response_status, 'body' => json_decode((string) $existing->response_body, true) ?: []];
            }

            // Stale: an answer past its day, or a "processing" that never finished. Remove it (only if it is still that row) and go round again.
            $staleBefore = Carbon::now()->subSeconds((int) config('booking.idempotency_processing_seconds'));
            $stale = $existing->status === 'completed'
                || $existing->expires_at === null || $existing->expires_at->isPast()
                || $existing->created_at === null || $existing->created_at->lte($staleBefore);

            if (! $stale) {
                throw new BookingRefused('request_in_progress', retryAfter: 2);
            }

            BookingIdempotencyKey::query()->whereKey($existing->id)->where('status', $existing->status)->delete();
        }

        throw new BookingRefused('request_in_progress', retryAfter: 2);
    }

    /** Keep the answer of a successful booking under its key. */
    public function complete(BookingIdempotencyKey $reservation, int $status, array $body): void
    {
        $reservation->forceFill(['status' => 'completed', 'response_status' => $status, 'response_body' => json_encode($body)])->save();
    }

    /** Forget a reservation (the request was refused or failed before anything was booked). */
    public function release(BookingIdempotencyKey $reservation): void
    {
        BookingIdempotencyKey::query()->whereKey($reservation->id)->delete();
    }

    /** A stable hash of the request: the same request always gives the same hash, however it was spelled. */
    public function fingerprint(string $slot, bool $acknowledged, string $startsAt, int $minutes, string $mode): string
    {
        return hash('sha256', json_encode([strtolower($slot), $acknowledged, Carbon::parse($startsAt)->utc()->format('Y-m-d\TH:i:s'), $minutes, strtolower($mode)]));
    }
}
