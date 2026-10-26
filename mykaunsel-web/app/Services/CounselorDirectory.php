<?php

namespace App\Services;

use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which counselors a signed-in person may see, and when they are free. Used by the mobile app's counselor search.
 *
 * THE RULES (docs/PUBLIC_COUNSELOR_DATA.md):
 *  - Only listable counselors (approved, practising certificate valid, working for an active organization).
 *  - Counselors of a CLOSED organization (a university's own counselors…) are visible only to that organization's active
 *    members. Open organizations and the Platform (independent counselors) are visible to everyone.
 *  - A slot belongs to an organization (its context). It is shown only if that context is visible to this person AND the
 *    counselor still works there, so a closed organization's slots never leak, and neither does its name.
 *  - This class decides WHO and WHEN. What is shown about a counselor is decided, field by field, in
 *    CounselorPublicPresenter.
 *
 * Nothing about what someone searches for is stored or logged.
 */
class CounselorDirectory
{
    /** @return list<int> organizations this person may see counselors and slots of */
    public function visibleOrganizationIds(User $viewer): array
    {
        $memberOf = $viewer->memberships()->where('status', MembershipStatus::Active->value)->pluck('org_id');

        return Organization::query()
            ->where('subscription_status', OrgStatus::Active->value)
            ->where(fn (Builder $q) => $q
                ->where('access_model', 'open')
                ->orWhere('org_type', OrgType::Platform->value)
                ->orWhereIn('id', $memberOf))
            ->pluck('id')
            ->all();
    }

    /**
     * One page of the search, best-available first.
     *
     * @param  array{q?: ?string, specialization?: ?string, language?: ?string, mode?: ?string, state?: ?string}  $filters
     * @return array{profiles: Collection<int, CounselorProfile>, slots: Collection<int, Collection<int, CalendarEntry>>, orgIds: list<int>, total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(User $viewer, array $filters, int $page = 1): array
    {
        $orgIds = $this->visibleOrganizationIds($viewer);
        $perPage = (int) config('counselors.directory.per_page', 20);

        $candidates = $this->filtered($orgIds, $filters)->with('user:id,name')->get(['counselor_profiles.id', 'counselor_profiles.user_id', 'counselor_profiles.display_name_org', 'counselor_profiles.display_name_independent']);

        // One query for every candidate's upcoming slots: gives next-available, session modes and places for the cards.
        $slots = $this->slotsFor($orgIds, $candidates->pluck('user_id')->all())->groupBy('counselor_user_id');

        $sorted = $candidates->sortBy([
            // Those with a slot first, soonest first; then the rest by name.
            fn ($a, $b) => $this->nextStart($slots->get($a->user_id)) <=> $this->nextStart($slots->get($b->user_id)),
            fn ($a, $b) => mb_strtolower($this->sortName($a)) <=> mb_strtolower($this->sortName($b)),
        ])->values();

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $ids = $sorted->forPage($page, $perPage)->pluck('id');
        $profiles = CounselorProfile::query()
            ->whereIn('id', $ids)
            ->with(['user:id,name', 'languages', 'specializations', 'memberships' => fn ($m) => $m
                ->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->with('organization:id,name,org_type')])
            ->get()
            ->sortBy(fn ($p) => $ids->search($p->id))
            ->values();

        return [
            'profiles' => $profiles,
            'slots' => $profiles->mapWithKeys(fn ($p) => [$p->user_id => $slots->get($p->user_id, collect())]),
            'orgIds' => $orgIds,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * One counselor, or null when they do not exist OR this person may not see them (the same answer for both, so
     * nobody can tell a hidden counselor from one that is not there).
     *
     * @return array{profile: CounselorProfile, slots: Collection<int, CalendarEntry>, orgIds: list<int>}|null
     */
    public function find(User $viewer, string $publicId): ?array
    {
        $orgIds = $this->visibleOrganizationIds($viewer);

        $profile = $this->eligible($orgIds)
            ->where('counselor_profiles.public_id', $publicId)
            ->with(['user:id,name', 'languages', 'specializations', 'memberships' => fn ($m) => $m
                ->where('role', MembershipRole::Counselor->value)->where('status', MembershipStatus::Active->value)->with('organization:id,name,org_type')])
            ->first();

        if ($profile === null) {
            return null;
        }

        return [
            'profile' => $profile,
            'slots' => $this->slotsFor($orgIds, [$profile->user_id]),
            'orgIds' => $orgIds,
        ];
    }

    /**
     * The choices for the filter screen, taken from what is really on offer to this person (so no filter ever gives an
     * empty list).
     *
     * @return array{specializations: list<string>, languages: list<string>, session_modes: list<string>, states: list<string>}
     */
    public function filterOptions(User $viewer): array
    {
        $orgIds = $this->visibleOrganizationIds($viewer);
        $profileIds = $this->eligible($orgIds)->pluck('counselor_profiles.id');
        $userIds = $this->eligible($orgIds)->pluck('counselor_profiles.user_id')->all();

        $distinct = fn (Collection $values) => $values
            ->map(fn ($v) => trim((string) $v))
            ->filter()
            ->groupBy(fn ($v) => mb_strtolower($v))
            ->map(fn ($group) => $group->first())
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $slots = $this->slotsFor($orgIds, $userIds);

        return [
            'specializations' => $distinct(\App\Models\CounselorSpecialization::whereIn('counselor_profile_id', $profileIds)->pluck('specialization')),
            'languages' => $distinct(\App\Models\CounselorLanguage::whereIn('counselor_profile_id', $profileIds)->pluck('language')),
            'session_modes' => $slots->pluck('session_mode')->map(fn ($m) => $m->value)->unique()->sort()->values()->all(),
            'states' => $distinct($slots->filter(fn ($s) => $s->session_mode === SessionMode::Physical)->map(fn ($s) => $s->location?->state)),
        ];
    }

    // ---- internals -------------------------------------------------------------------------

    /** Listable, and working in at least one organization this person may see. */
    private function eligible(array $orgIds): Builder
    {
        return CounselorProfile::query()
            ->listable()
            ->whereHas('memberships', fn (Builder $m) => $m
                ->where('role', MembershipRole::Counselor->value)
                ->where('status', MembershipStatus::Active->value)
                ->whereIn('org_id', $orgIds));
    }

    /** Eligible plus the person's search and filters. */
    private function filtered(array $orgIds, array $filters): Builder
    {
        $query = $this->eligible($orgIds);

        if (filled($filters['q'] ?? null)) {
            // "!" is the LIKE escape character (a backslash means different things to SQLite and MySQL).
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($filters['q']))).'%';

            $query->where(fn (Builder $q) => $q
                ->whereRaw("lower(counselor_profiles.display_name_org) like ? escape '!'", [$like])
                ->orWhereRaw("lower(counselor_profiles.display_name_independent) like ? escape '!'", [$like])
                ->orWhereHas('user', fn (Builder $u) => $u->whereRaw("lower(users.name) like ? escape '!'", [$like])));
        }

        if (filled($filters['specialization'] ?? null)) {
            $query->whereHas('specializations', fn (Builder $s) => $s->whereRaw('lower(specialization) = ?', [mb_strtolower(trim($filters['specialization']))]));
        }

        if (filled($filters['language'] ?? null)) {
            $query->whereHas('languages', fn (Builder $l) => $l->whereRaw('lower(language) = ?', [mb_strtolower(trim($filters['language']))]));
        }

        // Session type and state mean "has a slot I can actually book like that", not just "says so".
        if (filled($filters['mode'] ?? null) || filled($filters['state'] ?? null)) {
            $slot = $this->slotQuery($orgIds)->whereColumn('calendar_entries.counselor_user_id', 'counselor_profiles.user_id');

            if (filled($filters['mode'] ?? null)) {
                $slot->where('calendar_entries.session_mode', $filters['mode']);
            }

            if (filled($filters['state'] ?? null)) {
                $slot->where('calendar_entries.session_mode', SessionMode::Physical->value)
                    ->whereHas('location', fn (Builder $l) => $l->whereRaw('lower(state) = ?', [mb_strtolower(trim($filters['state']))]));
            }

            $query->whereExists($slot->selectRaw('1'));
        }

        return $query;
    }

    /**
     * Slots a person may see: available, in the future (Malaysian time), not already booked, in an organization this
     * person may see where the counselor still works.
     */
    private function slotQuery(array $orgIds): Builder
    {
        $now = Carbon::now(config('counselors.directory.slot_timezone'));

        return CalendarEntry::query()
            ->where('calendar_entries.entry_type', CalendarEntryType::AvailableSlot->value)
            ->where('calendar_entries.is_available', true)
            ->whereIn('calendar_entries.context_org_id', $orgIds)
            ->where(fn (Builder $q) => $q
                ->whereDate('calendar_entries.entry_date', '>', $now->toDateString())
                ->orWhere(fn (Builder $today) => $today
                    ->whereDate('calendar_entries.entry_date', '=', $now->toDateString())
                    ->where('calendar_entries.start_time', '>', $now->format('H:i:s'))))
            ->whereExists(fn ($m) => $m->selectRaw('1')->from('memberships')
                ->whereColumn('memberships.user_id', 'calendar_entries.counselor_user_id')
                ->whereColumn('memberships.org_id', 'calendar_entries.context_org_id')
                ->where('memberships.role', MembershipRole::Counselor->value)
                ->where('memberships.status', MembershipStatus::Active->value))
            ->whereNotExists(fn ($b) => $b->selectRaw('1')->from('bookings')
                ->whereColumn('bookings.calendar_entry_id', 'calendar_entries.id')
                ->where('bookings.status', '!=', 'cancelled'));
    }

    /** @return Collection<int, CalendarEntry> soonest first, only as far ahead as the horizon */
    private function slotsFor(array $orgIds, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $horizon = Carbon::now(config('counselors.directory.slot_timezone'))->addDays((int) config('counselors.directory.slot_horizon_days', 60));

        return $this->slotQuery($orgIds)
            ->whereIn('calendar_entries.counselor_user_id', $userIds)
            ->whereDate('calendar_entries.entry_date', '<=', $horizon->toDateString())
            ->with('location:id,city,state')
            ->orderBy('calendar_entries.entry_date')
            ->orderBy('calendar_entries.start_time')
            ->get(['calendar_entries.counselor_user_id', 'calendar_entries.context_org_id', 'calendar_entries.entry_date', 'calendar_entries.start_time',
                'calendar_entries.duration_minutes', 'calendar_entries.session_mode', 'calendar_entries.location_id']);
    }

    /** Sortable "when is the first free slot"; no slot sorts last. */
    private function nextStart(?Collection $slots): string
    {
        $first = $slots?->first();

        return $first ? $first->entry_date->format('Y-m-d').' '.$first->start_time : '9999-12-31 23:59:59';
    }

    private function sortName(CounselorProfile $profile): string
    {
        return $profile->display_name_independent ?: ($profile->display_name_org ?: (string) $profile->user?->name);
    }
}
