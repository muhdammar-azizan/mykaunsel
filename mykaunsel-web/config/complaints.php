<?php

/*
 * Platform Admin → Complaints (docs/COMPLAINTS.md). Each number is a decision that can be changed here.
 */
return [

    // A second warning on the SAME complaint is refused for this long (a double click, or two admins at once).
    'warning_cooldown_hours' => 24,

    // History card on a complaint: this many complaints about the same counselor within this many days is shown as a pattern.
    // It is information for the admin only; nothing happens automatically.
    'pattern' => ['count' => 3, 'days' => 90],

    // Retention: a CLOSED complaint's text (what was reported, the closing reason, the warning messages) is erased this many
    // years after it was closed. The record itself (reference, status, dates, outcome) stays. Open complaints are never touched.
    'retention' => ['closed_years' => 3],

    'per_page' => 10,
];
