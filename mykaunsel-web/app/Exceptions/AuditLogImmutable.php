<?php

namespace App\Exceptions;

use LogicException;

/** Thrown when code tries to change or remove an audit entry. The log is append-only. */
class AuditLogImmutable extends LogicException
{
    public static function make(string $attempt): self
    {
        return new self("Audit log entries are append-only: {$attempt} is not allowed.");
    }
}
