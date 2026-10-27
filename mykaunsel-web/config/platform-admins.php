<?php

return [

    /*
    | Invitations to become a Platform Admin (Platform Admin → Settings → Platform Admins).
    */
    'invitation' => [
        // How long the emailed link works. The link carries its own expiry as well (it is a signed link).
        'expires_hours' => (int) env('PLATFORM_ADMIN_INVITATION_HOURS', 48),

        // "Send again" can be used once a minute, and at most this many times in all for one invitation (the first send counts).
        'resend_cooldown_seconds' => 60,
        'max_sends' => 5,

        // New invitations one admin can send per hour.
        'new_per_admin_per_hour' => 20,

        // Wrong passwords on the accept page of ONE invitation before it is locked for a while.
        'accept_attempts' => 5,
        'accept_lock_minutes' => 15,
    ],

];
