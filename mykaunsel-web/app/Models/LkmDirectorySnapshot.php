<?php

namespace App\Models;

use App\Models\Concerns\HasPaStatusScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/**
 * Our snapshot of the LKM (Lembaga Kaunselor Malaysia) directory — what counselor registration is checked against.
 * Maintained by hand by Platform Admin. Records are never deleted: registered counselors refer to them by KB number,
 * so a counselor who is no longer registered is marked "Tidak Aktif" / "Digantung" instead.
 */
class LkmDirectorySnapshot extends Model
{
    use HasFactory;
    use HasPaStatusScope;

    /** status value => label shown. "Aktif" is the only one that counts as registered (see LkmVerificationService). */
    public const STATUSES = ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif', 'Digantung' => 'Digantung'];

    protected $table = 'lkm_directory_snapshot';

    protected $fillable = [
        'kb_number',
        'pa_number',
        'full_name',
        'status',
        'pa_valid_from',
        'pa_valid_until',
        'email',
        'city',
        'state',
    ];

    /**
     * The rules every way of changing a record shares (the form and the CSV import). The KB number is separate: it is only
     * ever set when a record is created.
     *
     * @return array<string, list<mixed>>
     */
    public static function fieldRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'pa_number' => ['required', 'regex:/^PA\d{5}$/'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'pa_valid_from' => ['required', 'date_format:Y-m-d'],
            'pa_valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:pa_valid_from'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function casts(): array
    {
        return [
            'pa_valid_from' => 'date',
            'pa_valid_until' => 'date',
        ];
    }

    /** MyKaunsel accounts registered with this record's KB number. */
    public function counselorProfiles(): HasMany
    {
        return $this->hasMany(CounselorProfile::class, 'kb_number', 'kb_number');
    }

    public function isActive(): bool
    {
        return $this->status === 'Aktif';
    }

    /** Shown in the audit log as the target's name. */
    public function auditName(): string
    {
        return $this->full_name;
    }

    /** Name, KB number or email. */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        // "!" is the LIKE escape character (a backslash means different things to SQLite and MySQL).
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("full_name like ? escape '!'", [$like])
            ->orWhereRaw("kb_number like ? escape '!'", [$like])
            ->orWhereRaw("email like ? escape '!'", [$like]));
    }

    /** Has (true) or has not (false) a MyKaunsel counselor account. */
    public function scopeRegistered(Builder $query, bool $registered = true): Builder
    {
        return $registered
            ? $query->whereHas('counselorProfiles')
            : $query->whereDoesntHave('counselorProfiles');
    }
}
