<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Enums\CalendarEntryType;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Enums\SessionMode;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PracticeLocation;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Who may see which counselor, and above all what may NEVER be shown. See docs/PUBLIC_COUNSELOR_DATA.md. */
class CounselorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $platform;

    private Organization $open;

    private Organization $closed;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'Asia/Kuala_Lumpur'));

        $this->seed(PlatformSeeder::class);
        $this->platform = Organization::where('org_type', 'platform')->firstOrFail();
        $this->open = $this->org('Klinik Damai', AccessModel::Open);
        $this->closed = $this->org('Universiti Contoh', AccessModel::Closed, OrgType::University);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- builders ----------------------------------------------------------------------------

    private function org(string $name, AccessModel $access, OrgType $type = OrgType::Clinic, OrgStatus $status = OrgStatus::Active): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => $type, 'access_model' => $access, 'subscription_status' => $status]);
    }

    /** A signed-in, email-confirmed individual (optionally a member of some organizations). */
    private function client(array $memberOf = [], bool $verified = true): User
    {
        $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);

        foreach ($memberOf as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A listable counselor working in the given organizations.
     *
     * @param  list<Organization>  $orgs
     */
    private function counselor(string $name, array $orgs, array $options = []): CounselorProfile
    {
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug().'@counselor.example']);

        $profile = CounselorProfile::create([
            'user_id' => $user->id,
            'kb_number' => $options['kb'] ?? 'KB'.random_int(50000, 99999),
            'pa_number' => $options['pa'] ?? 'PA'.random_int(50000, 99999),
            'pa_valid_until' => $options['pa_until'] ?? now()->addYear()->toDateString(),
            'verification_type' => VerificationType::OrgAssigned,
            'verification_status' => $options['status'] ?? VerificationStatus::Approved,
            'meeting_provider' => 'Google Meet',
            'display_name_org' => $options['display_org'] ?? null,
            'display_name_independent' => $options['display_independent'] ?? null,
            'accepts_requests' => $options['accepts_requests'] ?? true,
            'buffer_minutes' => 15,
        ]);

        foreach ($orgs as $org) {
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Counselor, 'status' => $options['membership_status'] ?? MembershipStatus::Active, 'joined_at' => now()]);
        }

        foreach ($options['languages'] ?? ['English'] as $language) {
            $profile->languages()->create(['language' => $language]);
        }

        foreach ($options['specializations'] ?? ['Stress'] as $specialization) {
            $profile->specializations()->create(['specialization' => $specialization]);
        }

        return $profile;
    }

    private function slot(CounselorProfile $counselor, Organization $org, string $when, SessionMode $mode = SessionMode::Online, ?PracticeLocation $location = null, bool $available = true): CalendarEntry
    {
        $at = Carbon::parse($when, 'Asia/Kuala_Lumpur');

        return CalendarEntry::create([
            'counselor_user_id' => $counselor->user_id,
            'entry_type' => CalendarEntryType::AvailableSlot,
            'context_org_id' => $org->id,
            'entry_date' => $at->toDateString(),
            'start_time' => $at->format('H:i:s'),
            'duration_minutes' => 60,
            'session_mode' => $mode,
            'location_id' => $location?->id,
            'is_available' => $available,
        ]);
    }

    private function place(Organization $org, string $city = 'Kuantan', string $state = 'Pahang'): PracticeLocation
    {
        return PracticeLocation::create([
            'owner_type' => 'organization', 'owner_id' => $org->id, 'name' => 'Klinik Damai Cawangan Rahsia',
            'address' => 'No 12, Jalan Rahsia Besar, Taman Sulit', 'city' => $city, 'state' => $state, 'postcode' => '25300',
            'latitude' => 3.80770001, 'longitude' => 103.32600002, 'description' => 'Tingkat dua', 'is_primary' => true,
        ]);
    }

    private function names($response): array
    {
        return collect($response->json('data'))->pluck('name')->all();
    }

    private function list(array $query = [])
    {
        return $this->getJson('/api/v1/counselors?'.http_build_query($query));
    }

    /** Every key and every text value in a JSON answer, for the "never shown" checks. */
    private function flatten(mixed $data, array &$keys = [], array &$values = []): array
    {
        foreach ((array) $data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }

            is_array($value) ? $this->flatten($value, $keys, $values) : $values[] = (string) $value;
        }

        return [$keys, $values];
    }

    // ---- who can ask -------------------------------------------------------------------------

    public function test_only_signed_in_people_with_a_confirmed_email_can_search(): void
    {
        $this->getJson('/api/v1/counselors')->assertUnauthorized();
        $this->getJson('/api/v1/counselors/filters')->assertUnauthorized();
        $this->getJson('/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz')->assertUnauthorized();

        $this->client(verified: false);
        $this->getJson('/api/v1/counselors')->assertForbidden();
    }

    public function test_answers_are_private_and_never_cached_by_a_shared_cache(): void
    {
        $this->client();
        $this->counselor('Siti', [$this->open]);

        $this->list()->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Vary', 'Authorization');
        $this->getJson('/api/v1/counselors/filters')->assertHeader('Cache-Control', 'no-store, private');
    }

    // ---- who is visible ----------------------------------------------------------------------

    public function test_a_stranger_sees_independent_and_open_organization_counselors_but_not_closed_ones(): void
    {
        $this->client();
        $this->counselor('Tan Independent', [$this->platform]);
        $this->counselor('Aina Damai', [$this->open]);
        $this->counselor('Ahmad Universiti', [$this->closed]);

        $names = $this->names($this->list()->assertOk());

        $this->assertEqualsCanonicalizing(['Tan Independent', 'Aina Damai'], $names);
    }

    public function test_a_closed_organizations_own_members_see_its_counselors_and_its_name(): void
    {
        $this->client([$this->closed]);
        $this->counselor('Ahmad Universiti', [$this->closed]);

        $response = $this->list()->assertOk();

        $this->assertSame(['Ahmad Universiti'], $this->names($response));
        $this->assertSame([['type' => 'organization', 'organization' => 'Universiti Contoh']], $response->json('data.0.contexts'));
    }

    public function test_a_member_who_was_removed_no_longer_sees_the_closed_organization(): void
    {
        $user = $this->client([$this->closed]);
        $this->counselor('Ahmad Universiti', [$this->closed]);
        Membership::where('user_id', $user->id)->update(['status' => MembershipStatus::Offboarded]);

        $this->assertSame([], $this->names($this->list()));
    }

    public function test_the_name_of_a_closed_organization_never_reaches_a_stranger_even_through_a_shared_counselor(): void
    {
        // Siti works for the closed university AND independently. A stranger may meet her only as an independent.
        $this->client();
        $siti = $this->counselor('Siti Rahman', [$this->closed, $this->platform], ['display_org' => 'Kaunselor Siti (UC)', 'display_independent' => 'Siti Rahman Counselling']);
        $this->slot($siti, $this->closed, '2026-11-03 10:00');
        $this->slot($siti, $this->platform, '2026-11-05 15:00');

        $response = $this->list()->assertOk();
        $card = $response->json('data.0');

        $this->assertSame('Siti Rahman Counselling', $card['name']);
        $this->assertSame([['type' => 'independent', 'organization' => null]], $card['contexts']);
        // The earlier slot belongs to the closed university: not shown. Only the independent slot is.
        $this->assertSame('2026-11-05T15:00:00+08:00', $card['next_available']['starts_at']);
        $this->assertStringNotContainsString('Universiti', $response->getContent());
        $this->assertStringNotContainsString('Kaunselor Siti (UC)', $response->getContent());
    }

    public function test_the_same_counselor_shows_a_member_both_ways_and_the_earlier_slot(): void
    {
        $this->client([$this->closed]);
        $siti = $this->counselor('Siti Rahman', [$this->closed, $this->platform], ['display_independent' => 'Siti Rahman Counselling']);
        $this->slot($siti, $this->closed, '2026-11-03 10:00');
        $this->slot($siti, $this->platform, '2026-11-05 15:00');

        $card = $this->list()->json('data.0');

        $this->assertSame('2026-11-03T10:00:00+08:00', $card['next_available']['starts_at']);
        $this->assertEquals(['type' => 'organization', 'organization' => 'Universiti Contoh'], $card['next_available']['context']);
        $this->assertSame(['independent', 'organization'], array_column($card['contexts'], 'type'));
    }

    public function test_counselors_who_are_not_listable_never_appear(): void
    {
        $this->client();

        $this->counselor('Good One', [$this->open]);
        $this->counselor('Pending One', [$this->open], ['status' => VerificationStatus::Pending]);
        $this->counselor('Rejected One', [$this->open], ['status' => VerificationStatus::Rejected]);
        $this->counselor('Suspended One', [$this->open], ['status' => VerificationStatus::Suspended]);
        $this->counselor('Expired One', [$this->open], ['pa_until' => now()->subDay()->toDateString()]);
        $this->counselor('Left One', [$this->open], ['membership_status' => MembershipStatus::Offboarded]);
        $this->counselor('Suspended Org One', [$this->org('Suspended Clinic', AccessModel::Open, OrgType::Clinic, OrgStatus::Suspended)]);
        $this->counselor('Pending Org One', [$this->org('Pending Clinic', AccessModel::Open, OrgType::Clinic, OrgStatus::Pending)]);

        $this->assertSame(['Good One'], $this->names($this->list()));
    }

    public function test_a_pa_that_ends_today_still_counts_all_day(): void
    {
        $this->client();
        $this->counselor('Last Day', [$this->open], ['pa_until' => now()->toDateString()]);

        $this->assertSame(['Last Day'], $this->names($this->list()));
    }

    // ---- what is NEVER shown -----------------------------------------------------------------

    public function test_nothing_private_appears_anywhere_in_the_list_or_the_profile(): void
    {
        $this->client();
        $place = $this->place($this->open);
        $aina = $this->counselor('Aina Damai', [$this->open, $this->platform], [
            'kb' => 'KB77777', 'pa' => 'PA88888', 'pa_until' => '2027-03-31',
            'languages' => ['Bahasa Malaysia', 'English'], 'specializations' => ['Stress', 'Trauma', 'Grief', 'Anxiety'],
        ]);
        $this->slot($aina, $this->open, '2026-11-03 10:00', SessionMode::Physical, $place);
        $this->slot($aina, $this->open, '2026-11-04 10:00', SessionMode::Online);
        Booking::query()->count(); // (touches the table so a missing migration would fail here)

        $list = $this->list()->assertOk()->json();
        $profile = $this->getJson('/api/v1/counselors/'.$aina->public_id)->assertOk()->json();

        foreach ([$list, $profile] as $answer) {
            [$keys, $values] = $this->flatten($answer);
            $text = strtolower(json_encode($answer));

            foreach (['kb_number', 'pa_number', 'pa_valid_until', 'email', 'phone', 'document', 'address', 'postcode', 'latitude', 'longitude', 'password',
                'meeting', 'buffer', 'working', 'avatar', 'photo', 'review', 'complaint', 'suspen', 'reject', 'user_id', 'verification', 'checklist', 'note'] as $bad) {
                foreach ($keys as $key) {
                    $this->assertStringNotContainsString($bad, strtolower($key), "key \"{$key}\" must not be public");
                }
            }

            foreach (['kb77777', 'pa88888', '2027-03-31', 'aina-damai@counselor.example', 'jalan rahsia', 'taman sulit', '25300', '3.8077', '103.326', 'cawangan rahsia', 'tingkat dua', 'google meet'] as $secret) {
                $this->assertStringNotContainsString($secret, $text, "\"{$secret}\" must never reach the app");
            }
        }
    }

    public function test_a_card_has_exactly_the_agreed_fields_so_a_new_column_can_not_leak_by_accident(): void
    {
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open]);
        $this->slot($aina, $this->open, '2026-11-03 10:00');

        $card = $this->list()->json('data.0');
        $profile = $this->getJson('/api/v1/counselors/'.$aina->public_id)->json('data');

        $this->assertEqualsCanonicalizing(['id', 'name', 'verified', 'contexts', 'specializations', 'specializations_count', 'languages', 'session_modes', 'locations', 'next_available'], array_keys($card));
        $this->assertEqualsCanonicalizing(array_merge(array_keys($card), ['accepts_requests', 'upcoming_slots']), array_keys($profile));
        $this->assertEqualsCanonicalizing(['starts_at', 'duration_minutes', 'session_mode', 'city', 'state', 'context'], array_keys($card['next_available']));
    }

    public function test_the_only_identifier_is_a_random_public_one_not_the_row_number(): void
    {
        $this->client();
        $first = $this->counselor('Aina Damai', [$this->open]);
        $this->counselor('Tan Wei', [$this->open]);

        $ids = array_column($this->list()->json('data'), 'id');

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $id);
        }
        $this->assertContains($first->public_id, $ids);
        $this->assertNotContains((string) $first->id, $ids);
        $this->getJson('/api/v1/counselors/'.$first->id)->assertNotFound(); // counting up finds nothing
    }

    public function test_in_person_slots_show_only_the_city_and_state(): void
    {
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open]);
        $this->slot($aina, $this->open, '2026-11-03 10:00', SessionMode::Physical, $this->place($this->open, 'Kuantan', 'Pahang'));
        $this->slot($aina, $this->open, '2026-11-04 10:00', SessionMode::Physical, $this->place($this->open, 'Kuantan', 'Pahang'));
        $this->slot($aina, $this->open, '2026-11-05 10:00', SessionMode::Physical, $this->place($this->open, 'Temerloh', 'Pahang'));

        $card = $this->list()->json('data.0');

        $this->assertSame([['city' => 'Kuantan', 'state' => 'Pahang'], ['city' => 'Temerloh', 'state' => 'Pahang']], $card['locations']);
        $this->assertSame(['physical'], $card['session_modes']);
        $this->assertSame(['city' => 'Kuantan', 'state' => 'Pahang'], ['city' => $card['next_available']['city'], 'state' => $card['next_available']['state']]);
    }

    // ---- availability ------------------------------------------------------------------------

    public function test_next_available_skips_past_booked_cancelled_unavailable_and_far_away_slots(): void
    {
        $client = $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open]);

        $this->slot($aina, $this->open, '2026-11-02 08:00');                              // earlier today: gone
        $this->slot($aina, $this->open, '2026-11-02 09:00');                              // exactly now: not in the future
        $this->slot($aina, $this->open, '2026-11-03 09:00', available: false);            // marked unavailable
        $booked = $this->slot($aina, $this->open, '2026-11-03 10:00');                    // booked by someone
        Booking::create(['org_id' => $this->open->id, 'user_id' => $client->id, 'counselor_user_id' => $aina->user_id, 'calendar_entry_id' => $booked->id,
            'booking_mode' => 'slot_based', 'status' => 'confirmed', 'session_mode' => 'online']);
        $freed = $this->slot($aina, $this->open, '2026-11-03 11:00');                     // booking cancelled: free again
        Booking::create(['org_id' => $this->open->id, 'user_id' => $client->id, 'counselor_user_id' => $aina->user_id, 'calendar_entry_id' => $freed->id,
            'booking_mode' => 'slot_based', 'status' => 'cancelled', 'session_mode' => 'online']);
        $this->slot($aina, $this->open, '2026-11-04 10:00');

        $this->assertSame('2026-11-03T11:00:00+08:00', $this->list()->json('data.0.next_available.starts_at'));

        // Beyond the 60-day horizon nothing is shown.
        $later = $this->counselor('Later Person', [$this->open]);
        $this->slot($later, $this->open, '2027-03-01 10:00');
        $this->assertNull(collect($this->list()->json('data'))->firstWhere('name', 'Later Person')['next_available']);
    }

    public function test_today_is_judged_in_malaysian_time_not_utc(): void
    {
        // 00:30 on 3 Nov in Malaysia is still 2 Nov in UTC. A 08:00 slot on 3 Nov must be in the future.
        Carbon::setTestNow(Carbon::parse('2026-11-03 00:30:00', 'Asia/Kuala_Lumpur'));
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open]);
        $this->slot($aina, $this->open, '2026-11-03 08:00');

        $this->assertSame('2026-11-03T08:00:00+08:00', $this->list()->json('data.0.next_available.starts_at'));
    }

    public function test_a_slot_of_an_organization_the_counselor_has_left_is_not_offered(): void
    {
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open, $this->platform]);
        $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->slot($aina, $this->platform, '2026-11-06 10:00');
        Membership::where('user_id', $aina->user_id)->where('org_id', $this->open->id)->update(['status' => MembershipStatus::Offboarded]);

        $this->assertSame('2026-11-06T10:00:00+08:00', $this->list()->json('data.0.next_available.starts_at'));
    }

    public function test_counselors_with_a_slot_come_first_soonest_first_then_the_rest_by_name(): void
    {
        $this->client();
        $zara = $this->counselor('Zara', [$this->open]);
        $amin = $this->counselor('Amin', [$this->open]);
        $this->counselor('Budi', [$this->open]);
        $this->counselor('Alice', [$this->open]);
        $this->slot($zara, $this->open, '2026-11-03 10:00');
        $this->slot($amin, $this->open, '2026-11-05 10:00');

        $this->assertSame(['Zara', 'Amin', 'Alice', 'Budi'], $this->names($this->list()));
    }

    // ---- search and filters ------------------------------------------------------------------

    public function test_search_by_name_matches_display_names_and_registered_names_ignoring_case(): void
    {
        $this->client();
        $this->counselor('Siti Nurhaliza', [$this->open], ['display_org' => 'Kaunselor Haliza']);
        $this->counselor('Tan Wei Ming', [$this->open]);

        $this->assertSame(['Kaunselor Haliza'], $this->names($this->list(['q' => 'HALIZA'])));
        $this->assertSame(['Tan Wei Ming'], $this->names($this->list(['q' => 'wei m'])));
        $this->assertSame([], $this->names($this->list(['q' => 'nobody'])));
    }

    public function test_wildcards_in_the_search_are_plain_characters(): void
    {
        $this->client();
        $this->counselor('Siti Nurhaliza', [$this->open]);

        $this->assertSame([], $this->names($this->list(['q' => '%%'])));
        $this->assertSame([], $this->names($this->list(['q' => 'S_ti'])));
    }

    public function test_filters_by_specialization_language_session_type_and_state(): void
    {
        $this->client();
        $place = $this->place($this->open, 'Kuantan', 'Pahang');
        $a = $this->counselor('Aina', [$this->open], ['specializations' => ['Grief', 'Stress'], 'languages' => ['English']]);
        $b = $this->counselor('Budi', [$this->open], ['specializations' => ['Trauma'], 'languages' => ['Bahasa Malaysia', 'Mandarin']]);
        $this->slot($a, $this->open, '2026-11-03 10:00', SessionMode::Online);
        $this->slot($b, $this->open, '2026-11-03 10:00', SessionMode::Physical, $place);

        $this->assertSame(['Aina'], $this->names($this->list(['specialization' => 'grief'])));
        $this->assertSame(['Budi'], $this->names($this->list(['language' => 'MANDARIN'])));
        $this->assertSame(['Aina'], $this->names($this->list(['mode' => 'online'])));
        $this->assertSame(['Budi'], $this->names($this->list(['mode' => 'physical'])));
        $this->assertSame(['Budi'], $this->names($this->list(['state' => 'pahang'])));
        $this->assertSame([], $this->names($this->list(['state' => 'Johor'])));
        $this->assertSame([], $this->names($this->list(['specialization' => 'grief', 'language' => 'Mandarin'])));
    }

    public function test_session_type_means_a_slot_that_can_really_be_booked(): void
    {
        $this->client();
        $a = $this->counselor('Aina', [$this->open]);
        $this->slot($a, $this->open, '2026-11-01 10:00', SessionMode::Online); // past
        $this->slot($a, $this->open, '2026-11-03 10:00', SessionMode::Online, available: false);

        $this->assertSame([], $this->names($this->list(['mode' => 'online'])));
        $this->assertSame(['Aina'], $this->names($this->list()));
    }

    public function test_bad_search_input_is_refused(): void
    {
        $this->client();

        $this->list(['q' => 'a'])->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->list(['q' => str_repeat('x', 101)])->assertUnprocessable();
        $this->list(['mode' => 'telepathy'])->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->list(['page' => 0])->assertUnprocessable();
        $this->list(['page' => 'abc'])->assertUnprocessable();
    }

    public function test_filter_choices_come_from_what_this_person_can_really_find(): void
    {
        $this->client();
        $place = $this->place($this->open, 'Kuantan', 'Pahang');
        $a = $this->counselor('Aina', [$this->open], ['specializations' => ['Grief', 'grief ', 'Stress'], 'languages' => ['English', 'english']]);
        $this->slot($a, $this->open, '2026-11-03 10:00', SessionMode::Physical, $place);
        // A closed organization's counselor must not add choices for a stranger.
        $hidden = $this->counselor('Hidden', [$this->closed], ['specializations' => ['Secret Specialty'], 'languages' => ['Klingon']]);
        $this->slot($hidden, $this->closed, '2026-11-03 10:00', SessionMode::Physical, $this->place($this->closed, 'Johor Bahru', 'Johor'));

        $options = $this->getJson('/api/v1/counselors/filters')->assertOk()->json('data');

        $this->assertSame(['Grief', 'Stress'], $options['specializations']);
        $this->assertSame(['English'], $options['languages']);
        $this->assertSame(['physical'], $options['session_modes']);
        $this->assertSame(['Pahang'], $options['states']);
    }

    // ---- paging and the profile --------------------------------------------------------------

    public function test_results_come_twenty_at_a_time_with_totals(): void
    {
        $this->client();
        foreach (range(1, 25) as $i) {
            $this->counselor(sprintf('Counselor %02d', $i), [$this->open]);
        }

        $first = $this->list()->assertOk();
        $second = $this->list(['page' => 2])->assertOk();

        $this->assertCount(20, $first->json('data'));
        $this->assertCount(5, $second->json('data'));
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 25, 'last_page' => 2], $first->json('meta'));
        $this->assertSame('Counselor 21', $second->json('data.0.name'));
        $this->assertCount(25, array_unique(array_merge($this->names($first), $this->names($second))));

        // A page far beyond the end is the last page, and a bigger page size is simply not available.
        $this->assertSame(2, $this->list(['page' => 99])->json('meta.page'));
        $this->assertCount(20, $this->list(['per_page' => 500])->json('data'));
    }

    public function test_the_number_of_database_queries_does_not_grow_with_the_number_of_counselors(): void
    {
        $this->client();
        $first = $this->counselor('First', [$this->open]);
        $this->slot($first, $this->open, '2026-11-03 10:00'); // so the "places of the slots" query runs in both measurements
        $this->list()->assertOk(); // warm-up: the first request of a test also pays for one-time set-up queries
        DB::enableQueryLog();
        $this->list()->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(1, 30) as $i) {
            $c = $this->counselor("Person {$i}", [$this->open, $this->platform]);
            $this->slot($c, $this->open, '2026-11-03 10:00');
        }
        DB::flushQueryLog();
        $this->list()->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many, 'one extra query per counselor would be an N+1');
    }

    public function test_the_profile_adds_everything_public_and_the_upcoming_slots(): void
    {
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open], ['accepts_requests' => false, 'specializations' => ['Stress', 'Grief', 'Trauma', 'Anxiety', 'Anger']]);
        foreach (range(3, 16) as $day) {
            $this->slot($aina, $this->open, sprintf('2026-11-%02d 10:00', $day));
        }

        $card = $this->list()->json('data.0');
        $profile = $this->getJson('/api/v1/counselors/'.$aina->public_id)->assertOk()->json('data');

        $this->assertCount(3, $card['specializations']);
        $this->assertSame(5, $card['specializations_count']);
        $this->assertCount(5, $profile['specializations']);
        $this->assertFalse($profile['accepts_requests']);
        $this->assertCount(10, $profile['upcoming_slots']);
        $this->assertSame('2026-11-03T10:00:00+08:00', $profile['upcoming_slots'][0]['starts_at']);
        $this->assertTrue($profile['verified']);
    }

    public function test_a_hidden_counselor_looks_exactly_like_one_that_does_not_exist(): void
    {
        $this->client();
        $closed = $this->counselor('Ahmad Universiti', [$this->closed]);
        $suspended = $this->counselor('Suspended One', [$this->open], ['status' => VerificationStatus::Suspended]);

        $hidden = $this->getJson('/api/v1/counselors/'.$closed->public_id);
        $gone = $this->getJson('/api/v1/counselors/'.$suspended->public_id);
        $never = $this->getJson('/api/v1/counselors/01hzzzzzzzzzzzzzzzzzzzzzzz');
        $junk = $this->getJson('/api/v1/counselors/not-an-id');

        foreach ([$hidden, $gone, $never, $junk] as $answer) {
            $answer->assertNotFound();
        }
        // Same words (the debug trace, only present in development, is not part of the answer).
        $this->assertSame($never->json('message'), $hidden->json('message'));
        $this->assertSame($never->json('message'), $gone->json('message'));
        $this->assertSame($never->json('message'), $junk->json('message'));
    }

    public function test_the_profile_id_is_not_case_sensitive(): void
    {
        $this->client();
        $aina = $this->counselor('Aina Damai', [$this->open]);

        $this->getJson('/api/v1/counselors/'.strtoupper($aina->public_id))->assertOk()->assertJsonPath('data.id', $aina->public_id);
    }

    // ---- nothing is recorded -----------------------------------------------------------------

    public function test_what_someone_searches_for_is_not_recorded_anywhere(): void
    {
        $this->client();
        $this->counselor('Aina Damai', [$this->open]);
        $before = AuditLog::count();

        $this->list(['q' => 'trauma', 'specialization' => 'Trauma', 'language' => 'English', 'state' => 'Pahang'])->assertOk();
        $this->getJson('/api/v1/counselors/filters')->assertOk();

        $this->assertSame($before, AuditLog::count());
        $this->assertFalse(DB::table('cache')->where('key', 'like', '%trauma%')->exists());
    }

    // ---- limits and the other switches -------------------------------------------------------

    public function test_the_directory_has_its_own_tight_limit_per_person(): void
    {
        $user = $this->client();
        RateLimiter::clear('directory|'.$user->id);

        $codes = collect(range(1, 42))->map(fn () => $this->getJson('/api/v1/counselors')->getStatusCode());

        $this->assertSame(40, $codes->filter(fn ($c) => $c === 200)->count());
        $this->assertSame(429, $codes->last());
    }

    public function test_maintenance_and_forced_updates_apply_to_the_directory_too(): void
    {
        $this->client();
        config(['api.min_app_version' => '2.0.0']);

        $this->withHeaders(['X-App-Version' => '1.0.0'])->getJson('/api/v1/counselors')->assertStatus(426);
    }
}
