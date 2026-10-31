<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Joining an organization was refused. [reason] is a stable word the app and the tests can rely on; the message is plain English for the person.
 * A wrong, expired, switched-off or used-up code all give the SAME message, so a guess teaches nothing.
 */
class MembershipRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    private const STATUS = [
        'invalid_code' => 422,
        'email_not_verified' => 403,
        'already_member' => 409,
        'waiting_approval' => 409,
        'in_other_organization' => 409,
    ];

    public function status(): int
    {
        return self::STATUS[$this->reason] ?? 422;
    }

    public function toResponse(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->reason], $this->status())
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Authorization');
    }

    public static function invalidCode(): self
    {
        return new self('invalid_code', 'This code is not valid or has expired. Check it with your organization.');
    }

    public static function emailNotVerified(): self
    {
        return new self('email_not_verified', 'Verify your email address before joining an organization.');
    }

    public static function alreadyMember(): self
    {
        return new self('already_member', 'You are already a member of this organization.');
    }

    public static function waitingApproval(): self
    {
        return new self('waiting_approval', 'Your request is already waiting for the organization to approve it.');
    }

    public static function inOtherOrganization(string $name): self
    {
        return new self('in_other_organization', "You are still a member of {$name}. Leave it first to join another organization.");
    }
}
