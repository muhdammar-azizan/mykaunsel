<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Per-request / per-process state for audit logging: the request id that groups entries,
 * and the "system" flag for scheduled jobs and queue workers.
 */
class AuditContext
{
    private ?string $requestId = null;

    private int $systemDepth = 0;

    /** New request (see AssignRequestId): every entry written during it shares this id. */
    public function startRequest(): string
    {
        return $this->requestId = (string) Str::uuid();
    }

    /** In the console (scheduler, queue, tinker) one id is generated lazily and used for the whole run. */
    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::uuid();
    }

    /** Run $callback so that anything it logs has the "system" actor. */
    public function asSystem(callable $callback): mixed
    {
        $this->systemDepth++;

        try {
            return $callback();
        } finally {
            $this->systemDepth--;
        }
    }

    public function isSystem(): bool
    {
        return $this->systemDepth > 0;
    }
}
