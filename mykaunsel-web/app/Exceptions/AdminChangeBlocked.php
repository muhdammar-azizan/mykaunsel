<?php

namespace App\Exceptions;

use RuntimeException;

/** A Platform Admin change cannot go ahead (already an admin, removing yourself or the last admin…). Carries the reasons shown to the admin. */
class AdminChangeBlocked extends RuntimeException
{
    /** @param  list<string>  $reasons */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
