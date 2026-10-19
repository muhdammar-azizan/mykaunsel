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

    'notifications' => [
        'new_organization_application' => ['label' => 'New organization application', 'default' => true],
        'new_counselor_application' => ['label' => 'New counselor application', 'default' => true],
        'escalated_complaint' => ['label' => 'Escalated complaint', 'default' => true],
        'weekly_summary' => ['label' => 'Weekly platform summary', 'default' => true],
    ],
];
