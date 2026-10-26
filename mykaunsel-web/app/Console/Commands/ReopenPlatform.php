<?php

namespace App\Console\Commands;

use App\Services\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Emergency way out of Registration Control, for the terminal: turn maintenance mode off and open every sign-up again.
 *
 * It needs no browser and no sign-in, so a wrong setting can never lock the platform with no way back. Everything it
 * changes is written to the audit log as a "system" action (via "command"). Safe to run twice: whatever is already open
 * is left alone and nothing more is logged. The wording of the messages is never touched.
 *
 *   php artisan platform:reopen              maintenance off AND every sign-up open
 *   php artisan platform:reopen --maintenance   only maintenance off
 *   php artisan platform:reopen --registration  only open the sign-ups
 *   php artisan platform:reopen --dry-run       show the current state and what would change, change nothing
 */
class ReopenPlatform extends Command
{
    protected $signature = 'platform:reopen
        {--maintenance : Only turn maintenance mode off}
        {--registration : Only open every sign-up}
        {--dry-run : Show what would change, change nothing}';

    protected $description = 'Emergency exit: turn maintenance mode off and open every sign-up again';

    public function handle(PlatformSettings $settings): int
    {
        $both = ! $this->option('maintenance') && ! $this->option('registration');
        $maintenance = $both || $this->option('maintenance');
        $registration = $both || $this->option('registration');
        $dryRun = (bool) $this->option('dry-run');

        $closed = array_keys(array_filter($settings->registrationStatus(), fn (bool $open) => ! $open));

        if ($maintenance) {
            $this->line('Maintenance mode: '.($settings->maintenanceOn() ? 'ON  -> will be turned off' : 'off (nothing to do)'));
        }

        if ($registration) {
            foreach ($settings->roles() as $role) {
                $this->line(str_pad(config("platform-settings.registration_roles.{$role}.label").' sign-up:', 26).(in_array($role, $closed, true) ? 'closed -> will be opened' : 'open (nothing to do)'));
            }
        }

        if ($dryRun) {
            $this->info('Dry run: nothing was changed.');

            return self::SUCCESS;
        }

        try {
            // One transaction around both: either the platform is reopened completely, or nothing changed at all.
            [$maintenanceChanged, $opened] = DB::transaction(fn () => [
                $maintenance && $settings->setMaintenance(null, false, via: 'command'),
                $registration ? $settings->setRegistration(null, $settings->roles(), true, forAll: true, via: 'command') : [],
            ]);
        } catch (Throwable $e) {
            report($e);
            $this->error('Nothing could be changed: '.$e->getMessage());
            $this->line('If the platform_settings table is missing, run: php artisan migrate');

            return self::FAILURE;
        }

        if (! $maintenanceChanged && $opened === []) {
            $this->info('Nothing to change: maintenance is off'.($registration ? ' and every sign-up is open' : '').'.');

            return self::SUCCESS;
        }

        if ($maintenanceChanged) {
            $this->info('Maintenance mode is now OFF.');
        }

        foreach ($opened as $role) {
            $this->info(config("platform-settings.registration_roles.{$role}.label").' sign-up is now open.');
        }

        $this->line('Visitors see the change on their next request (nothing is cached). Each change is in the audit log as a system action.');

        return self::SUCCESS;
    }
}
