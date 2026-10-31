<?php

/*
 * The in-app notification center (the bell). Event-based: "something just happened". It never replaces the Dashboard's state-based
 * "Needs your attention" list. The account-page preferences (config/account.php) control EMAIL only; in-app items are always created.
 * The types themselves are registered in code (App\Support\Inbox\CoreNotificationTypes): later pages add their own without touching the core.
 */
return [

    // Read items are deleted after this many days, unread ones after the second number (a scheduled command; counts only in the audit log).
    'retention' => [
        'read_days' => (int) env('NOTIFICATIONS_READ_DAYS', 90),
        'unread_days' => (int) env('NOTIFICATIONS_UNREAD_DAYS', 180),
    ],

    // How many items the bell's panel shows, and the page size of the full page.
    'panel_items' => 8,
    'page_size' => 25,

    // A coalescing type (repeated duplicate-KB attempts) makes ONE notification per target per this many hours, counting the attempts.
    'coalesce_hours' => 24,

    // Filter groups of the full page.
    'groups' => [
        'applications' => 'Applications',
        'lkm' => 'LKM',
        'complaints' => 'Complaints',
        'admins' => 'Admins',
        'system' => 'System',
        // The mobile app's notifications (App\Support\Inbox\ClientNotificationTypes): never listed on the Platform Admin page.
        'activity' => 'Your activity',
    ],
];
