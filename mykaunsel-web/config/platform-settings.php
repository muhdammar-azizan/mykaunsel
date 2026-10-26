<?php

/*
 * Platform settings that a Platform Admin can change on the Registration Control page.
 * The values live in the `platform_settings` table; this file holds the defaults and the wording the page uses.
 */
return [

    // Who can sign up from outside. Key => label shown to admins, and the word used in {role} in the message.
    'registration_roles' => [
        'organization' => ['label' => 'Organizations', 'word' => 'organization', 'hint' => 'Schools, universities, companies and clinics applying to join.'],
        'counselor' => ['label' => 'Counselors', 'word' => 'counselor', 'hint' => 'Registered counselors signing up on their own.'],
        'individual' => ['label' => 'Individuals', 'word' => 'individual', 'hint' => 'Clients creating their own account. They sign up in the mobile app; this switch closes that. The web sign-up form is switched off (and would obey this switch too if it were turned on).'],
    ],

    // {role} is replaced with the word above ("account" when every sign-up is closed).
    'default_messages' => [
        'registration_closed' => 'We are not accepting new {role} sign-ups right now. Please check back soon.',
        'maintenance' => 'MyKaunsel is down for maintenance. We will be back shortly. Thank you for your patience.',
    ],

    'message_max_length' => 500,

    // Maintenance can switch itself off at a set time (the scheduler checks every minute: cron must run `schedule:run`).
    // The time must be at least a minute away and no further than this many days.
    'maintenance_max_days' => 30,

    // Visitors are told when MyKaunsel is expected back in this timezone (they have none of their own), and the time is labelled.
    'public_timezone' => env('PUBLIC_TIMEZONE', 'Asia/Kuala_Lumpur'),

    // Retry-After (seconds) while maintenance has no reopen time.
    'retry_after_default' => 600,

    // Route names that still work during maintenance, so a Platform Admin can always sign in. EVERY route of the sign-in
    // flow must be named and listed here (a test fails if a new one is forgotten): an unnamed or unlisted one locks admins out.
    'maintenance_allowed_routes' => [
        'login', 'login.store', 'logout',
        'two-factor.challenge', 'two-factor.challenge.store',
        'password.request', 'password.email', 'password.reset', 'password.store',
        'context.select', 'context.store',
        // The app asks this first, to show the maintenance / closed-sign-up message instead of a plain error.
        'api.v1.status',
    ],

    // Right after signing in everybody is sent to /dashboard, before any role has been chosen. For a person who holds an
    // active Platform Admin role (and nobody else) these routes also work during maintenance, so the sign-in can finish:
    // /dashboard -> role chooser -> the Platform Admin area. Everyone else is stopped at the maintenance page as before.
    'maintenance_admin_entry_routes' => ['dashboard'],
];
