<?php

namespace App\Support\Inbox;

/**
 * The in-app notification types of a COUNSELOR (the bell of the counselor portal, docs/NOTIFICATIONS.md): what happened that they did not do
 * themselves. They sit in their own group ("portal"), so neither the Platform Admin page nor the mobile app ever lists them.
 *
 * A notification stores only a fixed key, a short label (the date and time of the session or request: no name, no note, no reason) and the id of
 * the booking, request or profile it is about. No URL is stored: each type opens a list page of the portal (never a single record, so a deleted
 * record can never send anyone anywhere odd). The e-mail stays: this is the second channel, written from the same place that sends it.
 */
final class CounselorNotificationTypes
{
    public const GROUP = 'portal';

    public static function register(NotificationRegistry $registry): void
    {
        // ---- sessions: a client did something -------------------------------------------------------------------------------------
        $registry->register(new NotificationType('session.booked', self::GROUP, 'A client booked a session', 'booking', fn () => route('counselor.appointments')));
        $registry->register(new NotificationType('session.cancelled_by_client', self::GROUP, 'A client cancelled a session', 'booking', fn () => route('counselor.appointments')));
        $registry->register(new NotificationType('session.rescheduled', self::GROUP, 'A client moved a session to another time', 'booking', fn () => route('counselor.appointments')));

        // ---- time requests (Mode B) ------------------------------------------------------------------------------------------------
        $registry->register(new NotificationType('request.received', self::GROUP, 'A client asked for a time', 'booking_request', fn () => route('counselor.requests')));
        $registry->register(new NotificationType('request.lapsed', self::GROUP, 'A time request closed because it was not answered in time', 'booking_request', fn () => route('counselor.requests', ['tab' => 'answered'])));
        $registry->register(new NotificationType('request.withdrawn', self::GROUP, 'A client took back a time request', 'booking_request', fn () => route('counselor.requests', ['tab' => 'answered'])));

        // ---- the counselor's own standing -------------------------------------------------------------------------------------------
        $registry->register(new NotificationType('pa.expiring', self::GROUP, 'Your practising certificate (PA) is about to expire', 'counselor_profile', fn () => route('counselor.profile')));
        $registry->register(new NotificationType('profile.suspended', self::GROUP, 'Your profile was suspended', 'counselor_profile', fn () => route('counselor.dashboard')));
        $registry->register(new NotificationType('profile.reactivated', self::GROUP, 'Your profile is active again', 'counselor_profile', fn () => route('counselor.dashboard')));
    }
}
