<?php

/*
 * Account page defaults. Notification keys map to users.notification_preferences;
 * any code that sends one of these emails must check User::wantsNotification($key).
 */
return [
    'default_timezone' => 'Asia/Kuala_Lumpur',

    'notifications' => [
        'new_organization_application' => ['label' => 'New organization application', 'default' => true],
        'new_counselor_application' => ['label' => 'New counselor application', 'default' => true],
        'escalated_complaint' => ['label' => 'Escalated complaint', 'default' => true],
        'weekly_summary' => ['label' => 'Weekly platform summary', 'default' => true],
    ],
];
