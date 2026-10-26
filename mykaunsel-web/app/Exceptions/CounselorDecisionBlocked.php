<?php

namespace App\Exceptions;

use RuntimeException;

/** A review action cannot go ahead (LKM record unusable, documents missing…). Carries every reason, shown to the admin. */
class CounselorDecisionBlocked extends RuntimeException
{
    /** @param  list<string>  $reasons */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
