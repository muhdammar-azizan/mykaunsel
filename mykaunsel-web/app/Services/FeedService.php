<?php

namespace App\Services;

use App\Exceptions\FeedRefused;
use App\Models\CounselorProfile;
use App\Models\FeedComment;
use App\Models\FeedMute;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\PostReaction;
use App\Models\User;
use App\Services\Chat\CrisisDetector;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * The wellness Feed (docs/FEED.md): everyone who is signed in can write, like and comment, like a small public timeline. Text only, no AI.
 *
 * - People post under their own name (the one in their profile). A verified counselor's posts and comments carry a badge.
 * - A post is visible until its author takes it down (`deleted_at`) or a moderator removes it (`removed_at`, from the Moderate Feed page).
 *   Anything not visible is the same `404` as one that never existed.
 * - A person can mute another: that person's posts and comments no longer show in THEIR lists. Nothing else changes for anyone.
 * - Reports wait in the Moderate Feed queue (`feed_reports`); one person reports one thing once.
 * - Safe to repeat: creating takes a client-made id (a retried post or comment is stored once), reacting twice is one reaction, deleting twice and
 *   reporting twice change nothing more.
 * - Words that suggest the writer may be in danger do NOT stop a post (the same rules as the chat: CrisisDetector): the answer says so and the app
 *   shows where to get help.
 */
class FeedService
{
    public function __construct(private readonly CrisisDetector $crisis) {}

    // =============================================================================================================
    //  Reading
    // =============================================================================================================

    /**
     * The feed, newest first, without people the viewer muted.
     *
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function feed(User $viewer, ?string $cursor): array
    {
        $per = (int) config('feed.per_page');
        $before = $cursor === null ? null : FeedPost::query()->where('public_id', strtolower($cursor))->value('id');

        $posts = FeedPost::query()->visible()
            ->whereNotIn('feed_posts.user_id', $this->mutedBy($viewer))
            ->when($before !== null, fn ($q) => $q->where('feed_posts.id', '<', $before))
            ->with('user:id,name')
            ->withCount(['postReactions as reactions_count', 'comments as comments_count' => fn ($q) => $q->visible()])
            ->withExists(['postReactions as reacted' => fn ($q) => $q->where('user_id', $viewer->getKey())])
            ->orderByDesc('feed_posts.id')->limit($per + 1)->get();

        $more = $posts->count() > $per;
        $posts = $posts->take($per);
        $badges = $this->counselors($posts->pluck('user_id'));

        return [
            'data' => $posts->map(fn (FeedPost $post) => $this->presentPost($post, $viewer, $badges))->values()->all(),
            'next_cursor' => $more ? $posts->last()->public_id : null,
        ];
    }

    /** @return array<string, mixed> */
    public function show(User $viewer, string $publicId): array
    {
        $post = $this->visiblePost($publicId, $viewer);

        return $this->presentPost($post, $viewer, $this->counselors(collect([$post->user_id])));
    }

