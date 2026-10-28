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

        // How long an accepted, expired or revoked invitation keeps the name and email it was sent to. After this many days they are
        // erased (the row stays as an empty record). A chosen default, not a legal rule: change it here. See admin-invitations:purge.
        'retention_days' => (int) env('PLATFORM_ADMIN_INVITATION_RETENTION_DAYS', 30),

        // Wrong passwords on the accept page of ONE invitation before it is locked for a while.
        'accept_attempts' => 5,
        'accept_lock_minutes' => 15,
    ],

];
