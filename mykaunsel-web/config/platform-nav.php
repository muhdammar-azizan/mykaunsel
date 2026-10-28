<?php

/*
 * Sidebar menu for the Platform Admin area. To add a page, add one item here
 * (label + SVG path + route name). Items without a 'route' show as "Soon" and
 * are not clickable — give them a route when the page is built.
 */
return [
    ['group' => null, 'items' => [
        ['label' => 'Dashboard', 'route' => 'platform.dashboard', 'icon' => 'M3 12h5v8H3zM10 7h5v13h-5zM17 3h5v17h-5z'],
    ]],
    ['group' => 'Operations', 'items' => [
        ['label' => 'Organizations', 'route' => 'platform.organizations', 'icon' => 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18zM6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M10 6h4M10 10h4M10 14h4'],
        ['label' => 'Counselors', 'route' => 'platform.counselors', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
        ['label' => 'LKM Directory', 'route' => 'platform.lkm', 'icon' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'],
    ]],
    ['group' => 'Trust & Safety', 'items' => [
        ['label' => 'Complaints', 'route' => 'platform.complaints', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
        ['label' => 'Moderate Feed', 'route' => null, 'icon' => 'M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4zM8 8h8M8 12h5'],
    ]],
    ['group' => 'Platform', 'items' => [
        ['label' => 'Analytics', 'route' => 'platform.analytics', 'icon' => 'M3 3v18h18M7 15l4-5 4 3 5-7'],
        ['label' => 'Audit Log', 'route' => 'platform.audit-log', 'icon' => 'M9 12h6M9 16h6M9 8h6M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z'],
        ['label' => 'Pricing & Promotions', 'route' => 'platform.pricing', 'icon' => 'M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2zM2 10h20M6 15h3'],
    ]],
    ['group' => 'Settings', 'items' => [
        ['label' => 'Registration Control', 'route' => 'platform.registration', 'icon' => 'M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0M14 4v4M8 10v4M16 16v4'],
        ['label' => 'Platform Admins', 'route' => 'platform.admins', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
        ['label' => 'Account', 'route' => 'platform.account', 'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 3.5-6 8-6s8 2 8 6'],
    ]],
];
