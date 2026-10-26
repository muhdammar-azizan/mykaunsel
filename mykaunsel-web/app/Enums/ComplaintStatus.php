<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    /**
     * Only forward: open → under review / resolved / dismissed, under review → resolved / dismissed.
     * Resolved and dismissed are final.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::UnderReview, self::Resolved, self::Dismissed],
            self::UnderReview => [self::Resolved, self::Dismissed],
            self::Resolved, self::Dismissed => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isClosed(): bool
    {
        return $this === self::Resolved || $this === self::Dismissed;
    }
}
