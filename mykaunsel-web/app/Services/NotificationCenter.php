<?php

namespace App\Services;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Inbox\NotificationRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * The in-app inbox ("the bell"). Event-based: it records that something just happened; it never replaces the Dashboard's state-based
 * "Needs your attention" list.
 *
 * WHO: every ACTIVE Platform Admin (platform_admin membership with status active in the platform organization), except the person who
 * did the thing. A removed or inactive admin receives nothing.
 *
 * WHAT IS STORED: the type key, the target (kind + id), a short label (a name or a "#12" reference), a count and timestamps. Producers pass
 * only that; there is no parameter that could carry complaint text, notes, reasons, document names, emails or KB numbers. No URL is stored
 * either: the page a notification opens comes from the type (App\Support\Inbox\NotificationType).
 *
 * BEST EFFORT: publish() / publishCoalescing() never throw. A failure is report()ed and the action that caused it (an admin's decision, an
 * applicant's sign-up) goes on. Producers call them AFTER the commit, from the same place that sends the email.
 *
 * ONE PER OCCURRENCE: a unique key (recipient, type, occurrence) means a retried job or a re-run scheduled task cannot create a second one.
 */
class NotificationCenter
{
    public function __construct(private readonly NotificationRegistry $registry) {}

    // =============================================================================================================
    //  Producing
    // =============================================================================================================

    /**
     * Tell every active Platform Admin (but $actor) that something happened. Never throws.
     *
     * @param  string  $occurrence  tells two occurrences about the same target apart (a resubmission number, a date); the same
     *                              occurrence published twice creates one notification
     * @return int how many notifications were created
     */
    public function publish(string $type, ?Model $target, ?string $label, string $occurrence = '', ?User $actor = null): int
    {
        try {
            return $this->notifyAdmins($type, $target, $label, $occurrence, $actor);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Same, for a type that coalesces (repeated duplicate-KB attempts): within the window (config notifications.coalesce_hours, counted
     * from the first attempt) a new event raises the count of the existing notification instead of making another. Never throws.
     *
     * @return int how many admins' notifications were created or raised
     */
    public function publishCoalescing(string $type, Model $target, ?string $label, ?User $actor = null): int
    {
        try {
            return $this->notifyAdminsCoalescing($type, $target, $label, $actor);
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Tell ONE person (a client, not an admin) that something happened. Never throws: a failure is report()ed and the action that caused it goes
     * on. The same occurrence told twice creates one notification (a retry cannot double it).
     *
     * @return bool whether a notification was created
     */
    public function notify(?User $recipient, string $type, ?Model $target, ?string $label, string $occurrence = ''): bool
    {
        if ($recipient === null) {
            return false;
        }

        try {
            $definition = $this->registry->get($type);
            $this->assertTarget($definition->targetType, $target);

            UserNotification::create($this->row($recipient->getKey(), $type, $definition->targetType, $target, $label, $this->occurrenceKey($definition->targetType, $target?->getKey(), $occurrence)));

            return true;
        } catch (UniqueConstraintViolationException) {
            return false; // a retry: this person already has it
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /** The same as publish(), but an error is thrown (used by tests and by publish()). */
    public function notifyAdmins(string $type, ?Model $target, ?string $label, string $occurrence = '', ?User $actor = null): int
    {
        $definition = $this->registry->get($type);
        $this->assertTarget($definition->targetType, $target);

        $key = $this->occurrenceKey($definition->targetType, $target?->getKey(), $occurrence);
        $created = 0;

        foreach ($this->activeAdminIds($actor) as $recipient) {
            try {
                UserNotification::create($this->row($recipient, $type, $definition->targetType, $target, $label, $key));
                $created++;
            } catch (UniqueConstraintViolationException) {
                // This admin already has this occurrence: a retry, nothing to add.
            }
        }

        return $created;
    }

    /** The same as publishCoalescing(), but an error is thrown. */
    public function notifyAdminsCoalescing(string $type, Model $target, ?string $label, ?User $actor = null): int
    {
        $definition = $this->registry->get($type);

        if (! $definition->coalesces()) {
            throw new InvalidArgumentException("The notification type {$type} does not coalesce.");
        }

        $this->assertTarget($definition->targetType, $target);

        return DB::transaction(function () use ($type, $definition, $target, $label, $actor) {
            // Two attempts at the same time wait for each other here, so one makes the notification and the other raises its count.
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->first();

            $since = now()->subHours((int) config('notifications.coalesce_hours'));
            $touched = 0;

            foreach ($this->activeAdminIds($actor) as $recipient) {
                $existing = UserNotification::query()->ownedBy($recipient)
                    ->where('type', $type)->where('target_id', $target->getKey())->where('created_at', '>=', $since)
                    ->orderByDesc('id')->first();

                if ($existing !== null) {
                    // Raised again: one more attempt, newest time, unread again.
                    $existing->forceFill(['count' => $existing->count + 1, 'occurred_at' => now(), 'read_at' => null])->save();
                } else {
                    UserNotification::create($this->row($recipient, $type, $definition->targetType, $target, $label, $this->occurrenceKey($definition->targetType, $target->getKey(), (string) now()->getTimestamp())));
                }

                $touched++;
            }

            return $touched;
        });
    }

    // =============================================================================================================
    //  Reading and changing (always the signed-in person's own)
    // =============================================================================================================

    /**
     * The bell's number: one indexed count.
     *
     * @param  list<string>|null  $types  only these type keys (the mobile app counts its own group only); null = all
     */
    public function unreadCount(User $user, ?array $types = null): int
    {
        return UserNotification::query()->ownedBy($user)->unread()->when($types !== null, fn ($query) => $query->whereIn('type', $types))->count();
    }

    /**
     * One page of a person's notifications of the given types, newest first.
     *
     * @param  list<string>  $types
     */
    public function pageOf(User $user, array $types, int $page, int $perPage): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return UserNotification::query()->ownedBy($user)->whereIn('type', $types)
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', max(1, $page));
    }

    /** Marks one of THIS person's notifications read, by its public id. @return bool false when it is not theirs (or does not exist) */
    public function markReadByPublicId(User $user, string $publicId): bool
    {
        $id = UserNotification::query()->ownedBy($user)->where('public_id', strtolower($publicId))->value('id');

        return $id !== null && $this->markRead($user, (int) $id);
    }

    /** @return Collection<int, UserNotification> newest first */
    public function latest(User $user, int $limit): Collection
    {
        return UserNotification::query()->ownedBy($user)->orderByDesc('occurred_at')->orderByDesc('id')->limit($limit)->get();
    }

    /** One of THIS person's notifications, or null. Another person's id is simply "not found". */
    public function find(User $user, int $id): ?UserNotification
    {
        return UserNotification::query()->ownedBy($user)->whereKey($id)->first();
    }

    /** @return bool false when the notification is not theirs (or does not exist) */
    public function markRead(User $user, int $id): bool
    {
        $notification = $this->find($user, $id);

        if ($notification === null) {
            return false;
        }

        if ($notification->isUnread()) {
            UserNotification::query()->ownedBy($user)->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return true;
    }

    /** @return bool false when the notification is not theirs (or does not exist) */
    public function markUnread(User $user, int $id): bool
    {
        if ($this->find($user, $id) === null) {
            return false;
        }

        UserNotification::query()->ownedBy($user)->whereKey($id)->whereNotNull('read_at')->update(['read_at' => null]);

        return true;
    }

    /**
     * @param  list<string>|null  $types  only these type keys (the page's type group); null = every unread one
     * @return int how many were unread and are now read
     */
    public function markAllRead(User $user, ?array $types = null): int
    {
        return UserNotification::query()->ownedBy($user)->unread()
            ->when($types !== null, fn ($query) => $query->whereIn('type', $types))
            ->update(['read_at' => now()]);
    }

    /** When a Platform Admin is removed: everything addressed to them goes. @return int how many were deleted */
    public function deleteAllFor(User $user): int
    {
        return UserNotification::query()->ownedBy($user)->delete();
    }

    // =============================================================================================================
    //  Retention
    // =============================================================================================================

    /**
     * Delete what is too old: READ notifications older than notifications.retention.read_days (90) and UNREAD ones older than unread_days
     * (180), counted from when the thing happened. Done in small batches. Opening or reading is never recorded, only this clean-up is
     * (by the command, with counts only).
     *
     * @return array{read: int, unread: int} how many were (or, with $dryRun, would be) deleted
     */
    public function purge(bool $dryRun = false): array
    {
        $read = fn () => UserNotification::query()->whereNotNull('read_at')->where('occurred_at', '<', now()->subDays(max(1, (int) config('notifications.retention.read_days'))));
        $unread = fn () => UserNotification::query()->whereNull('read_at')->where('occurred_at', '<', now()->subDays(max(1, (int) config('notifications.retention.unread_days'))));

        if ($dryRun) {
            return ['read' => $read()->count(), 'unread' => $unread()->count()];
        }

        return ['read' => $this->deleteInBatches($read()), 'unread' => $this->deleteInBatches($unread())];
    }

    private function deleteInBatches(\Illuminate\Database\Eloquent\Builder $query): int
    {
        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : UserNotification::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }

    // =============================================================================================================
    //  Internals
    // =============================================================================================================

    /**
     * Active Platform Admins: a platform_admin membership with status active in the platform organization (so a former admin, whose
     * membership is offboarded, receives nothing).
     *
     * @return list<int>
     */
    public function activeAdminIds(?User $except = null): array
    {
        return DB::table('memberships')
            ->join('organizations', 'organizations.id', '=', 'memberships.org_id')
            ->where('organizations.org_type', OrgType::Platform->value)
            ->where('memberships.role', MembershipRole::PlatformAdmin->value)
            ->where('memberships.status', MembershipStatus::Active->value)
            ->when($except, fn ($query) => $query->where('memberships.user_id', '!=', $except->getKey()))
            ->orderBy('memberships.user_id')
            ->distinct()
            ->pluck('memberships.user_id')
            ->map(fn ($id) => (int) $id)->all();
    }

    private function assertTarget(?string $targetType, ?Model $target): void
    {
        if (($targetType === null) !== ($target === null)) {
            throw new InvalidArgumentException($targetType === null ? 'This notification type has no target.' : "This notification type needs a {$targetType} as its target.");
        }
    }

    /** "organization:12:2" — the target and the occurrence; unique per recipient and type together with the type. */
    private function occurrenceKey(?string $targetType, int|string|null $targetId, string $occurrence): string
    {
        return Str::limit(($targetType ?? 'none').':'.($targetId ?? 0).($occurrence !== '' ? ':'.$occurrence : ''), 120, '');
    }

    /** @return array<string, mixed> */
    private function row(int $recipient, string $type, ?string $targetType, ?Model $target, ?string $label, string $key): array
    {
        return [
            'recipient_user_id' => $recipient,
            'type' => $type,
            'target_type' => $targetType,
            'target_id' => $target?->getKey(),
            'label' => $this->cleanLabel($label),
            'occurrence_key' => $key,
            'count' => 1,
            'occurred_at' => now(),
        ];
    }

    /** Short, one line, no control characters. It is escaped again whenever it is shown. */
    private function cleanLabel(?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim((string) preg_replace('/[\p{Cc}\s]+/u', ' ', $label));

        return $label === '' ? null : Str::limit($label, 150, '');
    }
}
