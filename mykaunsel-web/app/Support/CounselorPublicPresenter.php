<?php

namespace App\Support;

use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * THE ONLY PLACE that decides what a client may see about a counselor (see docs/PUBLIC_COUNSELOR_DATA.md).
 *
 * Every field is written out by hand. A model is never serialised, so a column added to the database later can not
 * appear in the app by accident. NEVER shown, on purpose: KB / PA numbers and dates, email, phone, documents, review
 * notes, complaints, suspension reasons, street address, postcode, coordinates, working hours, buffer, meeting links,
 * the row id, and (until there is an approval step) photos.
 */
class CounselorPublicPresenter
{
    /**
     * A card in the search results.
     *
     * @param  Collection<int, CalendarEntry>  $slots  this counselor's visible upcoming slots, soonest first
     * @param  list<int>  $orgIds  organizations the viewer may see
     * @return array<string, mixed>
     */
    public static function card(CounselorProfile $profile, Collection $slots, array $orgIds): array
    {
        $contexts = self::contexts($profile, $orgIds);

        return [
            'id' => $profile->public_id,
            'name' => self::name($profile, $contexts),
            'verified' => true,
            'contexts' => $contexts->map(fn ($c) => ['type' => $c['type'], 'organization' => $c['organization']])->values()->all(),
            'specializations' => self::specializations($profile)->take((int) config('counselors.directory.card_specializations', 3))->values()->all(),
            'specializations_count' => self::specializations($profile)->count(),
            'languages' => self::languages($profile),
            'session_modes' => self::modes($slots),
            'locations' => self::locations($slots),
            'next_available' => ($first = $slots->first()) ? self::slot($first, $contexts) : null,
        ];
    }

    /**
     * The profile page: the card plus everything else that is public.
     *
     * @param  Collection<int, CalendarEntry>  $slots
     * @param  list<int>  $orgIds
     * @return array<string, mixed>
     */
    public static function profile(CounselorProfile $profile, Collection $slots, array $orgIds): array
    {
        $contexts = self::contexts($profile, $orgIds);

        return array_merge(self::card($profile, $slots, $orgIds), [
            'specializations' => self::specializations($profile)->values()->all(),
            'accepts_requests' => (bool) $profile->accepts_requests,
            'upcoming_slots' => $slots->take((int) config('counselors.directory.profile_slots', 10))
                ->map(fn ($slot) => self::slot($slot, $contexts))->values()->all(),
        ]);
    }

    // ---- pieces ----------------------------------------------------------------------------

    /**
     * Where this counselor works, as far as THIS viewer may know: "independent", and/or an organization by name.
     *
     * @return Collection<int, array{type: string, organization: ?string, org_id: int}>
     */
    private static function contexts(CounselorProfile $profile, array $orgIds): Collection
    {
        return $profile->memberships
            ->filter(fn (Membership $m) => in_array($m->org_id, $orgIds, true) && $m->organization !== null)
            ->map(fn (Membership $m) => $m->organization->org_type === OrgType::Platform
                ? ['type' => 'independent', 'organization' => null, 'org_id' => $m->org_id]
                : ['type' => 'organization', 'organization' => $m->organization->name, 'org_id' => $m->org_id])
            // Independent first, then organizations A–Z.
            ->sortBy(fn ($c) => ($c['type'] === 'independent' ? '0' : '1').mb_strtolower((string) $c['organization']))
            ->values();
    }

    /** The name the counselor chose for the way this person meets them; the registered name when none was chosen. */
    private static function name(CounselorProfile $profile, Collection $contexts): string
    {
        $chosen = $contexts->contains('type', 'independent') && filled($profile->display_name_independent)
            ? $profile->display_name_independent
            : ($contexts->contains('type', 'organization') && filled($profile->display_name_org) ? $profile->display_name_org : null);

        return trim((string) ($chosen ?: $profile->user?->name));
    }

    /** @return Collection<int, string> */
    private static function specializations(CounselorProfile $profile): Collection
    {
        return $profile->specializations->pluck('specialization')->map(fn ($s) => trim((string) $s))->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** @return list<string> */
    private static function languages(CounselorProfile $profile): array
    {
        return $profile->languages->pluck('language')->map(fn ($l) => trim((string) $l))->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }

    /** @return list<string> */
    private static function modes(Collection $slots): array
    {
        return $slots->map(fn ($s) => $s->session_mode->value)->unique()->sort()->values()->all();
    }

    /**
     * Cities and states of in-person slots. NEVER the street, postcode, coordinates or the place's name.
     *
     * @return list<array{city: string, state: string}>
     */
    private static function locations(Collection $slots): array
    {
        return $slots
            ->filter(fn ($s) => $s->session_mode === SessionMode::Physical && $s->location !== null)
            ->map(fn ($s) => ['city' => (string) $s->location->city, 'state' => (string) $s->location->state])
            ->unique(fn ($l) => mb_strtolower($l['city'].'|'.$l['state']))
            ->sortBy(fn ($l) => mb_strtolower($l['state'].'|'.$l['city']))
            ->values()
            ->all();
    }

    /**
     * One slot. The time is Malaysian time with its offset (+08:00), so the phone shows the right hour anywhere.
     *
     * @param  Collection<int, array{type: string, organization: ?string, org_id: int}>  $contexts
     * @return array<string, mixed>
     */
    private static function slot(CalendarEntry $slot, Collection $contexts): array
    {
        $context = $contexts->firstWhere('org_id', $slot->context_org_id);
        $physical = $slot->session_mode === SessionMode::Physical && $slot->location !== null;

        return [
            'starts_at' => Carbon::parse($slot->entry_date->format('Y-m-d').' '.$slot->start_time, config('counselors.directory.slot_timezone'))->toIso8601String(),
            'duration_minutes' => (int) $slot->duration_minutes,
            'session_mode' => $slot->session_mode->value,
            'city' => $physical ? (string) $slot->location->city : null,
            'state' => $physical ? (string) $slot->location->state : null,
            'context' => $context ? ['type' => $context['type'], 'organization' => $context['organization']] : null,
        ];
    }
}
