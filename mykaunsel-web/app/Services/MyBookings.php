<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\SessionMode;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\User;
use App\Support\CounselorPublicPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * "My bookings": a person's OWN bookings, as the app shows them (docs/AVAILABILITY.md, "My bookings"). Read only.
 *
 * - Only the person's own bookings are ever read; another person's booking is "not found", exactly like one that does not exist.
 * - Upcoming = confirmed and the session has not ended (a session under way is still upcoming, so "Join session" works). Everything else
 *   is Past. Upcoming is small by design (the cap), so it is worked out in PHP; Past is paged in SQL, newest first.
 * - The meeting link is sent ONLY inside its window (`booking.meeting_link_visible_minutes` before the start until the end) and only for a
 *   confirmed booking. The full address is sent only for a confirmed in-person booking. Never an organization name, the client's own
 *   email or phone, a row id, or the counselor's email.
 */
class MyBookings
{
    public const PAST_PER_PAGE = 20;

    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly CounselorDirectory $directory,
        private readonly BookingCancellation $cancellation,
    ) {}

    /** @return list<array<string, mixed>> */
    public function upcoming(User $user): array
    {
        $orgIds = $this->directory->visibleOrganizationIds($user);

        return $this->upcomingBookings($user)
            ->map(fn (Booking $booking) => $this->summary($booking, $orgIds))
            ->values()
            ->all();
    }

    /** @return array{data: list<array<string, mixed>>, page: int, last_page: int, total: int} */
    public function past(User $user, int $page): array
    {
        $orgIds = $this->directory->visibleOrganizationIds($user);
        $upcomingIds = $this->upcomingBookings($user)->modelKeys();

        $paginator = $this->query($user)
            ->whereNotIn('bookings.id', $upcomingIds)
            ->orderByDesc('calendar_entries.entry_date')
            ->orderByDesc('calendar_entries.start_time')
            ->orderByDesc('bookings.id')
            ->paginate(self::PAST_PER_PAGE, ['bookings.*'], 'page', $page);

        return $this->page($paginator, $orgIds);
    }

    /** @return array<string, mixed>|null null = not found (it does not exist, or it is not this person's) */
    public function find(User $user, string $publicId): ?array
    {
        $booking = $this->query($user)->where('bookings.public_id', strtolower($publicId))->first(['bookings.*']);

        return $booking === null ? null : $this->details($booking, $this->directory->visibleOrganizationIds($user));
    }

    // ---- queries ----------------------------------------------------------------------------------------------

    /** The person's own bookings that have a slot (Mode B requests have none yet), with everything the answer needs in a fixed number of queries. */
    private function query(User $user)
    {
        return Booking::query()
            ->join('calendar_entries', 'calendar_entries.id', '=', 'bookings.calendar_entry_id')
            ->where('bookings.user_id', $user->getKey())
            ->with([
                'calendarEntry.location',
                'calendarEntry.contextOrg',
                'counselor.counselorProfile.user',
                'counselor.counselorProfile.memberships' => fn ($m) => $m->where('role', 'counselor')->where('status', 'active')->with('organization:id,name,org_type'),
            ]);
    }

    /** @return Collection<int, Booking> soonest first */
    private function upcomingBookings(User $user): Collection
    {
        $now = $this->availability->now();

        return $this->query($user)
            ->where('bookings.status', BookingStatus::Confirmed->value)
            // Today or later; yesterday too, so a session that crossed midnight is not lost. The exact end is checked in PHP.
            ->whereDate('calendar_entries.entry_date', '>=', $now->subDay()->format('Y-m-d'))
            ->get(['bookings.*'])
            ->filter(fn (Booking $booking) => $this->endOf($booking->calendarEntry)->gt($now))
            ->sortBy(fn (Booking $booking) => $this->availability->startOf($booking->calendarEntry)->getTimestamp())
            ->values();
    }

    /** @return array{data: list<array<string, mixed>>, page: int, last_page: int, total: int} */
    private function page(LengthAwarePaginator $paginator, array $orgIds): array
    {
        return [
            'data' => $paginator->getCollection()->map(fn (Booking $booking) => $this->summary($booking, $orgIds))->values()->all(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    // ---- shapes -------------------------------------------------------------------------------------------------

    private function endOf(CalendarEntry $entry): CarbonImmutable
    {
        return $this->availability->startOf($entry)->addMinutes((int) $entry->duration_minutes);
    }

    private function modeOf(Booking $booking): SessionMode
    {
        return $booking->session_mode;
    }

    /** @return array<string, mixed> */
    private function summary(Booking $booking, array $orgIds): array
    {
        $entry = $booking->calendarEntry;
        $start = $this->availability->startOf($entry);
        $end = $this->endOf($entry);
        $now = $this->availability->now();
        $mode = $this->modeOf($booking);
        $profile = $booking->counselor?->counselorProfile;
        $soon = $booking->status === BookingStatus::Confirmed && $end->gt($now)
            && $start->lte($now->addMinutes((int) config('booking.starting_soon_minutes')));

        return [
            'id' => $booking->public_id,
            'status' => $booking->status->value,
            'starting_soon' => $soon,
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'duration_minutes' => (int) $entry->duration_minutes,
            'mode' => $mode->value,
            'counselor' => [
                // The counselor's public id, so "Book again" can open their page. Null if they no longer have a profile.
                'id' => $profile?->public_id,
                'display_name' => $profile === null ? '' : CounselorPublicPresenter::displayName($profile, $orgIds),
            ],
            'location' => $mode === SessionMode::Physical && $entry->location !== null
                ? ['name' => (string) $entry->location->name, 'city' => (string) $entry->location->city]
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function details(Booking $booking, array $orgIds): array
    {
        $entry = $booking->calendarEntry;
        $start = $this->availability->startOf($entry);
        $end = $this->endOf($entry);
        $now = $this->availability->now();
        $confirmed = $booking->status === BookingStatus::Confirmed;
        $rule = $this->cancellation->rule($booking, $entry);
        $maxReschedules = (int) config('booking.max_reschedules');

        return $this->summary($booking, $orgIds) + [
            'cancellation_hours' => $rule['hours'],
            'cancellation_deadline_at' => $confirmed && $rule['deadline']->gt($now) ? $rule['deadline']->toIso8601String() : null,
            // What the rules say now (the server decides; the app only shows). The same rule cancelling itself uses.
            'can_cancel' => $rule['allowed'],
            'reschedules_left' => max(0, $maxReschedules - (int) $booking->reschedule_count),
            'meeting' => $this->meeting($booking, $start, $end, $now),
            'place' => $this->place($booking, $entry),
        ];
    }

    /**
     * Online sessions only. `state`: `opens_later` (before the window), `available` (inside it, link added: the only time `url` is sent),
     * `not_added` (inside it, the counselor has not pasted a link yet), `ended`. Null for in-person, and for a booking that is not confirmed.
     *
     * @return array<string, mixed>|null
     */
    private function meeting(Booking $booking, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $now): ?array
    {
        if ($booking->status !== BookingStatus::Confirmed || $this->modeOf($booking) !== SessionMode::Online) {
            return null;
        }

        $opens = $start->subMinutes((int) config('booking.meeting_link_visible_minutes'));
        $hasLink = filled($booking->meeting_url);

        $state = match (true) {
            $now->gte($end) => 'ended',
            $now->lt($opens) => 'opens_later',
            $hasLink => 'available',
            default => 'not_added',
        };

        return [
            'state' => $state,
            'opens_at' => $opens->toIso8601String(),
            'closes_at' => $end->toIso8601String(),
            'provider' => $state === 'available' ? $booking->meeting_provider : null,
            'url' => $state === 'available' ? (string) $booking->meeting_url : null,
        ];
    }

    /**
     * The full address of a confirmed in-person session, for its owner only. Null for online, and for a booking that is not confirmed.
     *
     * @return array<string, mixed>|null
     */
    private function place(Booking $booking, CalendarEntry $entry): ?array
    {
        if ($booking->status !== BookingStatus::Confirmed || $this->modeOf($booking) !== SessionMode::Physical || $entry->location === null) {
            return null;
        }

        $place = $entry->location;

        return [
            'name' => (string) $place->name,
            'address' => (string) $place->address,
            'city' => (string) $place->city,
            'state' => (string) $place->state,
            'postcode' => (string) $place->postcode,
            'latitude' => $place->latitude === null ? null : (float) $place->latitude,
            'longitude' => $place->longitude === null ? null : (float) $place->longitude,
        ];
    }
}
