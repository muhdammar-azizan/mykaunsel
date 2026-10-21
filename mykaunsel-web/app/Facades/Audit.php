<?php

namespace App\Facades;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Models\AuditLog log(string $action, ?\Illuminate\Database\Eloquent\Model $target = null, array $metadata = [], \App\Models\User|string|null $actor = null, array $options = [])
 * @method static \App\Models\AuditLog|null tryLog(string $action, ?\Illuminate\Database\Eloquent\Model $target = null, array $metadata = [], \App\Models\User|string|null $actor = null, array $options = [])
 * @method static mixed asSystem(callable $callback)
 *
 * @see AuditLogger
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}
