<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FeedRefused;
use App\Http\Controllers\Controller;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The wellness Feed (docs/FEED.md). Everything is about the signed-in person and the public posts; a post or comment that is gone (taken down by
 * its author, removed by a moderator, or never there) is the same `404` for everyone.
 *
 * GET    /api/v1/feed                               the feed, newest first (`cursor`), without people the person muted
 * POST   /api/v1/feed/posts                          write a post (`text`, `client_id`)
 * GET    /api/v1/feed/posts/{id}                     one post
 * PATCH  /api/v1/feed/posts/{id}                     edit own post
 * DELETE /api/v1/feed/posts/{id}                     take own post down
 * PUT    /api/v1/feed/posts/{id}/reaction            like (safe to repeat)        DELETE: take the like back
 * GET    /api/v1/feed/posts/{id}/comments            comments, oldest first
 * POST   /api/v1/feed/posts/{id}/comments            comment (`text`, `client_id`)
 * DELETE /api/v1/feed/comments/{id}                  delete own comment
 * POST   /api/v1/feed/posts/{id}/report              report a post (`reason`)     POST /feed/comments/{id}/report: a comment
 * POST   /api/v1/feed/posts/{id}/mute-author         hide that person from the feed    GET /feed/mutes, DELETE /feed/mutes/{id}
 */
class FeedController extends Controller
{
    public function index(Request $request, FeedService $feed): JsonResponse
    {
        $query = $request->validate(['cursor' => ['nullable', 'string', 'max:26']]);

        return $this->private(response()->json($feed->feed($request->user(), $query['cursor'] ?? null)));
    }

    public function show(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(fn () => response()->json(['post' => $feed->show($request->user(), $id)]));
    }

    public function store(Request $request, FeedService $feed): JsonResponse
    {
        $input = $request->validate([
            'text' => ['required', 'string', 'max:'.(int) config('feed.max_post_chars')],
            'client_id' => ['nullable', 'uuid'],
        ]);

        return $this->run(function () use ($request, $feed, $input) {
            $made = $feed->create($request->user(), $input['text'], $input['client_id'] ?? null);

            return response()->json(['post' => $made['post'], 'crisis' => $made['crisis'], 'helplines' => $made['helplines']], $made['created'] ? 201 : 200);
        });
    }

    public function update(Request $request, string $id, FeedService $feed): JsonResponse
    {
        $input = $request->validate(['text' => ['required', 'string', 'max:'.(int) config('feed.max_post_chars')]]);

        return $this->run(fn () => response()->json(['post' => $feed->update($request->user(), $id, $input['text'])]));
    }

    public function destroy(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(function () use ($request, $id, $feed) {
            $feed->delete($request->user(), $id);

            return response()->json(['deleted' => true]);
        });
    }

    public function react(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(fn () => response()->json(['reactions' => $feed->react($request->user(), $id, $request->isMethod('PUT'))]));
    }

    public function comments(Request $request, string $id, FeedService $feed): JsonResponse
    {
        $query = $request->validate(['cursor' => ['nullable', 'string', 'max:26']]);

        return $this->run(fn () => response()->json($feed->comments($request->user(), $id, $query['cursor'] ?? null)));
    }

    public function comment(Request $request, string $id, FeedService $feed): JsonResponse
    {
        $input = $request->validate([
            'text' => ['required', 'string', 'max:'.(int) config('feed.max_comment_chars')],
            'client_id' => ['nullable', 'uuid'],
        ]);

        return $this->run(function () use ($request, $id, $feed, $input) {
            $made = $feed->addComment($request->user(), $id, $input['text'], $input['client_id'] ?? null);

            return response()->json(['comment' => $made['comment'], 'crisis' => $made['crisis'], 'helplines' => $made['helplines']], $made['created'] ? 201 : 200);
        });
    }

    public function destroyComment(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(function () use ($request, $id, $feed) {
            $feed->deleteComment($request->user(), $id);

            return response()->json(['deleted' => true]);
        });
    }

    public function report(Request $request, string $id, FeedService $feed): JsonResponse
    {
        $input = $request->validate(['reason' => ['required', Rule::in(config('feed.report_reasons'))]]);
        $kind = str_contains($request->path(), '/comments/') ? 'comment' : 'post';

        return $this->run(function () use ($request, $feed, $kind, $id, $input) {
            $feed->report($request->user(), $kind, $id, $input['reason']);

            return response()->json(['reported' => true], 202);
        });
    }

    public function muteAuthor(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(fn () => response()->json(['mute' => $feed->muteAuthor($request->user(), $id)]));
    }

    public function mutes(Request $request, FeedService $feed): JsonResponse
    {
        return $this->private(response()->json(['data' => $feed->mutes($request->user())]));
    }

    public function unmute(Request $request, string $id, FeedService $feed): JsonResponse
    {
        return $this->run(function () use ($request, $id, $feed) {
            $feed->unmute($request->user(), $id);

            return response()->json(['deleted' => true]);
        });
    }

    /** @param  callable(): JsonResponse  $work */
    private function run(callable $work): JsonResponse
    {
        try {
            return $this->private($work());
        } catch (FeedRefused $refused) {
            return $refused->toResponse();
        }
    }

    private function private(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store')->header('Vary', 'Authorization');
    }
}
