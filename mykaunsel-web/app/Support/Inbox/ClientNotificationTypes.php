<?php

namespace App\Support\Inbox;

/**
 * The notification types of the MOBILE APP (docs/NOTIFICATIONS.md): what happened to a client that they did not do themselves. They share the
 * table and the center with the Platform Admin ones, in their own group ("activity"), so the admin bell never shows them.
 *
 * A type stores a fixed key, a short label (a counselor's name or an organization's name) and, for a booking or a time request, its id; the API
 * turns that into a public id. Nothing else is stored: no times, reasons or messages. The app writes the sentence from the key (the `title` here is
 * the English fallback). A type with no target page uses none: the app knows where each key leads.
 *
 * Only things the person did not do themselves are here: booking something, or cancelling it, is not news to them.
 */
final class ClientNotificationTypes
{
    public const GROUP = 'activity';

    public static function register(NotificationRegistry $registry): void
    {
        // ---- time requests (Mode B): the counselor answered, or nobody did in time -------------------------------------------------
        $registry->register(new NotificationType('request.approved', self::GROUP, 'Your time request was approved', 'booking_request'));
        $registry->register(new NotificationType('request.declined', self::GROUP, 'Your time request was declined', 'booking_request'));
        $registry->register(new NotificationType('request.expired', self::GROUP, 'Your time request was not answered in time', 'booking_request'));

        // ---- a later session was cancelled for the person (the counselor was suspended, or the membership ended) ------------------
        $registry->register(new NotificationType('booking.cancelled', self::GROUP, 'A session was cancelled', 'booking'));

        // ---- the organization ------------------------------------------------------------------------------------------------------
        $registry->register(new NotificationType('invitation.received', self::GROUP, 'An organization invited you', 'organization'));
        $registry->register(new NotificationType('membership.approved', self::GROUP, 'Your organization approved you', 'organization'));
        $registry->register(new NotificationType('membership.declined', self::GROUP, 'Your organization did not approve your request', 'organization'));
        $registry->register(new NotificationType('membership.ended', self::GROUP, 'Your membership of an organization ended', 'organization'));

        // ---- the Feed: the writing is gone, so there is nothing to open ------------------------------------------------------------
        $registry->register(new NotificationType('feed.removed', self::GROUP, 'Something you wrote on the Feed was removed'));
        $registry->register(new NotificationType('feed.warned', self::GROUP, 'You received a warning about the Feed'));
    }
}
