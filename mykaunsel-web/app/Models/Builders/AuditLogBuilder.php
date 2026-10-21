<?php

namespace App\Models\Builders;

use App\Exceptions\AuditLogImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent query builder for AuditLog: reading and inserting are allowed, changing or removing rows is not.
 * (Raw DB::table() access is outside what code can police — production adds database triggers, see docs/AUDIT_LOG.md.)
 */
class AuditLogBuilder extends Builder
{
    public function update(array $values)
    {
        throw AuditLogImmutable::make('update');
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw AuditLogImmutable::make('upsert');
    }

    public function delete()
    {
        throw AuditLogImmutable::make('delete');
    }

    public function forceDelete()
    {
        throw AuditLogImmutable::make('delete');
    }

    public function truncate()
    {
        throw AuditLogImmutable::make('truncate');
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw AuditLogImmutable::make('update');
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw AuditLogImmutable::make('update');
    }
}
