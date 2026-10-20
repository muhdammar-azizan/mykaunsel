<?php

namespace App\Enums;

enum OrgStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    /**
     * The only moves an organization may make:
     * pending → active / rejected, rejected → pending (applicant resubmits), active ⇄ suspended.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Rejected],
            self::Rejected => [self::Pending],
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
