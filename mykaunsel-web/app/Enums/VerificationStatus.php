<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * The only moves a counselor profile may make:
     * pending → approved / rejected, rejected → pending (they resubmit), approved ⇄ suspended
     * (approved → suspended also covers the system suspending after the PA expires).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Rejected => [self::Pending],
            self::Approved => [self::Suspended],
            self::Suspended => [self::Approved],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
