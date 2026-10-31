<?php

namespace App\Exceptions;

use RuntimeException;

/** The invoice can not be changed that way: it is already paid or voided (someone else may have just done it). Nothing was changed. */
class InvoiceNotOpen extends RuntimeException
{
    public function __construct(string $status)
    {
        parent::__construct($status === 'paid' ? 'This invoice is already paid.' : 'This invoice is already void.');
    }
}
