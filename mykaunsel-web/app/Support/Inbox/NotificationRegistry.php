<?php

namespace App\Support\Inbox;

use App\Models\UserNotification;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Every notification type there is. The core types are registered in CoreNotificationTypes; a later page (Moderate Feed, another role)
 * calls register() with its own types and the center, the panel and the full page pick them up without any change to the core.
 */
class NotificationRegistry
{
    /** @var array<string, NotificationType> */
    private array $types = [];

    public function register(NotificationType $type): void
    {
        if (isset($this->types[$type->key])) {
            throw new InvalidArgumentException("The notification type {$type->key} is already registered.");
        }

        if (! array_key_exists($type->group, config('notifications.groups'))) {
            throw new InvalidArgumentException("The notification type {$type->key} uses an unknown group ({$type->group}).");
        }

        $this->types[$type->key] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /** An unknown key throws: a typo is caught in tests instead of writing a notification nobody can open. */
    public function get(string $key): NotificationType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Unknown notification type: {$key}");
    }

    /** @return array<string, NotificationType> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return list<string> the type keys of one filter group */
    public function keysInGroup(string $group): array
    {
        return array_values(array_keys(array_filter($this->types, fn (NotificationType $t) => $t->group === $group)));
    }

    /**
     * Whether each notification's target is still waiting, already handled or gone, for a whole list at once. Each type is asked ONCE for
     * all of its targets, so the number of queries depends on how many kinds are in the list, never on how many items.
     *
     * @param  Collection<int, UserNotification>  $notifications
     * @return array<int, string> [notification id => NotificationType::OPEN|HANDLED|GONE]
     */
    public function states(Collection $notifications): array
    {
        $result = [];

        foreach ($notifications->groupBy('type') as $key => $group) {
            if (! $this->has($key)) {
                foreach ($group as $notification) {
                    $result[$notification->id] = NotificationType::OPEN;
                }

                continue;
            }

            $type = $this->get($key);

            // A type with no target at all (a system event) has nothing that can be handled or gone.
            if ($type->targetType === null) {
                foreach ($group as $notification) {
                    $result[$notification->id] = NotificationType::OPEN;
                }

                continue;
            }

            $states = $type->states($group->pluck('target_id')->filter()->map(fn ($id) => (int) $id)->all());

            foreach ($group as $notification) {
                $result[$notification->id] = $notification->target_id === null ? NotificationType::GONE : $states[(int) $notification->target_id];
            }
        }

        return $result;
    }
}
