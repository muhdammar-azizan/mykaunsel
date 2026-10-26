<?php

namespace App\Exceptions;

use App\Models\LkmReviewRequest;
use RuntimeException;

/** Another admin decided this review request first. */
class LkmReviewAlreadyDecided extends RuntimeException
{
    public function __construct(public readonly LkmReviewRequest $review)
    {
        parent::__construct("This review request was already {$review->status}.");
    }
}
