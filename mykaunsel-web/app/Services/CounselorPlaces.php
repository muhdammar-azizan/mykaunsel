<?php

namespace App\Services;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Membership;
use App\Models\PracticeLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "My places": where an INDEPENDENT counselor sees clients in person (docs/COUNSELORS.md, "My places").
 *
 * - A place is owned by the counselor (`owner_type = counselor`, `owner_id` = their user id). An organization's places stay the organization's.
 *   Only someone with an independent practice (an active counselor membership of the MyKaunsel platform itself) has places.
 * - At most `counselors.profile.max_places` active places. No map pin in this step: coordinates stay empty.
 * - Clients and searches only ever see a place's NAME and CITY; the full address and the directions go only to a client who booked (the existing
 *   rule of the booking details). The page tells the counselor not to use a home address as the name.
 * - A place can not be CHANGED while a confirmed session or a waiting request depends on it (the client was told that address). It can not be
 *   REMOVED while a time, a weekly line or a waiting request still uses it. Removing a place that history points at (it was used before) ARCHIVES
 *   it: never offered again, never deleted. One that was never used is deleted.
 * - Audit: ids and the names of the fields only, never the address. Same transaction as the change, same lock as opening a time (the profile row).
 */
class CounselorPlaces
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public static function max(): int
    {
        return (int) config('counselors.profile.max_places', 5);
    }

    /** Does this counselor have an independent practice (so places of their own make sense)? */
    public function hasIndependentPractice(User $counselor): bool
    {
        return Membership::query()
            ->where('user_id', $counselor->getKey())->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)
            ->whereHas('organization', fn ($q) => $q->where('org_type', OrgType::Platform->value))->exists();
    }

    /**
     * The counselor's active places, each with what stops it from being changed or removed (for the page to say so).
     *
     * @return list<array<string, mixed>>
     */
    public function list(User $counselor): array
    {
        return $this->own($counselor)->orderBy('name')->get()->map(function (PracticeLocation $place) {
            $changeBlock = $this->changeBlock($place);
            $removeBlock = $this->removeBlock($place);

            return [
                'id' => $place->id,
                'name' => (string) $place->name,
                'address' => (string) $place->address,
                'city' => (string) $place->city,
                'state' => (string) $place->state,
                'postcode' => (string) $place->postcode,
                'description' => (string) $place->description,
                'change_blocked' => $changeBlock,
                'remove_blocked' => $removeBlock,
            ];
        })->all();
    }

    /**
     * @param  array{name: string, address: string, city: string, state: string, postcode: string, description?: ?string}  $input
     *
     * @throws RuntimeException in plain words
     */
    public function add(User $counselor, array $input): PracticeLocation
    {
        if (! $this->hasIndependentPractice($counselor)) {
            throw new RuntimeException('Places of your own are for an independent practice. An organization\'s places are managed by the organization.');
        }

        return DB::transaction(function () use ($counselor, $input) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            if ($this->own($counselor)->count() >= self::max()) {
                throw new RuntimeException('You have reached the most places ('.self::max().'). Remove one you do not use first.');
            }

            $place = PracticeLocation::create($this->fields($input) + ['owner_type' => 'counselor', 'owner_id' => $counselor->getKey(), 'is_primary' => false]);

            Audit::log('counselor.place_added', $profile, ['counselor_id' => $profile?->getKey(), 'place_id' => $place->getKey()], $counselor);

            return $place;
        });
    }

    /**
     * @param  array{name: string, address: string, city: string, state: string, postcode: string, description?: ?string}  $input
     * @return list<string> the fields that changed
     *
     * @throws RuntimeException
     */
    public function update(User $counselor, int $placeId, array $input): array
    {
        return DB::transaction(function () use ($counselor, $placeId, $input) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
            $place = $this->own($counselor)->lockForUpdate()->findOrFail($placeId);

            if (($why = $this->changeBlock($place)) !== null) {
                throw new RuntimeException($why);
            }

            $place->fill($this->fields($input));
            $changed = array_keys($place->getDirty());

            if ($changed !== []) {
                $place->save();
                Audit::log('counselor.place_updated', $profile, ['counselor_id' => $profile?->getKey(), 'place_id' => $place->getKey(), 'fields' => $changed], $counselor);
            }

            return $changed;
        });
    }

    /**
     * Removes a place: deleted when nothing ever used it, archived when history points at it.
     *
     * @return bool true when it was archived (kept for the history), false when it was deleted
     *
     * @throws RuntimeException
     */
    public function remove(User $counselor, int $placeId): bool
    {
        return DB::transaction(function () use ($counselor, $placeId) {
            $profile = CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();
            $place = $this->own($counselor)->lockForUpdate()->findOrFail($placeId);

            if (($why = $this->removeBlock($place)) !== null) {
                throw new RuntimeException($why);
            }

            $archived = $this->everUsed($place);
            $archived ? $place->forceFill(['archived_at' => now()])->save() : $place->delete();

            Audit::log('counselor.place_removed', $profile, ['counselor_id' => $profile?->getKey(), 'place_id' => $placeId, 'archived' => $archived], $counselor);

            return $archived;
        });
    }

    // ---- rules --------------------------------------------------------------------------------------------------------------------------

    /** Why this place can not be changed now, or null. */
    private function changeBlock(PracticeLocation $place): ?string
    {
        if ($this->upcomingBookings($place) > 0 || $this->waitingRequests($place) > 0) {
            return 'A client has a booked session or a waiting request at this place and was told this address. Change it after those sessions are over, or add a new place.';
        }

        return null;
    }

    /** Why this place can not be removed now, or null. */
    private function removeBlock(PracticeLocation $place): ?string
    {
        if ($this->futureTimes($place) > 0 || $this->weeklyLines($place) > 0 || $this->waitingRequests($place) > 0) {
            return 'Times, weekly hours or a waiting request still use this place. Remove or change those first.';
        }

        return null;
    }

    private function own(User $counselor)
    {
        return PracticeLocation::query()->active()->where('owner_type', 'counselor')->where('owner_id', $counselor->getKey());
    }

    private function today(): string
    {
        return $this->availability->now()->format('Y-m-d');
    }

    private function futureTimes(PracticeLocation $place): int
    {
        return CalendarEntry::query()->where('location_id', $place->getKey())->whereDate('entry_date', '>=', $this->today())
            ->whereIn('entry_type', [CalendarEntryType::AvailableSlot->value, CalendarEntryType::RequestedSession->value])->count();
    }

    private function upcomingBookings(PracticeLocation $place): int
    {
        // The place of a session is the place of its time (the booking copies it, so does the calendar entry): the entry is the one source.
        return Booking::query()->where('status', BookingStatus::Confirmed->value)
            ->whereHas('calendarEntry', fn ($q) => $q->where('location_id', $place->getKey())->whereDate('entry_date', '>=', $this->today()))->count();
    }

    private function weeklyLines(PracticeLocation $place): int
    {
        return CounselorWeeklyHour::query()->where('location_id', $place->getKey())->count();
    }

    private function waitingRequests(PracticeLocation $place): int
    {
        return BookingRequest::query()->where('location_id', $place->getKey())->where('status', BookingRequestStatus::Pending->value)
            ->where('expires_at', '>', now())->count();
    }

    private function everUsed(PracticeLocation $place): bool
    {
        $id = $place->getKey();

        return CalendarEntry::query()->where('location_id', $id)->exists() || Booking::query()->where('location_id', $id)->exists()
            || BookingRequest::query()->where('location_id', $id)->exists() || CounselorWeeklyHour::query()->where('location_id', $id)->exists();
    }

    /** @return array<string, mixed> */
    private function fields(array $input): array
    {
        $clean = fn (?string $v) => trim((string) preg_replace('/\s+/u', ' ', (string) $v));

        return [
            'name' => mb_substr($clean($input['name'] ?? ''), 0, 100),
            'address' => mb_substr($clean($input['address'] ?? ''), 0, 255),
            'city' => mb_substr($clean($input['city'] ?? ''), 0, 100),
            'state' => (string) ($input['state'] ?? ''),
            'postcode' => preg_replace('/\D/', '', (string) ($input['postcode'] ?? '')),
            'description' => ($d = trim((string) ($input['description'] ?? ''))) === '' ? null : mb_substr($d, 0, 500),
        ];
    }
}
