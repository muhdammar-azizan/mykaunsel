<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorLeftOrganization;
use App\Notifications\CounselorRemovedFromOrganization;
use App\Notifications\TimeRequestAnsweredClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor's Organizations page: where they work, and leaving an organization by themselves. Leaving has the same effects as the organization
 * ending the assignment, but the counselor chooses it. The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class CounselorOrganizationsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform, $this->open]);

        $this->hr = User::factory()->create(['name' => 'Hajar Admin']);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->open->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?CounselorProfile $profile = null, ?int $org = null)
    {
        return $this->actingAs(($profile ?? $this->aina)->user)->withSession(['current_org_id' => $org ?? $this->platform->id, 'current_role' => 'counselor']);
    }

    private function membership(?CounselorProfile $who = null, $org = null): Membership
    {
        return Membership::where('user_id', ($who ?? $this->aina)->user_id)->where('org_id', ($org ?? $this->open)->id)->where('role', MembershipRole::Counselor->value)->sole();
    }

    private function leave(Membership $membership, ?string $reason = 'I am moving to another city.', ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.organizations.leave', $membership->id), ['reason' => $reason]);
    }

    public function test_the_page_lists_where_the_counselor_works_and_offers_leave_for_organizations_only(): void
    {
        $page = $this->as()->get(route('counselor.organizations'))->assertOk();

        $page->assertSee('Independent practice (MyKaunsel)')->assertSee('Klinik Damai')->assertSee('Active')->assertSee('Since')
            ->assertSee('Managed by MyKaunsel');
        $this->assertSame(1, substr_count($page->getContent(), route('counselor.organizations.leave', $this->membership()->id)), 'one Leave form: the organization');
        $page->assertDontSee(route('counselor.organizations.leave', $this->membership(null, $this->platform)->id), false);
    }

    public function test_it_shows_only_my_own_places(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform, $this->closed]);

        $this->as()->get(route('counselor.organizations'))->assertOk()->assertDontSee($this->closed->name);
        $this->as($bakar)->get(route('counselor.organizations'))->assertOk()->assertSee($this->closed->name)->assertDontSee('Klinik Damai');
    }

    public function test_leaving_ends_that_organization_only_with_the_same_effects_as_being_removed(): void
    {
        Notification::fake();
        $client = User::factory()->create(['name' => 'Siti Client']);
        $asking = User::factory()->create();

        $later = $this->book($this->slot($this->aina, $this->open, '2026-11-05 10:00'), $client);
        $past = $this->book($this->slot($this->aina, $this->open, '2026-10-28 10:00'), $client, BookingStatus::Completed);
        $elsewhere = $this->book($this->slot($this->aina, $this->platform, '2026-11-06 10:00'), User::factory()->create());
        $free = $this->slot($this->aina, $this->open, '2026-11-07 10:00');
        $line = CounselorWeeklyHour::create(['counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->open->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 60, 'session_mode' => 'online']);
        $waiting = BookingRequest::create([
            'user_id' => $asking->id, 'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->open->id, 'requested_date' => '2026-11-09',
            'requested_time' => '10:00:00', 'duration_minutes' => 60, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addHours(40),
        ]);
        $membership = $this->membership();

        $this->leave($membership)->assertSessionHasNoErrors()->assertRedirect(route('counselor.organizations'));

        $this->assertSame(MembershipStatus::Offboarded, $membership->fresh()->status);
        $this->assertSame('I am moving to another city.', $membership->fresh()->offboard_reason);
        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
        $this->assertSame('counselor', $later->fresh()->cancelled_by);
        $this->assertTrue((bool) CalendarEntry::find($later->calendar_entry_id)->is_available);
        $this->assertSame(BookingStatus::Completed, $past->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $elsewhere->fresh()->status);
        $this->assertNull(CalendarEntry::find($free->id));
        $this->assertNull(CounselorWeeklyHour::find($line->id));
        $this->assertSame(BookingRequestStatus::Expired, $waiting->fresh()->status);
        $this->assertSame(MembershipStatus::Active, $this->membership(null, $this->platform)->status);

        Notification::assertSentTo($client, ClientSessionNotice::class, fn ($n) => ! str_contains(json_encode($n->toMail($client)->toArray()), 'another city'));
        Notification::assertSentTo($asking, TimeRequestAnsweredClient::class);
        $this->assertSame(1, UserNotification::query()->ownedBy($client)->where('type', 'booking.cancelled')->count());
        $this->assertSame(1, UserNotification::query()->ownedBy($asking)->where('type', 'request.expired')->count());

        // The organization's admin is told; the counselor is not "told they were removed"; nobody sees the reason.
        Notification::assertSentTo($this->hr, CounselorLeftOrganization::class, fn ($n) => ! str_contains(json_encode($n->toMail($this->hr)->toArray()), 'another city'));
        Notification::assertNotSentTo($this->aina->user, CounselorRemovedFromOrganization::class);
    }

    public function test_leaving_is_audited_with_ids_only(): void
    {
        Notification::fake();
        $later = $this->book($this->slot($this->aina, $this->open, '2026-11-05 10:00'), User::factory()->create(['name' => 'Siti Client']));
        $membership = $this->membership();

        $this->leave($membership, 'A private reason about my family.')->assertSessionHasNoErrors();

        $entry = AuditLog::where('action', 'counselor.left_org')->sole();
        $this->assertSame($this->aina->user_id, $entry->actor_user_id);
        $this->assertSame($membership->id, $entry->metadata['membership_id']);
        $this->assertSame([$later->id], $entry->metadata['cancelled_booking_ids']);

        $everything = json_encode($entry->getAttributes());
        $this->assertStringNotContainsString('private reason', $everything);
        $this->assertStringNotContainsString('Siti', $everything);
        $this->assertSame(0, AuditLog::where('action', 'counselor.removed_from_org')->count());
    }

    public function test_a_reason_is_needed_and_nothing_changes_without_it(): void
    {
        Notification::fake();
        $membership = $this->membership();

        $this->leave($membership, '')->assertSessionHasErrors('reason');
        $this->leave($membership, 'ab')->assertSessionHasErrors('reason');

        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'counselor.left_org')->count());
    }

    public function test_the_independent_practice_cannot_be_left_here(): void
    {
        Notification::fake();
        $independent = $this->membership(null, $this->platform);

        $this->leave($independent)->assertSessionHasErrors('organization');

        $this->assertSame(MembershipStatus::Active, $independent->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'counselor.left_org')->count());
    }

    public function test_someone_elses_membership_is_a_404_and_is_not_touched(): void
    {
        Notification::fake();
        $bakar = $this->counselor('Bakar', [$this->platform, $this->open]);
        $theirs = $this->membership($bakar);

        $this->leave($theirs)->assertNotFound();

        $this->assertSame(MembershipStatus::Active, $theirs->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'counselor.left_org')->count());
    }

    public function test_an_unknown_id_is_a_404(): void
    {
        $this->as()->post(route('counselor.organizations.leave', 999999), ['reason' => 'Because.'])->assertNotFound();
    }

    public function test_leaving_twice_is_refused_and_writes_no_second_entry(): void
    {
        Notification::fake();
        $membership = $this->membership();

        $this->leave($membership)->assertSessionHasNoErrors();
        $this->leave($membership)->assertSessionHasErrors('organization');

        $this->assertSame(1, AuditLog::where('action', 'counselor.left_org')->count());
        Notification::assertSentToTimes($this->hr, CounselorLeftOrganization::class, 1);
    }

    public function test_the_page_then_shows_it_as_ended_and_the_portal_moves_to_another_place_if_it_was_the_current_one(): void
    {
        Notification::fake();
        $membership = $this->membership();

        $this->as(null, $this->open->id)->post(route('counselor.organizations.leave', $membership->id), ['reason' => 'Moving on.'])->assertSessionHasNoErrors();

        $this->assertSame($this->platform->id, session('current_org_id'));
        $this->get(route('counselor.organizations'))->assertOk()->assertSee('Ended on')->assertSee('Ended');
    }

    public function test_only_counselors_may_open_it(): void
    {
        $client = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($client)->get(route('counselor.organizations'))->assertForbidden();
        $this->actingAs($client)->post(route('counselor.organizations.leave', $this->membership()->id), ['reason' => 'Nope.'])->assertForbidden();
        $this->assertSame(MembershipStatus::Active, $this->membership()->status);
    }
}