    /**
     * The comments of one post, oldest first (the order of the conversation), without people the viewer muted.
     *
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function comments(User $viewer, string $postPublicId, ?string $cursor): array
    {
        $post = $this->visiblePost($postPublicId);
        $per = (int) config('feed.per_page');
        $after = $cursor === null ? null : FeedComment::query()->where('public_id', strtolower($cursor))->value('id');

        $rows = FeedComment::query()->visible()->where('feed_post_id', $post->getKey())
            ->whereNotIn('feed_comments.user_id', $this->mutedBy($viewer))
            ->when($after !== null, fn ($q) => $q->where('feed_comments.id', '>', $after))
            ->with('user:id,name')->orderBy('feed_comments.id')->limit($per + 1)->get();

        $more = $rows->count() > $per;
        $rows = $rows->take($per);
        $badges = $this->counselors($rows->pluck('user_id'));

        return [
            'data' => $rows->map(fn (FeedComment $c) => $this->presentComment($c, $viewer, $badges))->values()->all(),
            'next_cursor' => $more ? $rows->last()->public_id : null,
        ];
    }

    // =============================================================================================================
    //  Writing
    // =============================================================================================================

    /**
     * @return array{post: array<string, mixed>, created: bool, crisis: bool, helplines: list<array<string, string>>}
     *
     * @throws FeedRefused
     */
    public function create(User $user, string $text, ?string $clientId): array
    {
        $text = $this->tidy($text);

        if ($clientId !== null && ($same = FeedPost::query()->where('user_id', $user->getKey())->where('client_id', $clientId)->first()) !== null) {
            return $this->wrap($this->presentOne($same, $user), false, $text);
        }

        $this->checkDailyLimit($user);

        try {
            $post = FeedPost::create(['user_id' => $user->getKey(), 'content' => $text, 'client_id' => $clientId]);
        } catch (QueryException $e) {
            // Two sends of the same message at once: the second finds the first.
            $post = $clientId === null ? throw $e : FeedPost::query()->where('user_id', $user->getKey())->where('client_id', $clientId)->firstOr(fn () => throw $e);

            return $this->wrap($this->presentOne($post, $user), false, $text);
        }

        return $this->wrap($this->presentOne($post, $user), true, $text);
    }

    /** @return array<string, mixed> */
    public function update(User $user, string $publicId, string $text): array
    {
        $post = $this->ownPost($user, $publicId);
        $text = $this->tidy($text);

        if ($post->content !== $text) {
            $post->update(['content' => $text, 'edited_at' => now()]);
        }

        return $this->presentOne($post->fresh(), $user);
    }

    /** Takes the post down (and with it its comments from view). Deleting one already deleted changes nothing. */
    public function delete(User $user, string $publicId): void
    {
        $post = FeedPost::query()->where('public_id', strtolower($publicId))->where('user_id', $user->getKey())->whereNull('removed_at')->first()
            ?? throw new FeedRefused('post_not_found');

        if ($post->deleted_at === null) {
            $post->update(['deleted_at' => now()]);
        }
    }

    /**
     * @return array{comment: array<string, mixed>, created: bool, crisis: bool, helplines: list<array<string, string>>}
     *
     * @throws FeedRefused
     */
    public function addComment(User $user, string $postPublicId, string $text, ?string $clientId): array
    {
        $post = $this->visiblePost($postPublicId);
        $text = $this->tidy($text);

        if ($clientId !== null && ($same = FeedComment::query()->where('user_id', $user->getKey())->where('client_id', $clientId)->first()) !== null) {
            return $this->wrapComment($this->presentComment($same->load('user:id,name'), $user, $this->counselors(collect([$user->getKey()]))), false, $text);
        }

        try {
            $comment = FeedComment::create(['feed_post_id' => $post->getKey(), 'user_id' => $user->getKey(), 'content' => $text, 'client_id' => $clientId]);
        } catch (QueryException $e) {
            $comment = $clientId === null ? throw $e : FeedComment::query()->where('user_id', $user->getKey())->where('client_id', $clientId)->firstOr(fn () => throw $e);

            return $this->wrapComment($this->presentComment($comment->load('user:id,name'), $user, $this->counselors(collect([$user->getKey()]))), false, $text);
        }

        return $this->wrapComment($this->presentComment($comment->load('user:id,name'), $user, $this->counselors(collect([$user->getKey()]))), true, $text);
    }

    public function deleteComment(User $user, string $publicId): void
    {
        $comment = FeedComment::query()->where('public_id', strtolower($publicId))->where('user_id', $user->getKey())->whereNull('removed_at')->first()
            ?? throw new FeedRefused('comment_not_found');

        if ($comment->deleted_at === null) {
            $comment->update(['deleted_at' => now()]);
        }
    }

    // =============================================================================================================
    //  Reactions, reports, mutes
    // =============================================================================================================

