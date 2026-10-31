<?php

namespace App\Exceptions;

use RuntimeException;

/** A moderation action found no pending reports: another admin has already dealt with this post or comment. */
class ModerationNothingToDo extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Another admin has already dealt with this.');
    }
}
