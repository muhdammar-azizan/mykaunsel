<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the scheduler running (cron: * * * * * php artisan schedule:run).
Schedule::command('organizations:check-dns')->everyThirtyMinutes()->withoutOverlapping();

// Document retention (ID copies after 30 days, certificates of rejected applications after 90): see config/counselors.php.
Schedule::command('counselors:purge-documents')->dailyAt('02:30')->withoutOverlapping();

// Maintenance mode with a reopen time switches itself off when the time arrives (checked every minute).
Schedule::command('maintenance:reopen-due')->everyMinute()->withoutOverlapping();

// Waitlist addresses are deleted 30 days after they were notified (365 days if never): see config/waitlist.php.
Schedule::command('waitlist:purge')->dailyAt('03:00')->withoutOverlapping();

// Evidence of LKM review requests is deleted 30 days after the decision: see config/lkm.php.
Schedule::command('lkm:purge-review-evidence')->dailyAt('02:45')->withoutOverlapping();

// Practising certificate (PA): reminders before it runs out, automatic suspension once it has expired.
// Runs in the application timezone, just after the last valid second of the "valid until" day (see PaValidity).
Schedule::command('counselors:check-pa-expiry')->dailyAt('00:10')->withoutOverlapping();