    /** @return array{count: int, mine: bool} */
    public function react(User $user, string $postPublicId, bool $on): array
    {
        $post = $this->visiblePost($postPublicId);

        if ($on) {
            try {
                PostReaction::query()->firstOrCreate(['feed_post_id' => $post->getKey(), 'user_id' => $user->getKey()]);
            } catch (QueryException) {
                // The same reaction twice at once: it exists, which is what was asked.
            }
        } else {
            PostReaction::query()->where('feed_post_id', $post->getKey())->where('user_id', $user->getKey())->delete();
        }

        return $this->reactions($post, $user);
    }

    /** Reports a post or a comment to the moderators. One person reports one thing once; doing it again changes nothing. */
    public function report(User $user, string $kind, string $publicId, string $reason): void
    {
        if ($kind === 'post') {
            $target = $this->visiblePost($publicId);
            $owner = $target->user_id;
            $keys = ['feed_post_id' => $target->getKey()];
        } else {
            $target = FeedComment::query()->visible()->where('public_id', strtolower($publicId))->whereHas('post', fn ($q) => $q->visible())->first()
                ?? throw new FeedRefused('comment_not_found');
            $owner = $target->user_id;
            $keys = ['feed_comment_id' => $target->getKey()];
        }

        if ($owner === $user->getKey()) {
            throw new FeedRefused('cannot_report_own');
        }

        try {
            FeedReport::query()->firstOrCreate(['user_id' => $user->getKey()] + $keys, ['reason' => $reason, 'status' => 'pending']);
        } catch (QueryException) {
            // Reported twice at once: it is in the queue, which is what was asked.
        }
    }

    /** Hides this post's author from the viewer's feed and comments. @return array{id: string, name: string} */
    public function muteAuthor(User $user, string $postPublicId): array
    {
        $post = $this->visiblePost($postPublicId);

        if ($post->user_id === $user->getKey()) {
            throw new FeedRefused('cannot_mute_self');
        }

        try {
            $mute = FeedMute::query()->firstOrCreate(['user_id' => $user->getKey(), 'muted_user_id' => $post->user_id]);
        } catch (QueryException) {
            $mute = FeedMute::query()->where('user_id', $user->getKey())->where('muted_user_id', $post->user_id)->firstOrFail();
        }

        return ['id' => $mute->public_id, 'name' => (string) $post->user?->name];
    }

    /** @return list<array{id: string, name: string}> */
    public function mutes(User $user): array
    {
        return FeedMute::query()->where('user_id', $user->getKey())->with('muted:id,name')->orderBy('id')->get()
            ->map(fn (FeedMute $m) => ['id' => $m->public_id, 'name' => (string) $m->muted?->name])->all();
    }

    public function unmute(User $user, string $publicId): void
    {
        $deleted = FeedMute::query()->where('user_id', $user->getKey())->where('public_id', strtolower($publicId))->delete();

        if ($deleted === 0) {
            throw new FeedRefused('mute_not_found');
        }
    }

    // =============================================================================================================
    //  Inside
    // =============================================================================================================

    private function tidy(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            throw new FeedRefused('text_empty');
        }

