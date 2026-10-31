<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use App\Support\Inbox\ClientNotificationTypes;
use App\Support\Inbox\NotificationRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The notifications of the signed-in person in the app (docs/NOTIFICATIONS.md). Always their own; there is no user id in any route. Only the app's
 * own group of types is listed and counted: anything else in the table (a Platform Admin's) is not this API's business.
 *
 * An item carries its type key (the app writes the sentence), the label, how many times it happened, when, whether it was read, and, for a booking or
 * a time request, that thing's PUBLIC id (null if it no longer exists). No counter, no e-mail, no text from anyone.
 */
class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private NotificationCenter $center, private NotificationRegistry $registry) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $types = $this->types();
        $page = $this->center->pageOf($user, $types, (int) $request->query('page', 1), self::PER_PAGE);
        $items = $page->getCollection();

        $bookings = Booking::query()->whereIn('id', $items->where('target_type', 'booking')->pluck('target_id'))->pluck('public_id', 'id');
        $requests = BookingRequest::query()->whereIn('id', $items->where('target_type', 'booking_request')->pluck('target_id'))->pluck('public_id', 'id');

        return $this->private(response()->json([
            'data' => $items->map(fn (UserNotification $n) => $this->present($n, $bookings->all(), $requests->all()))->values()->all(),
            'unread' => $this->center->unreadCount($user, $types),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]));
    }

    /** The bell's number, on its own: cheap enough to ask whenever the app comes back. */
    public function unread(Request $request): JsonResponse
    {
        return $this->private(response()->json(['unread' => $this->center->unreadCount($request->user(), $this->types())]));
    }

    /** Marks one read. Safe to repeat. Someone else's and an unknown one are the same 404. */
    public function read(Request $request, string $id): JsonResponse
    {
        if (! $this->center->markReadByPublicId($request->user(), $id)) {
            return $this->private(response()->json(['message' => 'This notification was not found.', 'code' => 'notification_not_found'], 404));
        }

        return $this->private(response()->json(['unread' => $this->center->unreadCount($request->user(), $this->types())]));
    }

    public function readAll(Request $request): JsonResponse
    {
        $marked = $this->center->markAllRead($request->user(), $this->types());

        return $this->private(response()->json(['marked' => $marked, 'unread' => 0]));
    }

    /** @return list<string> */
    private function types(): array
    {
        return $this->registry->keysInGroup(ClientNotificationTypes::GROUP);
    }

    /**
     * @param  array<int, string>  $bookings  internal id => public id
     * @param  array<int, string>  $requests
     * @return array<string, mixed>
     */
    private function present(UserNotification $n, array $bookings, array $requests): array
    {
        $target = match ($n->target_type) {
            'booking' => isset($bookings[$n->target_id]) ? ['kind' => 'booking', 'id' => $bookings[$n->target_id]] : null,
            'booking_request' => isset($requests[$n->target_id]) ? ['kind' => 'booking_request', 'id' => $requests[$n->target_id]] : null,
            default => null,
        };

        return [
            'id' => $n->public_id,
            'type' => $n->type,
            'title' => $this->registry->has($n->type) ? $this->registry->get($n->type)->title : 'Notification',
            'label' => $n->label,
            'count' => $n->count,
            'occurred_at' => $n->occurred_at->toIso8601String(),
            'read' => $n->read_at !== null,
            'target' => $target,
        ];
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
