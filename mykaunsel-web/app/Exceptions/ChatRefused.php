<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A chat request that is refused for a reason the person can be told. Carries the machine-readable `code` the app switches on. */
class ChatRefused extends RuntimeException
{
    private const STATUS = [
        'ai_consent_required' => [403, 'Turn on AI chat in your settings to talk with the companion.'],
        'message_empty' => [422, 'Write a message first.'],
        'session_not_found' => [404, 'This conversation was not found.'],
        'session_ended' => [409, 'This conversation has ended. Start a new one.'],
        'session_full' => [409, 'This conversation is full. Start a new one.'],
        'chat_daily_limit' => [429, "You've reached today's limit of messages. Please come back tomorrow."],
    ];

    /** @param  array<string, mixed>  $extra */
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