        return $text;
    }

    private function visiblePost(string $publicId, ?User $viewer = null): FeedPost
    {
        $query = FeedPost::query()->visible()->where('public_id', strtolower($publicId))->with('user:id,name');

        if ($viewer !== null) {
            $query->withCount(['postReactions as reactions_count', 'comments as comments_count' => fn ($q) => $q->visible()])
                ->withExists(['postReactions as reacted' => fn ($q) => $q->where('user_id', $viewer->getKey())]);
        }

        return $query->first() ?? throw new FeedRefused('post_not_found');
    }

    private function ownPost(User $user, string $publicId): FeedPost
    {
        return FeedPost::query()->visible()->where('public_id', strtolower($publicId))->where('user_id', $user->getKey())->first() ?? throw new FeedRefused('post_not_found');
    }

    /** @return list<int> */
    private function mutedBy(User $viewer): array
    {
        return FeedMute::query()->where('user_id', $viewer->getKey())->pluck('muted_user_id')->all();
    }

    private function checkDailyLimit(User $user): void
    {
        $tz = (string) config('counselors.directory.slot_timezone');
        $startOfDay = CarbonImmutable::now($tz)->startOfDay();

        $made = FeedPost::query()->where('user_id', $user->getKey())->where('created_at', '>=', $startOfDay->setTimezone((string) config('app.timezone')))->count();

        if ($made >= (int) config('feed.daily_posts')) {
            throw new FeedRefused('daily_post_limit', retryAfter: max(60, $startOfDay->addDay()->getTimestamp() - CarbonImmutable::now()->getTimestamp()));
        }
    }

    /** The people among these ids who are verified counselors people may book (the badge). @return array<int, true> */
    private function counselors(Collection $userIds): array
    {
        $ids = $userIds->unique()->values()->all();

        return $ids === [] ? [] : CounselorProfile::query()->listable()->whereIn('user_id', $ids)->pluck('user_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    private function presentOne(FeedPost $post, User $viewer): array
    {
        $post = $this->visiblePost($post->public_id, $viewer);

        return $this->presentPost($post, $viewer, $this->counselors(collect([$post->user_id])));
    }

    /** @return array{count: int, mine: bool} */
    private function reactions(FeedPost $post, User $viewer): array
    {
        return [
            'count' => PostReaction::query()->where('feed_post_id', $post->getKey())->count(),
            'mine' => PostReaction::query()->where('feed_post_id', $post->getKey())->where('user_id', $viewer->getKey())->exists(),
        ];
    }

    /** @param  array<int, true>  $badges */
    private function presentPost(FeedPost $post, User $viewer, array $badges): array
    {
        return [
            'id' => $post->public_id,
            'text' => (string) $post->content,
            'created_at' => $this->when($post->created_at),
            'edited' => $post->edited_at !== null,
            'author' => ['name' => (string) $post->user?->name, 'counselor' => isset($badges[$post->user_id])],
            'mine' => $post->user_id === $viewer->getKey(),
            'reactions' => ['count' => (int) $post->reactions_count, 'mine' => (bool) $post->reacted],
            'comments_count' => (int) $post->comments_count,
        ];
    }

    /** @param  array<int, true>  $badges */
    private function presentComment(FeedComment $comment, User $viewer, array $badges): array
    {
        return [
            'id' => $comment->public_id,
            'text' => (string) $comment->content,
            'created_at' => $this->when($comment->created_at),
            'author' => ['name' => (string) $comment->user?->name, 'counselor' => isset($badges[$comment->user_id])],
            'mine' => $comment->user_id === $viewer->getKey(),
        ];
    }

    private function when(?\DateTimeInterface $moment): ?string
    {
        return $moment === null ? null : CarbonImmutable::instance($moment)->setTimezone((string) config('counselors.directory.slot_timezone'))->toIso8601String();
    }

    /** @return array{post: array<string, mixed>, created: bool, crisis: bool, helplines: list<array<string, string>>} */
    private function wrap(array $post, bool $created, string $text): array
    {
        $crisis = $this->crisis->flags($text);

        return ['post' => $post, 'created' => $created, 'crisis' => $crisis, 'helplines' => $crisis ? CrisisDetector::helplines() : []];
    }

    /** @return array{comment: array<string, mixed>, created: bool, crisis: bool, helplines: list<array<string, string>>} */
    private function wrapComment(array $comment, bool $created, string $text): array
    {
        $crisis = $this->crisis->flags($text);

        return ['comment' => $comment, 'created' => $created, 'crisis' => $crisis, 'helplines' => $crisis ? CrisisDetector::helplines() : []];
    }
}
