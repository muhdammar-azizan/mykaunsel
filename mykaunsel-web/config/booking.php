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

    // Used only when the slot's organization has no cancellation deadline of its own (organizations.cancellation_deadline_hours).
    'cancellation_default_hours' => (int) env('BOOKING_CANCELLATION_DEFAULT_HOURS', 24),

    'max_reschedules' => (int) env('BOOKING_MAX_RESCHEDULES', 2),

    // How long before the session the meeting link appears in the person's booking.
    'meeting_link_visible_minutes' => (int) env('BOOKING_MEETING_LINK_VISIBLE_MINUTES', 15),

    // An Idempotency-Key (and the answer kept with it) is forgotten after this many hours.
    'idempotency_ttl_hours' => (int) env('BOOKING_IDEMPOTENCY_TTL_HOURS', 24),

    // A request that never finished (the server died) stops blocking its key after this many seconds.
    'idempotency_processing_seconds' => 60,

    // "Starting soon" badge: a confirmed session that starts within this many minutes (or is under way).
    'starting_soon_minutes' => (int) env('BOOKING_STARTING_SOON_MINUTES', 60),

    // Rate limits per person.
    'create_rate_per_minute' => (int) env('BOOKING_CREATE_RATE_PER_MINUTE', 10),
    'create_rate_per_hour' => (int) env('BOOKING_CREATE_RATE_PER_HOUR', 60),
    'cancel_rate_per_minute' => (int) env('BOOKING_CANCEL_RATE_PER_MINUTE', 10),
    'cancel_rate_per_hour' => (int) env('BOOKING_CANCEL_RATE_PER_HOUR', 60),
    'reschedule_rate_per_minute' => (int) env('BOOKING_RESCHEDULE_RATE_PER_MINUTE', 10),
    'reschedule_rate_per_hour' => (int) env('BOOKING_RESCHEDULE_RATE_PER_HOUR', 60),
    'preview_rate_per_minute' => (int) env('BOOKING_PREVIEW_RATE_PER_MINUTE', 60),
    'preview_rate_per_hour' => (int) env('BOOKING_PREVIEW_RATE_PER_HOUR', 600),

    // The longest stretch of days one availability request may ask for.
    'availability_days_per_request' => 14,
];
