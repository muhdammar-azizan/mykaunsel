<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A reference code an organization gives its employees who only have an ordinary e-mail address. Whoever uses it asks to join as an
 * EMPLOYEE (never a counselor or an admin) and waits for the organization's approval.
 */
class OrgInviteCode extends Model
{
    use HasFactory;

    /** No 0/O and 1/I/L: a code is read aloud and typed from a message. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 10;

    protected $fillable = [
        'org_id',
        'created_by_user_id',
        'code',
        'expires_at',
        'max_uses',
        'used_count',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /** A new, unused code. [$maxUses] and [$expiresAt] null mean "no limit". */
    public static function issue(Organization $organization, ?User $by, ?int $maxUses, ?Carbon $expiresAt): self
    {
        do {
            $code = '';

            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (static::query()->where('code', $code)->exists());

        return static::create([
            'org_id' => $organization->getKey(),
            'created_by_user_id' => $by?->getKey(),
            'code' => $code,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt,
            'used_count' => 0,
        ]);
    }

    /** What a person typed, as stored: capitals only, no spaces or dashes ("k7m2-9qxr4p" is the same code as "K7M29QXR4P"). */
    public static function normalize(string $typed): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $typed) ?? '');
    }

    /** "K7M29-QXR4P": easier to read than ten characters in a row. */
    public function display(): string
    {
        return implode('-', str_split($this->code, (int) (self::LENGTH / 2)));
    }

    public function isUsable(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isAfter($at))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }
}
