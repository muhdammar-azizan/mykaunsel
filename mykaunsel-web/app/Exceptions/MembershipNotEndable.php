<?php

namespace App\Exceptions;

use RuntimeException;

/** There is no membership to end: the person already left (or was removed), or they are not a member of this organization. */
class MembershipNotEndable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This person is not a member of the organization (any more).');
    }
}
