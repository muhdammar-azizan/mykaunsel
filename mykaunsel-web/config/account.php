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
        'login' => 'Signed in',
        'login_failed' => 'Failed sign-in attempt',
        'password_changed' => 'Password changed',
        'email_change_requested' => 'Email change requested',
        'email_changed' => 'Email address changed',
        'two_factor_failed' => 'Failed two-factor attempt',
        'recovery_code_used' => 'Recovery code used to sign in',
        'two_factor_enabled' => 'Two-factor authentication turned on',
        'two_factor_disabled' => 'Two-factor authentication turned off',
        'recovery_codes_regenerated' => 'Recovery codes regenerated',
        'other_sessions_revoked' => 'Signed out other sessions',
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
