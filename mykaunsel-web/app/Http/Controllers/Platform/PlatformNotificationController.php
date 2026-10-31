<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use App\Support\Inbox\NotificationRegistry;
use App\Support\Inbox\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Platform Admin → the bell and the notifications page. Platform Admins only (the route group), and only ever the signed-in admin's OWN
 * notifications: every lookup goes through NotificationCenter, which is scoped to the owner, so another admin's id is simply a 404.
 *
 * Opening, reading and marking notifications is not audited. The page a notification opens comes from the type registry, never from
 * what is stored, so nothing here can redirect anywhere the registry does not define.
 */
class PlatformNotificationController extends Controller
{
    public function __construct(private readonly NotificationCenter $center, private readonly NotificationRegistry $registry) {}

    /** The route-name prefix of this controller's pages: the portal subclass uses its own routes and nothing else. */
    protected function prefix(): string
    {
        return 'platform';
    }

    /** The bell's number, for the polling. One indexed count query. */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['count' => $this->center->unreadCount($request->user())])->header('Cache-Control', 'no-store');
    }

    /** The panel's contents (HTML built and escaped here), fetched only when the bell is opened: the latest items, Today and Earlier. */
    public function panel(Request $request): Response
    {
        $user = $request->user();
        $items = $this->center->latest($user, (int) config('notifications.panel_items'));
        $groups = $this->grouped($items, $user->display_timezone);

        return response()->view('platform.notifications._panel', ['groups' => $groups, 'empty' => $items->isEmpty(), 'p' => $this->prefix()])
            ->header('X-Unread-Count', (string) $this->center->unreadCount($user))
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Mark the admin's own unread notifications as read and say how many that was. From the bell (a script asking for JSON) it is
     * every unread one; from the page it can be limited to the type group being looked at.
     */
    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $group = $this->group($request->input('group'));

        $marked = $this->center->markAllRead($user, $group === null ? null : $this->registry->keysInGroup($group));

        if ($request->expectsJson()) {
            return response()->json(['marked' => $marked, 'count' => $this->center->unreadCount($user)])->header('Cache-Control', 'no-store');
        }

        $where = $group === null ? '' : ' in '.config('notifications.groups')[$group];

        return redirect()->route($this->prefix().'.notifications', $this->pageQuery($request))->with('status', $marked === 0
            ? 'Nothing to mark: everything'.$where.' is already read.'
            : "Marked {$marked} ".($marked === 1 ? 'notification' : 'notifications').$where.' as read.');
    }

    /** Mark one of my own as read / unread, from the page. Not found (or not yours) is a 404. */
    public function markRead(Request $request, int $notification): RedirectResponse
    {
        abort_unless($this->center->markRead($request->user(), $notification), 404);

        return redirect()->route($this->prefix().'.notifications', $this->pageQuery($request));
    }

    public function markUnread(Request $request, int $notification): RedirectResponse
    {
        abort_unless($this->center->markUnread($request->user(), $notification), 404);

        return redirect()->route($this->prefix().'.notifications', $this->pageQuery($request));
    }

    /**
     * Click on an item: mark it read and go to its target. Not found (or not yours) is a 404. If the target no longer exists, the admin
     * lands on the notifications page with a short message instead of a broken page.
     */
    public function open(Request $request, int $notification): RedirectResponse
    {
        $user = $request->user();
        $row = $this->center->find($user, $notification);
        abort_if($row === null, 404);

        $this->center->markRead($user, $row->id);

        if (! $this->registry->has($row->type)) {
            return $this->goneBack();
        }

        $type = $this->registry->get($row->type);

        if ($type->targetType !== null && ($row->target_id === null || ($type->states([$row->target_id])[$row->target_id] ?? NotificationType::GONE) === NotificationType::GONE)) {
            return $this->goneBack();
        }

        return redirect()->to($type->url($row->target_id) ?? route($this->prefix().'.notifications'));
    }

    /** The full page: All / Unread, a filter by type group, 25 per page, with the three kinds of empty state. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';
        $group = $this->group($request->query('group'));

        $base = fn () => UserNotification::query()->ownedBy($user)
            ->when($group !== null, fn ($query) => $query->whereIn('type', $this->registry->keysInGroup($group)));

        $page = ($tab === 'unread' ? $base()->unread() : $base())
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate((int) config('notifications.page_size'))->withQueryString();

        // A page number past the end (for example after marking things read on the Unread tab): go to the last real page.
        if ($page->isEmpty() && $page->currentPage() > 1) {
            return redirect()->route($this->prefix().'.notifications', array_filter(['tab' => $tab === 'unread' ? 'unread' : null, 'group' => $group, 'page' => $page->lastPage()]));
        }

        $has = UserNotification::query()->ownedBy($user)->exists();

        return view('platform.notifications.index', [
            'items' => $this->present($page->getCollection(), $user->display_timezone),
            'page' => $page,
            'tab' => $tab,
            'group' => $group,
            'groups' => collect(config('notifications.groups'))->except(\App\Support\Inbox\ClientNotificationTypes::GROUP)->all(),
            'counts' => ['all' => $base()->count(), 'unread' => $base()->unread()->count()],
            'empty' => ! $has ? 'none' : ($page->isEmpty() ? ($group === null && $tab === 'unread' ? 'no_unread' : 'no_match') : null),
            'unreadTotal' => $this->center->unreadCount($user),
            'readDays' => (int) config('notifications.retention.read_days'),
            'unreadDays' => (int) config('notifications.retention.unread_days'),
            'query' => $this->pageQuery($request),
        ]);
    }

    protected function goneBack(): RedirectResponse
    {
        return redirect()->route($this->prefix().'.notifications')->with('notice', 'That item no longer exists.');
    }

    /** A known type group, or null (anything else typed into the address is ignored). */
    protected function group(mixed $value): ?string
    {
        return is_string($value) && $value !== \App\Support\Inbox\ClientNotificationTypes::GROUP && array_key_exists($value, config('notifications.groups')) ? $value : null;
    }

    /**
     * Where to come back to on the page: only the tab, the group and the page number, each checked. Nothing else from the request is
     * carried, so it cannot be made to point anywhere else.
     *
     * @return array<string, string|int>
     */
    protected function pageQuery(Request $request): array
    {
        $page = $request->input('page', $request->query('page'));

        return array_filter([
            'tab' => $request->input('tab', $request->query('tab')) === 'unread' ? 'unread' : null,
            'group' => $this->group($request->input('group', $request->query('group'))),
            'page' => is_numeric($page) && (int) $page > 1 ? (int) $page : null,
        ]);
    }

    /**
     * What the panel and the page need for each item, with "Already reviewed" resolved for the whole list at once (no query per item).
     *
     * @param  Collection<int, UserNotification>  $items
     * @return list<array<string, mixed>>
     */
    protected function present(Collection $items, string $timezone): array
    {
        $states = $this->registry->states($items);
        $now = CarbonImmutable::now($timezone);

        return $items->map(function (UserNotification $item) use ($states, $timezone, $now) {
            $type = $this->registry->has($item->type) ? $this->registry->get($item->type) : null;
            $at = CarbonImmutable::instance($item->occurred_at)->setTimezone($timezone);
            $state = $states[$item->id] ?? NotificationType::OPEN;

            return [
                'id' => $item->id,
                'unread' => $item->isUnread(),
                'title' => $type?->title ?? 'Notification',
                'label' => $item->label,
                'detail' => $type?->detail($item->count),
                'state' => $state,
                'handled' => $state === NotificationType::HANDLED,
                'gone' => $state === NotificationType::GONE,
                'today' => $at->isSameDay($now),
                'ago' => $at->diffForHumans(),
                'full_time' => $at->format('j M Y, g:i A'),
                'iso' => $at->toIso8601String(),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, UserNotification>  $items
     * @return array<string, list<array<string, mixed>>> "Today" and "Earlier" (empty groups left out)
     */
    protected function grouped(Collection $items, string $timezone): array
    {
        $presented = collect($this->present($items, $timezone));

        return array_filter([
            'Today' => $presented->where('today', true)->values()->all(),
            'Earlier' => $presented->where('today', false)->values()->all(),
        ]);
    }
}
