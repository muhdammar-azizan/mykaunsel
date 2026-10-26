<?php

namespace App\Support;

use App\Models\LkmDirectorySnapshot;

/**
 * What the applicant submitted, side by side with the LKM snapshot record, and what it adds up to.
 *
 * outcome: match | mismatch | not_found | inactive | expired
 *   - not_found / inactive / expired  → approval must be blocked
 *   - mismatch (name, PA number, or the valid-until date differs) → a warning the admin must acknowledge
 */
final class LkmComparison
{
    public const MATCH = 'match';

    public const MISMATCH = 'mismatch';

    public const NOT_FOUND = 'not_found';

    public const INACTIVE = 'inactive';

    public const EXPIRED = 'expired';

    /**
     * @param  list<array{label: string, submitted: ?string, record: ?string, state: string}>  $fields  state: match | mismatch | info
     * @param  list<string>  $problems  human-readable, most serious first
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ?LkmDirectorySnapshot $record,
        public readonly array $fields,
        public readonly array $problems,
    ) {}

    /** Approval is not allowed: there is no usable LKM record. */
    public function blocksApproval(): bool
    {
        return in_array($this->outcome, [self::NOT_FOUND, self::INACTIVE, self::EXPIRED], true);
    }

    /** Approval is allowed, but the admin has to acknowledge something that does not line up. */
    public function needsAcknowledgement(): bool
    {
        return $this->outcome === self::MISMATCH;
    }

    public function isMatch(): bool
    {
        return $this->outcome === self::MATCH;
    }

    public function label(): string
    {
        return match ($this->outcome) {
            self::MATCH => 'Matches LKM',
            self::MISMATCH => 'Does not fully match LKM',
            self::NOT_FOUND => 'Not found in LKM',
            self::INACTIVE => 'LKM record not active',
            self::EXPIRED => 'PA expired in LKM',
        };
    }

    /** Tone for a small badge: good | warn | bad. */
    public function tone(): string
    {
        return match ($this->outcome) {
            self::MATCH => 'good',
            self::MISMATCH => 'warn',
            default => 'bad',
        };
    }
}
