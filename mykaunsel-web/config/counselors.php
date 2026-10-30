<?php

/*
 * Counselor verification settings (Platform Admin → Counselors).
 */
return [

    // Where KB / PA / ID documents live. Must be a PRIVATE disk (see config/filesystems.php) — switch to a private
    // S3/R2 disk by pointing this at it. Nothing under it is ever reachable by a public URL.
    'documents_disk' => env('COUNSELOR_DOCUMENTS_DISK', 'counselor_documents'),

    // How long documents are kept (checked daily by `counselors:purge-documents`). A conscious decision to review before launch.
    'retention' => [
        // ID / passport copy: deleted this many days after the final decision (approved, or rejected and not resubmitted).
        'ic_days' => (int) env('COUNSELOR_RETENTION_IC_DAYS', 30),
        // KB + PA certificates are kept for approved counselors as proof of verification; for an application that stays
        // rejected they are deleted this many days after the rejection.
        'rejected_certificates_days' => (int) env('COUNSELOR_RETENTION_REJECTED_DAYS', 90),
        'chunk' => 200,
    ],

    // Daily practising-certificate (PA) check (`counselors:check-pa-expiry`, 00:10 in the application timezone, i.e. right after
    // the last valid second of the "valid until" day). Counselors get an automatic reminder this many days before the PA
    // runs out (each step once per PA date); once it has expired they are suspended automatically.
    'pa_reminder_days' => [30, 7, 1],

    // What clients can see in the mobile app (see docs/PUBLIC_COUNSELOR_DATA.md for the privacy rules).
    'directory' => [
        'per_page' => 20,

        // Slots are stored as a date and a time with no zone: they are Malaysian time (where every counselor works).
        'slot_timezone' => 'Asia/Kuala_Lumpur',

        // How far ahead slots are shown (and the minimum lead time) is the BOOKING window: config/booking.php. It used to be 60 days here;
        // it is now the same 30 days as the booking screen, so a time shown on a profile can always be booked.

        // Slots listed on a counselor's own page.
        'profile_slots' => 10,

        // Chips on a list card (the profile shows all).
        'card_specializations' => 3,
    ],

    // Platform Admin "Send renewal reminder": at most one email to the same counselor in this many hours.
    'renewal_reminder_cooldown_hours' => 24,
];
