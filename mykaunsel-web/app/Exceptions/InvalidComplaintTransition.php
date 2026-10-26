<?php

namespace App\Exceptions;

use App\Enums\ComplaintStatus;
use RuntimeException;

class InvalidComplaintTransition extends RuntimeException
{
    public static function between(ComplaintStatus $from, ComplaintStatus $to): self
    {
        return new self("A complaint cannot move from {$from->value} to {$to->value}.");
    }

    /** Someone else (another admin) already changed the status. */
    public static function alreadyChanged(ComplaintStatus $expected, ComplaintStatus $actual): self
    {
        return new self("Expected status {$expected->value} but it is now {$actual->value}.");
    }
}
