<?php

/*
 * Booking rules shared by the places that decide whether a time can be booked: the mobile app's availability screen, the counselor's
 * profile and search (so they never show a time the booking screen would refuse), and later the confirm step. See docs/AVAILABILITY.md.
 */
return [

    // A slot that starts sooner than this many hours from now cannot be booked (and is not shown).
    'min_lead_hours' => (int) env('BOOKING_MIN_LEAD_HOURS', 2),

    // How far ahead a slot can be booked, in days from today (Malaysian time). Slots after that day are not shown.
    'horizon_days' => (int) env('BOOKING_HORIZON_DAYS', 30),

    // How many upcoming sessions one person may hold at once. Enforced when a booking is CONFIRMED (a later step), not when browsing.
    'max_upcoming_sessions' => (int) env('BOOKING_MAX_UPCOMING_SESSIONS', 3),

    // The longest stretch of days one availability request may ask for.
    'availability_days_per_request' => 14,
];
