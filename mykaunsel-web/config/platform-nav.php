<?php

/*
 * Sidebar menu for the Platform Admin area. To add a page, add one item here
 * (route name + label + SVG path) — the sidebar picks it up everywhere.
 */
return [
    ['group' => null, 'items' => [
        ['label' => 'Dashboard', 'route' => 'platform.dashboard', 'icon' => 'M3 12h5v8H3zM10 7h5v13h-5zM17 3h5v17h-5z'],
    ]],
    ['group' => 'Operations', 'items' => [
        ['label' => 'Organizations', 'route' => 'platform.organizations', 'icon' => 'M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18zM6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2M10 6h4M10 10h4M10 14h4'],
        ['label' => 'Counselors', 'route' => 'platform.counselors', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M17 11l2 2 4-4M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    ]],
    ['group' => 'Trust & Safety', 'items' => [
        ['label' => 'Complaints', 'route' => 'platform.complaints', 'icon' => 'M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'],
    ]],
];
