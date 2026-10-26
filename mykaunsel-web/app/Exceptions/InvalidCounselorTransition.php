<?php

namespace App\Exceptions;

use App\Enums\VerificationStatus;
use RuntimeException;

class InvalidCounselorTransition extends RuntimeException
{
    public static function between(VerificationStatus $from, VerificationStatus $to): self
    {
        return new self("A counselor cannot move from {$from->value} to {$to->value}.");
    }

    /** Someone else (another admin) already changed the status. */
    public static function alreadyChanged(VerificationStatus $expected, VerificationStatus $actual): self
    {
        return new self("Expected status {$expected->value} but it is now {$actual->value}.");
    }
}
