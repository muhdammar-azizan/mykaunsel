<?php

namespace App\Support\Inbox;

use Closure;

/**
 * One kind of in-app notification. A type declares everything the center needs to know about it:
 *
 * - key and group: the dotted key ("organization.application_submitted") and the filter group of the full page.
 * - title: a fixed sentence. The label (a name or a "#12" reference) is shown beside it, never inside a template.
 * - target: what the notification is about ('organization', 'counselor_profile', …) and, given the target's id, the URL it opens. The
 *   URL is worked out here on the server every time; it is never stored, so a stored row can never send anyone to an arbitrary address.
 * - state (optional): given the ids of many targets at once, says for each whether it is still 'open' (waiting for someone), 'handled'
 *   (already decided, shown dimmed as "Already reviewed") or 'gone' (deleted). One call per type for a whole list: no query per item.
 * - countNoun (optional): marks a type that coalesces repeated events into one row with a count ("3 attempts").
 */
final class NotificationType
{
    public const OPEN = 'open';

    public const HANDLED = 'handled';

    public const GONE = 'gone';

    /**
     * @param  (Closure(?int): ?string)|null  $route  target id → URL (the id is null for a type without a target); null = no page of its own
     * @param  (Closure(list<int>): array<int, string>)|null  $state  target ids → [id => open|handled|gone]
     */
    public function __construct(
        public readonly string $key,
        public readonly string $group,
        public readonly string $title,
        public readonly ?string $targetType = null,
        private readonly ?Closure $route = null,
        private readonly ?Closure $state = null,
        public readonly ?string $countNoun = null,
    ) {}

    public function coalesces(): bool
    {
        return $this->countNoun !== null;
    }

    /** The page this notification opens, or null when the type has no target page (the caller then uses the notifications page). */
    public function url(?int $targetId): ?string
    {
        return $this->route !== null ? ($this->route)($targetId) : null;
    }

    /**
     * @param  list<int>  $targetIds
     * @return array<int, string> [target id => OPEN|HANDLED|GONE]; a type with no state rule is simply open
     */
    public function states(array $targetIds): array
    {
        if ($this->state === null || $targetIds === []) {
            return array_fill_keys($targetIds, self::OPEN);
        }

        $found = ($this->state)(array_values(array_unique($targetIds)));

        // Whatever the rule did not mention is treated as gone (it was deleted).
        return collect($targetIds)->mapWithKeys(fn (int $id) => [$id => $found[$id] ?? self::GONE])->all();
    }

    /** "3 attempts" for a coalesced row, nothing for a single event. */
    public function detail(int $count): ?string
    {
        if ($this->countNoun === null || $count < 2) {
            return null;
        }

        return $count.' '.$this->countNoun.'s';
    }
}
