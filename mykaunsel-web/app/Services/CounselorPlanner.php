<?php

namespace App\Services;

use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\SessionMode;
use App\Exceptions\BookingRefused;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\PracticeLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The counselor opens and removes their OWN bookable times (docs/AVAILABILITY.md, "The planner").
 *
 * - A time belongs to ONE context (an organization the counselor works in, or the platform for an independent) and is online or in person.
 *   In person needs a place of that context; an organization without one offers online only.
 * - Not in the past, not further ahead than `booking.planner_horizon_days`, on a 5-minute mark, one of the allowed lengths.
 * - It must fit the calendar: clear of personal blocks and booked times with the counselor's buffer (`AvailabilityService::isFree`, the rule
 *   the booking itself uses) AND not on top of another open time of theirs, in any context (a counselor is one person).
 * - A time that has EVER had a booking (even a cancelled one) cannot be removed: the booking's history points at it. A booked one cannot be
 *   touched at all. Nothing is edited in place: remove and open again.
 * - Same lock as booking: the counselor's profile row, so opening a time and booking one never interleave.
 */
class CounselorPlanner
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @return list<int> */
    public static function durations(): array
    {
        return array_map('intval', (array) config('booking.planner_durations'));
    }

    /**
     * The places this counselor can open times in: each active counselor membership, with that organization's places.
     *
     * @return list<array{id: int, name: string, locations: list<array{id: int, name: string, city: string}>}>
     */
    public function contexts(User $counselor): array
    {
        $memberships = Membership::query()
            ->where('user_id', $counselor->getKey())->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)
            ->with('organization:id,name')->get();

        $orgIds = $memberships->pluck('org_id')->all();
        $places = PracticeLocation::query()->where('owner_type', 'organization')->whereIn('owner_id', $orgIds)->orderBy('name')->get(['id', 'owner_id', 'name', 'city']);

        return $memberships->filter(fn (Membership $m) => $m->organization !== null)->map(fn (Membership $m) => [
            'id' => (int) $m->org_id,
            'name' => (string) $m->organization->name,
            'locations' => $places->where('owner_id', $m->org_id)->map(fn ($p) => ['id' => (int) $p->id, 'name' => (string) $p->name, 'city' => (string) $p->city])->values()->all(),
        ])->values()->all();
    }

    /**
     * The counselor's times from today on, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public function upcoming(User $counselor): array
    {
        $today = $this->availability->now()->format('Y-m-d');

        $entries = CalendarEntry::query()
            ->where('counselor_user_id', $counselor->getKey())->where('entry_type', CalendarEntryType::AvailableSlot->value)
            ->whereDate('entry_date', '>=', $today)
            ->with(['location:id,name,city', 'contextOrg:id,name'])
            ->withExists('bookings as has_history')
            ->withExists(['bookings as has_active' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->orderBy('entry_date')->orderBy('start_time')->limit(400)->get();

        return $entries->map(function (CalendarEntry $entry) {
            $start = $this->availability->startOf($entry);

            return [
                'id' => $entry->public_id,
                'day' => $start->format('Y-m-d'),
                'day_label' => $start->format('l, j F Y'),
                'time' => $start->format('g:i A').' – '.$start->addMinutes((int) $entry->duration_minutes)->format('g:i A'),
                'minutes' => (int) $entry->duration_minutes,
                'mode' => ($entry->session_mode ?? SessionMode::Online)->value,
                'place' => $entry->location === null ? null : $entry->location->name.', '.$entry->location->city,
                'context' => (string) $entry->contextOrg?->name,
                'booked' => ! $entry->is_available || $entry->has_active,
                'removable' => ! $entry->has_history && $entry->is_available,
            ];
        })->all();
    }

    /**
     * @param  array{context: int, date: string, start: string, duration: int, mode: string, location: int|null}  $input
     *
     * @throws BookingRefused
     */
    public function open(User $counselor, array $input): CalendarEntry
    {
        $mode = SessionMode::from($input['mode']);
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i', $input['date'].' '.$input['start'], $this->availability->timezone());
        $now = $this->availability->now();

        if ((int) $start->format('i') % 5 !== 0 || ! in_array((int) $input['duration'], self::durations(), true)) {
            throw new BookingRefused('planner_time_invalid');
        }

        if ($start->lte($now)) {
            throw new BookingRefused('planner_in_past');
        }

        if ($start->startOfDay()->gt($now->startOfDay()->addDays((int) config('booking.planner_horizon_days')))) {
            throw new BookingRefused('planner_too_far');
        }

        return DB::transaction(function () use ($counselor, $input, $mode, $start) {
            // The same lock booking takes: opening a time and booking one of this counselor never interleave.
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            ['context' => $contextId, 'location' => $locationId] = $this->placement($counselor, (int) $input['context'], $mode, isset($input['location']) ? (int) $input['location'] : null);

            $minutes = (int) $input['duration'];

            if (! $this->availability->isFree((int) $counselor->getKey(), $start, $minutes) || $this->overlapsOpenTime((int) $counselor->getKey(), $start, $minutes)) {
                throw new BookingRefused('planner_overlap');
            }

            return CalendarEntry::create([
                'counselor_user_id' => $counselor->getKey(),
                'entry_type' => CalendarEntryType::AvailableSlot,
                'context_org_id' => $contextId,
                'entry_date' => $start->format('Y-m-d'),
                'start_time' => $start->format('H:i:s'),
                'duration_minutes' => $minutes,
                'session_mode' => $mode,
                'location_id' => $locationId,
                'is_available' => true,
            ]);
        });
    }

    /**
     * Where a time is held: a context the counselor works in and, for in person, one of THAT organization's places. An online time keeps no
     * place. Also used to check a weekly-hours line.
     *
     * @return array{context: int, location: int|null}
     *
     * @throws BookingRefused planner_context_invalid | planner_place_invalid
     */
    public function placement(User $counselor, int $contextId, SessionMode $mode, ?int $locationId): array
    {
        $context = collect($this->contexts($counselor))->firstWhere('id', $contextId);

        if ($context === null) {
            throw new BookingRefused('planner_context_invalid');
        }

        if ($mode !== SessionMode::Physical) {
            return ['context' => $contextId, 'location' => null];
        }

        if ($locationId === null || ! in_array($locationId, array_column($context['locations'], 'id'), true)) {
            throw new BookingRefused('planner_place_invalid');
        }

        return ['context' => $contextId, 'location' => $locationId];
    }

    /**
     * Removes one open time that has never had a booking.
     *
     * @throws BookingRefused planner_not_found | planner_has_booking
     */
    public function remove(User $counselor, string $publicId): void
    {
        DB::transaction(function () use ($counselor, $publicId) {
            CounselorProfile::query()->where('user_id', $counselor->getKey())->lockForUpdate()->first();

            $entry = CalendarEntry::query()
                ->where('counselor_user_id', $counselor->getKey())->where('entry_type', CalendarEntryType::AvailableSlot->value)
                ->where('public_id', strtolower($publicId))->first();

            if ($entry === null) {
                throw new BookingRefused('planner_not_found');
            }

            if (! $entry->is_available || $entry->bookings()->exists()) {
                throw new BookingRefused('planner_has_booking');
            }

            $entry->delete();
        });
    }

    /** Would the stretch sit on top of another OPEN time of this counselor (any context)? Touching at the edge does not count. */
    private function overlapsOpenTime(int $counselorUserId, CarbonImmutable $start, int $minutes): bool
    {
        $end = $start->addMinutes($minutes);

        $others = CalendarEntry::query()
            ->where('counselor_user_id', $counselorUserId)->where('entry_type', CalendarEntryType::AvailableSlot->value)
            ->whereDate('entry_date', '>=', $start->subDay()->format('Y-m-d'))->whereDate('entry_date', '<=', $start->addDay()->format('Y-m-d'))
            ->get(['id', 'entry_date', 'start_time', 'duration_minutes']);

        foreach ($others as $other) {
            $otherStart = $this->availability->startOf($other);

            if ($start->getTimestamp() < $otherStart->addMinutes((int) $other->duration_minutes)->getTimestamp() && $end->getTimestamp() > $otherStart->getTimestamp()) {
                return true;
            }
        }

        return false;
    }
}
