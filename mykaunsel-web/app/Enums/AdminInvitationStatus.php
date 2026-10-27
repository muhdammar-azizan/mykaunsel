<?php

namespace App\Enums;

/**
 * What is stored. An invitation that is still "pending" in the database but past its time is shown as expired
 * (see AdminInvitation::displayStatus()); "expired" is stored only when a newer invitation replaced it.
 */
enum AdminInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
