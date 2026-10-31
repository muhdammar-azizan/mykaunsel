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
Schedule::command('admin-invitations:purge')->dailyAt('03:45')->withoutOverlapping();
Schedule::command('counselors:purge-documents')->dailyAt('02:30')->withoutOverlapping();

// Maintenance mode with a reopen time switches itself off when the time arrives (checked every minute).
Schedule::command('maintenance:reopen-due')->everyMinute()->withoutOverlapping();

// The text of complaints closed 3 years ago is erased (the record stays): see config/complaints.php.
Schedule::command('complaints:purge-text')->dailyAt('03:15')->withoutOverlapping();

// In-app notifications are deleted after 90 days (read) or 180 days (unread): see config/notifications.php.
Schedule::command('notifications:purge')->dailyAt('03:20')->withoutOverlapping();

// Waitlist addresses are deleted 30 days after they were notified (365 days if never): see config/waitlist.php.
Schedule::command('bookings:purge-idempotency')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('waitlist:purge')->dailyAt('03:00')->withoutOverlapping();

// Bookings and time requests of accounts deleted more than 90 days ago are deleted (those a session note or a complaint needs stay): see config/account.php.
Schedule::command('accounts:purge-deleted')->dailyAt('03:40')->withoutOverlapping();

// Evidence of LKM review requests is deleted 30 days after the decision: see config/lkm.php.
Schedule::command('lkm:purge-review-evidence')->dailyAt('02:45')->withoutOverlapping();

// Practising certificate (PA): reminders before it runs out, automatic suspension once it has expired.
// Runs in the application timezone, just after the last valid second of the "valid until" day (see PaValidity).
Schedule::command('counselors:check-pa-expiry')->dailyAt('00:10')->withoutOverlapping();

// Time requests nobody answered in time are closed, and the people who asked are told.
Schedule::command('requests:expire')->everyTenMinutes()->withoutOverlapping();

// Chat: end conversations nobody wrote in for a while (storing their dominant emotion), and delete old ones after the retention period.
Schedule::command('chat:end-idle')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('chat:purge')->dailyAt('03:40')->withoutOverlapping();
