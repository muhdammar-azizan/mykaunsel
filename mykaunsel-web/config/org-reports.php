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
];
