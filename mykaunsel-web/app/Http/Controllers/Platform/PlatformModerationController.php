<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\ModerationNothingToDo;
use App\Http\Controllers\Controller;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Services\FeedModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform Admin → Moderate Feed. Reports from the app wait here, one row per reported post or comment. Only Platform Admins read the writing.
 * Reporters are never shown (a count only).
 */
class PlatformModerationController extends Controller
{
    private const PER_PAGE = 15;

    public function index(Request $request): View|RedirectResponse
    {
        $tab = $request->query('tab') === 'handled' ? 'handled' : 'pending';
        $handled = $tab === 'handled';

        $groups = FeedReport::query()
            ->when($handled, fn ($q) => $q->where('status', '!=', 'pending'), fn ($q) => $q->where('status', 'pending'))
            ->selectRaw('feed_post_id, feed_comment_id, status, count(*) as reports, min(created_at) as first_at, max(handled_at) as handled_at')
            ->groupBy('feed_post_id', 'feed_comment_id', 'status')
            ->when($handled, fn ($q) => $q->orderByDesc('handled_at'), fn ($q) => $q->orderBy('first_at'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        if ($groups->isEmpty() && $groups->currentPage() > 1) {
            return redirect()->route('platform.moderation', ['tab' => $tab === 'handled' ? 'handled' : null, 'page' => $groups->lastPage()]);
        }

        $posts = FeedPost::query()->with('user:id,name')->findMany($groups->pluck('feed_post_id')->filter()->unique())->keyBy('id');
        $comments = FeedComment::query()->with('user:id,name')->findMany($groups->pluck('feed_comment_id')->filter()->unique())->keyBy('id');

        return view('platform.moderation.index', [
            'tab' => $tab,
            'counts' => ['pending' => $this->count('pending'), 'handled' => $this->count('handled')],
            'groups' => $groups,
            'posts' => $posts,
            'comments' => $comments,
            'reasons' => $this->reasons($groups, $handled),
            'timezone' => $request->user()->display_timezone,
        ]);
    }

    public function dismiss(Request $request, string $kind, int $id, FeedModerationService $moderation): RedirectResponse
    {
        return $this->act(fn () => $moderation->dismiss($kind, $id, $request->user()), 'Reports set aside. The '.$kind.' stays.');
    }

    public function remove(Request $request, string $kind, int $id, FeedModerationService $moderation): RedirectResponse
    {
        $data = $request->validateWithBag('remove'.$kind.$id, ['note' => ['nullable', 'string', 'max:1000']]);

        return $this->act(fn () => $moderation->remove($kind, $id, $request->user(), filled($data['note'] ?? null) ? trim($data['note']) : null), ucfirst($kind).' removed. Its author has been emailed.');
    }

    public function warn(Request $request, string $kind, int $id, FeedModerationService $moderation): RedirectResponse
    {
        $data = $request->validateWithBag('warn'.$kind.$id, ['message' => ['required', 'string', 'min:10', 'max:1000']], [
            'message.required' => 'Write the warning — it is emailed to the author.',
            'message.min' => 'Give a little more detail (at least 10 characters).',
        ]);

        return $this->act(fn () => $moderation->warn($kind, $id, $request->user(), trim($data['message'])), 'Warning emailed to the author.');
    }

    /** Runs the action; if another admin was first, says so instead of failing. */
    private function act(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (ModerationNothingToDo $e) {
            return back()->with('error', $e->getMessage().' Nothing was changed.');
        }

        return back()->with('status', $done);
    }

    private function count(string $tab): int
    {
        return FeedReport::query()
            ->when($tab === 'handled', fn ($q) => $q->where('status', '!=', 'pending'), fn ($q) => $q->where('status', 'pending'))
            ->selectRaw('feed_post_id, feed_comment_id, status')->groupBy('feed_post_id', 'feed_comment_id', 'status')
            ->get()->count();
    }

    /**
     * How many reports gave each reason, for the posts and comments on this page.
     *
     * @return array<string, array<string, int>> keyed "post:ID" / "comment:ID", then "status|reason"
     */
    private function reasons($groups, bool $handled): array
    {
        $postIds = $groups->pluck('feed_post_id')->filter()->unique()->all();
        $commentIds = $groups->pluck('feed_comment_id')->filter()->unique()->all();

        $rows = FeedReport::query()
            ->when($handled, fn ($q) => $q->where('status', '!=', 'pending'), fn ($q) => $q->where('status', 'pending'))
            ->where(fn ($q) => $q->whereIn('feed_post_id', $postIds)->orWhereIn('feed_comment_id', $commentIds))
            ->selectRaw('feed_post_id, feed_comment_id, status, reason, count(*) as n')
            ->groupBy('feed_post_id', 'feed_comment_id', 'status', 'reason')->get();

        $out = [];

        foreach ($rows as $row) {
            $key = $row->feed_post_id ? 'post:'.$row->feed_post_id : 'comment:'.$row->feed_comment_id;
            $out[$key][$row->status.'|'.$row->reason] = (int) $row->n;
        }

        return $out;
    }
}
