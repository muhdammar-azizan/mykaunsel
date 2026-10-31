<?php

/**
 * The sidebar of the counselor and organization admin portals, by role (current_role in the session). Platform Admin has its own (platform-nav.php).
 * An item whose route does not exist yet is shown as "Soon" and is not clickable: it becomes a link the day its route is added.
 * 'match' is the route-name pattern that keeps the item highlighted on its child pages. Icons are 24x24 line paths.
 */
return [
    'counselor' => [
        ['label' => 'Dashboard', 'route' => 'counselor.dashboard', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'],
        ['label' => 'Bookings', 'route' => 'counselor.appointments', 'match' => 'counselor.appointments*', 'icon' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM9 16l2 2 4-4'],
        ['label' => 'Schedule', 'route' => 'counselor.planner', 'match' => 'counselor.planner*', 'icon' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2'],
        ['label' => 'Session notes', 'route' => 'counselor.notes', 'match' => 'counselor.notes*', 'icon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8'],
        ['label' => 'Reviews', 'route' => 'counselor.reviews', 'match' => 'counselor.reviews*', 'icon' => 'm12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z'],
        ['label' => 'Profile', 'route' => 'counselor.profile', 'match' => 'counselor.profile*', 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.5-6 8-6s8 2 8 6'],
    ],

    'org_admin' => [
        ['label' => 'Dashboard', 'route' => 'org.dashboard', 'icon' => 'M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z'],
        ['label' => 'Members', 'route' => 'org.members', 'match' => 'org.members*', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        ['label' => 'Counselors', 'route' => 'org.counselors', 'match' => 'org.counselors*', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ['label' => 'Reviews', 'route' => 'org.reviews', 'match' => 'org.reviews*', 'icon' => 'm12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z'],
        ['label' => 'Reports', 'route' => 'org.reports', 'match' => 'org.reports*', 'icon' => 'M3 3v18h18M7 15l4-5 4 3 5-7'],
        ['label' => 'Subscription', 'route' => 'org.subscription', 'match' => 'org.subscription*', 'icon' => 'M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM2 10h20'],
        ['label' => 'Settings', 'route' => 'org.settings', 'match' => 'org.settings*', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
    ],
];
