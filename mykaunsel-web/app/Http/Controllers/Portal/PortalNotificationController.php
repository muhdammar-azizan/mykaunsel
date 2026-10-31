<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Platform\PlatformNotificationController;
use App\Models\UserNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Counselor and organization admin portals → the bell and the notifications page. It is the Platform Admin controller with its own routes
 * (see prefix()) and page: the same rules, so only the signed-in person's OWN notifications are ever read, marked or opened, and where an
 * item leads comes from the type registry, never from what is stored.
 */
class PortalNotificationController extends PlatformNotificationController
{
    protected function prefix(): string
    {
        return 'portal';
    }

    /** The full page: All / Unread, 25 per page. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'unread' ? 'unread' : 'all';

        $base = fn () => UserNotification::query()->ownedBy($user);

        $page = ($tab === 'unread' ? $base()->unread() : $base())
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate((int) config('notifications.page_size'))->withQueryString();

        if ($page->isEmpty() && $page->currentPage() > 1) {
            return redirect()->route('portal.notifications', array_filter(['tab' => $tab === 'unread' ? 'unread' : null, 'page' => $page->lastPage()]));
        }

        return view('portal.notifications.index', [
            'items' => $this->present($page->getCollection(), $user->display_timezone),
            'page' => $page,
            'tab' => $tab,
            'counts' => ['all' => $base()->count(), 'unread' => $base()->unread()->count()],
        ]);
    }
}
