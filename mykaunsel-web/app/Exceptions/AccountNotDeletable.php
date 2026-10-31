<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The account can not be deleted from here. [reason] is a stable word the app relies on. */
class AccountNotDeletable extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message, private int $status)
    {
        parent::__construct($message);
    }

    /** Counselors and admins hold records other people depend on (sessions, notes, the organization), so they are ended with help. */
    public static function staff(): self
    {
        return new self('staff_account', 'This account belongs to a counselor or an administrator, so it can not be deleted here. Please write to MyKaunsel support.', 403);
    }

    public static function alreadyDeleted(): self
    {
        return new self('already_deleted', 'This account has already been deleted.', 404);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->reason], $this->status)
            ->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
