<?php

namespace App\Exceptions;

use App\Enums\OrgStatus;
use RuntimeException;

class InvalidOrgTransition extends RuntimeException
{
    public static function between(OrgStatus $from, OrgStatus $to): self
    {
        return new self("An organization cannot move from {$from->value} to {$to->value}.");
    }

    /** Someone else (another admin) already changed the status. */
    public static function alreadyChanged(OrgStatus $expected, OrgStatus $actual): self
    {
        return new self("Expected status {$expected->value} but it is now {$actual->value}.");
    }
}
