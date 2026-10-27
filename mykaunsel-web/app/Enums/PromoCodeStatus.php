<?php

namespace App\Enums;

/**
 * The state of a promo code. It is NEVER stored: PromoCode::status() works it out from the data, in that one place, and the
 * list, the filter, the dashboard count and the tests all use it.
 */
enum PromoCodeStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case UsedUp = 'used_up';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::UsedUp => 'Used up',
            default => ucfirst($this->value),
        };
    }

    /** Tailwind classes for the badge. */
    public function classes(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Scheduled => 'bg-brand-blue/10 text-brand-blue',
            self::Expired => 'bg-brand-yellow/20 text-[#8A6210]',
            self::UsedUp => 'bg-brand-ink/10 text-brand-ink/70',
            self::Disabled => 'bg-brand-red/10 text-brand-red',
        };
    }
}
