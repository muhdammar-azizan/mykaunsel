<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * For every seeder that makes DEMO data: accounts with a well-known password, sample organizations, counselors, complaints, prices.
 * Such a seeder only runs in the local and testing environments. Anywhere else it stops with a clear error BEFORE it creates
 * anything, so `php artisan db:seed` (or `migrate --seed`) on a real server cannot leave a known password behind.
 *
 * Call $this->refuseOutsideDevelopment() as the very first thing in run().
 */
trait RefusesOutsideDevelopment
{
    protected function refuseOutsideDevelopment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                class_basename($this).' creates demo data (accounts with a well-known password, sample records), so it only runs in the local and testing environments. '
                .'Nothing was created. On a real server run "php artisan migrate --force" and create the first Platform Admin with "php artisan platform:create-admin" (see docs/SETUP.md).'
            );
        }
    }
}
