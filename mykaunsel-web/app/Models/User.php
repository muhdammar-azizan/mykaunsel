<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'pending_email',
        'timezone',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_consent_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted',
        ];
    }

    /**
     * Whether this user wants the given email notification (defaults come from config/account.php).
     */
    public function wantsNotification(string $key): bool
    {
        $stored = $this->notification_preferences[$key] ?? null;

        return (bool) ($stored ?? config("account.notifications.{$key}.default", config("account.client_notifications.{$key}.default", false)));
    }

    /** 2FA is on only after the user has proved they can generate a code. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** A secret exists but setup was not confirmed yet. */
    public function hasPendingTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at === null;
    }

    /** @return array<int, string> HMAC hashes of the unused recovery codes */
    public function recoveryCodeHashes(): array
    {
        return json_decode($this->two_factor_recovery_codes ?? '[]', true) ?: [];
    }

    /** Public URL of the profile photo, or null when the user has none. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path
            ? Storage::disk(config('account.avatar_disk'))->url($this->avatar_path)
            : null;
    }

    /** Timezone used to display times; falls back to the platform default. */
    public function getDisplayTimezoneAttribute(): string
    {
        return $this->timezone ?: config('account.default_timezone');
    }

    /** Up to two initials taken from the name, e.g. "Ammar Azizan" => "AA". */
    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => Str::upper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function counselorProfile(): HasOne
    {
        return $this->hasOne(CounselorProfile::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function feedPosts(): HasMany
    {
        return $this->hasMany(FeedPost::class);
    }
}
