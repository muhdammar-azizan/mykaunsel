<?php

namespace App\Exceptions;

use RuntimeException;

/** A plan, promo code or assignment change cannot go ahead (a rule is broken, or someone else changed it first). Carries the reasons shown to the admin. */
class PricingChangeBlocked extends RuntimeException
{
    /** @param  list<string>  $reasons */
    public function __construct(public readonly array $reasons, public readonly bool $conflict = false)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
