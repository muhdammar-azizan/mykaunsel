<?php

/*
 * The waitlist for closed sign-ups (Platform Admin → Registration Control → Waitlist; docs/WAITLIST.md).
 * Only Organization and Counselor sign-up on the web have one. Individuals sign up in the mobile app: no waitlist for them.
 */
return [

    // The kinds of sign-up with a waitlist (the keys of platform-settings.registration_roles).
    'types' => ['organization', 'counselor'],

    // A dedicated secret for the hashes of people who opted out and for the opt-out links. NOT APP_KEY: rotating APP_KEY
    // must not break them. At least 32 characters; make one with `php artisan waitlist:key`. Keep it stable: changing it
    // makes old opt-out links stop working and old opt-outs stop matching. Without it the waitlist is switched off.
    'hmac_key' => env('WAITLIST_HMAC_KEY'),

    // Abuse limits on the public form. Every answer is the same for a new and an existing address, so none of these can
    // be used to find out who is on the list.
    'limits' => [
        'per_ip_per_hour' => 5,
        'per_email_per_day' => 3,
        // Per kind of sign-up, against a spread-out attack from many addresses.
        'global_per_hour' => (int) env('WAITLIST_GLOBAL_PER_HOUR', 100),
    ],

    // "Notify all": emails are queued and spread out at this many a minute.
    'send_per_minute' => (int) env('WAITLIST_SEND_PER_MINUTE', 60),
    'chunk' => 200,

    // How long an address is kept (`waitlist:purge`, daily). Counted from the moment they were notified; addresses that
    // were never notified go after this many days from sign-up.
    'retention' => [
        'notified_days' => (int) env('WAITLIST_RETENTION_NOTIFIED_DAYS', 30),
        'waiting_days' => (int) env('WAITLIST_RETENTION_WAITING_DAYS', 365),
    ],

    'per_page' => 25,
    'export_max_rows' => 10000,

    // Linked from the consent line (a placeholder page until the real policy is written).
    'privacy_url' => env('PRIVACY_POLICY_URL', '/privacy'),
];
