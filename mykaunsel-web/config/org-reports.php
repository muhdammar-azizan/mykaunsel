<?php

/*
 * What an organization admin may see about use (Dashboard now, Reports later). Only counts, never names; docs/PORTALS_PLAN.md.
 */
return [
    // A figure about a group smaller than this (but not zero) is shown as "fewer than N", so nobody can be recognised from it.
    'min_group' => 5,

    // A member waiting for approval this many days or more is shown as a notice on the Dashboard.
    'waiting_notice_days' => 3,

    // The subscription is called "ending soon" this many days before it ends (Organization::subscriptionState uses 30 too).
    'ending_notice_days' => 30,

    // The periods an admin can choose on the report pages: key => [label, months back from the start of this month (0 = this month only)].
    'ranges' => [
        'month' => ['This month', 0],
        '3months' => ['Last 3 months', 2],
        '12months' => ['Last 12 months', 11],
    ],
    'default_range' => '3months',

    // How far ahead a session was booked, in whole days before it: label => [from, to] (to = null for no upper end).
    'lead_buckets' => [
        'The same day' => [0, 0],
        '1 to 2 days ahead' => [1, 2],
        '3 to 7 days ahead' => [3, 7],
        'More than a week ahead' => [8, null],
    ],

];
