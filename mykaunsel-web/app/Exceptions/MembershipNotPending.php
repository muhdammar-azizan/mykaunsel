<?php

namespace App\Exceptions;

use RuntimeException;

/** The request to join was already answered (or withdrawn) by someone else, so there is nothing to approve or decline any more. */
class MembershipNotPending extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This request has already been dealt with.');
    }
}
