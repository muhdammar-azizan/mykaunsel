<?php

/*
 * Account page defaults. Notification keys map to users.notification_preferences;
 * any code that sends one of these emails must check User::wantsNotification($key).
 */
return [
    'default_timezone' => 'Asia/Kuala_Lumpur',

    // Disk for profile photos. Switch to an S3/R2 disk by changing AVATAR_DISK in .env.
    'avatar_disk' => env('AVATAR_DISK', 'public'),
    'avatar_max_kb' => 2048,
    'avatar_size' => 512,

    // Minutes the "confirm your new email" link stays valid.
    'email_verification_minutes' => 60,

    // audit_logs actions shown in "Recent security activity" (action => label).
    'security_events' => [
        'auth.login' => 'Signed in',
        'auth.login_failed' => 'Failed sign-in attempt',
        'account.password_changed' => 'Password changed',
        'account.email_change_requested' => 'Email change requested',
        'account.email_changed' => 'Email address changed',
        'account.two_factor_failed' => 'Failed two-factor attempt',
        'account.recovery_code_used' => 'Recovery code used to sign in',
        'account.two_factor_enabled' => 'Two-factor authentication turned on',
        'account.two_factor_disabled' => 'Two-factor authentication turned off',
        'account.recovery_codes_regenerated' => 'Recovery codes regenerated',
        'account.other_sessions_revoked' => 'Signed out other sessions',
    ],

    // The e-mails an ordinary person (a client in the app) can switch off, in Account > Notifications of the app. The same rule as below: whatever
    // sends one of these must check User::wantsNotification($key). Essential notices (a session cancelled for them by the platform) are NOT here:
    // those are always sent.
    'client_notifications' => [
        'booking_emails' => [
            'label' => 'Bookings',
            'description' => 'Confirmations, answers to your time requests and changes to your sessions.',
            'default' => true,
        ],
    ],

    'notifications' => [
        'new_organization_application' => [
            'label' => 'New organization application',
            'description' => 'Email me when an organization registers and is waiting for approval.',
            'default' => true,
        ],
        'new_counselor_application' => [
            'label' => 'New counselor application',
            'description' => 'Email me when a counselor submits credentials for verification.',
            'default' => true,
        ],
        'escalated_complaint' => [
            'label' => 'Escalated complaint',
            'description' => 'Email me when a complaint is escalated to the platform.',
            'default' => true,
        ],
        'weekly_summary' => [
            'label' => 'Weekly platform summary',
            'description' => 'A weekly email with pending approvals, complaints and platform totals.',
            'default' => true,
        ],
    ],
];
