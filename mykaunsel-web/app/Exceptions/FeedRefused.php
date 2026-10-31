<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A feed request that is refused for a reason the person can be told. Carries the machine-readable `code` the app switches on. */
class FeedRefused extends RuntimeException
{
    private const STATUS = [
        'post_not_found' => [404, 'This post was not found.'],
        'comment_not_found' => [404, 'This comment was not found.'],
        'mute_not_found' => [404, 'This was not found.'],
        'text_empty' => [422, 'Write something first.'],
        'cannot_report_own' => [409, "You can't report your own writing. You can delete it instead."],
        'cannot_mute_self' => [409, "You can't mute yourself."],
        'daily_post_limit' => [429, "You've reached today's limit of posts. Please come back tomorrow."],
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
