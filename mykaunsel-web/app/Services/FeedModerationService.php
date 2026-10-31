<?php

namespace App\Services;

use App\Exceptions\ModerationNothingToDo;
use App\Facades\Audit;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\User;
use App\Notifications\FeedContentRemoved;
use App\Notifications\FeedWarning;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What a Platform Admin can do about reported feed writing (Moderate Feed): set the reports aside, remove the post or comment, or warn its author.
 *
 * The queue is one row per post or comment (all pending reports about it together). Every action works on ALL its pending reports at once, inside
 * one transaction with the post or comment locked, so two admins cannot both act on the same row: the second finds nothing pending and is told so.
 * The audit entries hold ids and the kind of action, never the writing or what the admin wrote to the author. Emails go out after the commit.
 */
class FeedModerationService
{
    /** Sets the pending reports about this post or comment aside. The writing stays. */
    public function dismiss(string $kind, int $id, User $admin): void
    {
        DB::transaction(function () use ($kind, $id, $admin) {
            $target = $this->lock($kind, $id);
            $count = $this->close($kind, $target, 'dismissed', $admin);

            Audit::log('moderation.report_dismissed', $target, ['kind' => $kind, 'reports' => $count], $admin);
        });
    }

    /** Takes the post or comment down for everyone and tells its author (with the admin's note, if there is one). */
    public function remove(string $kind, int $id, User $admin, ?string $note): void
    {
        $author = null;

        DB::transaction(function () use ($kind, $id, $admin, &$author) {
            $target = $this->lock($kind, $id);
            $count = $this->close($kind, $target, 'actioned', $admin);

            if ($target->removed_at === null) {
                $target->forceFill(['removed_at' => now()])->save();
            }

            $author = $target->user;

            Audit::log($kind === 'post' ? 'moderation.post_removed' : 'moderation.comment_removed', $target, ['reports' => $count], $admin);
        });

        $this->tell($author, new FeedContentRemoved($kind, $note));
    }

    /** Warns the author by email. The writing stays; the reports about it are closed as acted on. */
    public function warn(string $kind, int $id, User $admin, string $message): void
    {
        $author = null;

        DB::transaction(function () use ($kind, $id, $admin, &$author) {
            $target = $this->lock($kind, $id);
            $count = $this->close($kind, $target, 'actioned', $admin);
            $author = $target->user;

            Audit::log('moderation.user_warned', $author, ['kind' => $kind, 'target_id' => $target->getKey(), 'reports' => $count], $admin);
        });

        $this->tell($author, new FeedWarning($message));
    }

    /** @throws ModerationNothingToDo */
    private function lock(string $kind, int $id): Model
    {
        $model = $kind === 'post' ? FeedPost::class : FeedComment::class;

        return $model::query()->with('user')->lockForUpdate()->findOrFail($id);
    }

    /** Closes the pending reports about the target. @throws ModerationNothingToDo when nobody is waiting (another admin was first) */
    private function close(string $kind, Model $target, string $status, User $admin): int
    {
        $column = $kind === 'post' ? 'feed_post_id' : 'feed_comment_id';

        $count = FeedReport::query()->where($column, $target->getKey())->where('status', 'pending')
            ->update(['status' => $status, 'handled_by_user_id' => $admin->getKey(), 'handled_at' => now()]);

        if ($count === 0) {
            throw new ModerationNothingToDo;
        }

        return $count;
    }

    private function tell(?User $author, object $notification): void
    {
        if ($author === null) {
            return;
        }

        try {
            $author->notify($notification);
        } catch (Throwable $e) {
            Log::warning('Could not email a feed moderation notice.', ['user_id' => $author->getKey(), 'error' => $e->getMessage()]);
        }
    }
}
