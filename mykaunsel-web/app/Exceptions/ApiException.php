<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** An expected API refusal with a machine-readable code the app can act on (email_not_verified, two_factor_required…). */
class ApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode, public readonly int $status = 403, public readonly array $extra = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode] + $this->extra, $this->status);
    }
}
