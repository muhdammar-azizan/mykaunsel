<?php

namespace Tests\Feature\Membership;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Exceptions\MembershipNotEndable;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\User;
use App\Notifications\MemberLeft;
use App\Notifications\MembershipEnded;
use App\Notifications\SessionCancelledMembershipEnded;
use App\Services\MembershipEnding;
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * A client's membership ends (they leave, or the organization removes them): later sessions with the organization's counselors are cancelled
 * and their time given back, except the next 7 days; the right people are told, and the organization hears a name and nothing more.
 * The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class MembershipEndingTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    private User $hr;

    private CounselorProfile $aina;

    private CounselorProfile $budi;

    private CounselorProfile $publicCounselor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();

        $this->me = $this->client([$this->closed], signIn: false);
        $this->me->update(['name' => 'Siti Aminah']);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $this->budi = $this->counselor('Budi Hartono', [$this->closed]);
        $this->publicCounselor = $this->counselor('Dr Public', [$this->open]);
    }

    private function ending(): MembershipEnding
    {
        return app(MembershipEnding::class);
    }

    private function row(): Membership
    {
        return Membership::where('user_id', $this->me->id)->where('org_id', $this->closed->id)->firstOrFail();
    }

    private function sessionAt(CounselorProfile $counselor, string $when, $org = null): Booking
    {
        return $this->book($this->slot($counselor, $org ?? $this->closed, $when), $this->me);
    }

    public function test_leaving_cancels_later_sessions_gives_the_time_back_and_keeps_the_next_seven_days(): void
    {
        Notification::fake();

        $soon = $this->sessionAt($this->aina, '2026-11-04 10:00');
        $edge = $this->sessionAt($this->aina, '2026-11-09 09:00'); // exactly 7 days from now: still goes ahead
        $justPast = $this->sessionAt($this->budi, '2026-11-09 09:30'); // a little later: cancelled
        $later = $this->sessionAt($this->budi, '2026-11-20 14:00');
        $elsewhere = $this->sessionAt($this->publicCounselor, '2026-11-25 10:00', $this->open); // a public counselor: not theirs to lose
        $over = $this->sessionAt($this->aina, '2026-11-02 08:00'); // already started today
        $done = $this->sessionAt($this->aina, '2026-11-30 10:00');
        $done->update(['status' => BookingStatus::Completed]);

        $result = $this->ending()->leave($this->me);

        $this->assertSame(2, $result['cancelled']);
        $this->assertSame(2, $result['kept']);
        $this->assertSame(MembershipStatus::Offboarded, $this->row()->status);
        $this->assertSame('Left by the person', $this->row()->offboard_reason);

        foreach ([$justPast, $later] as $cancelled) {
            $this->assertSame(BookingStatus::Cancelled, $cancelled->fresh()->status);
            $this->assertTrue(CalendarEntry::find($cancelled->calendar_entry_id)->is_available, 'the time is given back');
        }

        foreach ([$soon, $edge, $elsewhere, $over] as $untouched) {
            $this->assertSame(BookingStatus::Confirmed, $untouched->fresh()->status);
            $this->assertFalse(CalendarEntry::find($untouched->calendar_entry_id)->is_available);
        }

        $this->assertSame(BookingStatus::Completed, $done->fresh()->status);
    }

    public function test_requests_nobody_answered_are_closed_for_that_organization_only(): void
    {
        $mine = $this->request($this->aina, $this->closed);
        $other = $this->request($this->publicCounselor, $this->open);

        $this->ending()->leave($this->me);

        $this->assertSame(BookingRequestStatus::Rejected, $mine->fresh()->status);
        $this->assertSame('You are no longer a member of this organization.', $mine->fresh()->response_reason);
        $this->assertSame(BookingRequestStatus::Pending, $other->fresh()->status);
    }

    private function request(CounselorProfile $counselor, $org): BookingRequest
    {
        return BookingRequest::create([
            'user_id' => $this->me->id, 'counselor_user_id' => $counselor->user_id, 'context_org_id' => $org->id,
            'requested_date' => '2026-11-12', 'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online',
            'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addDays(3),
        ]);
    }

    public function test_when_a_person_leaves_everyone_is_told_what_they_should_know_and_the_organization_only_hears_the_name(): void
    {
        Notification::fake();
        $this->sessionAt($this->aina, '2026-11-04 10:00');
        $this->sessionAt($this->budi, '2026-11-20 14:00');

        $this->ending()->leave($this->me);

        Notification::assertSentTo($this->me, MembershipEnded::class, function ($n) {
            $text = implode("\n", $n->toMail($this->me)->introLines);

            return str_contains($text, 'You left')
                && str_contains($text, 'Budi Hartono') && str_contains($text, 'cancelled')
                && str_contains($text, 'Aina Syazwani') && str_contains($text, 'still go ahead')
                && str_contains($text, '15555');
        });

        Notification::assertSentTo($this->budi->user, SessionCancelledMembershipEnded::class);
        Notification::assertNotSentTo($this->aina->user, SessionCancelledMembershipEnded::class); // her session still goes ahead

        Notification::assertSentTo($this->hr, MemberLeft::class, function ($n) {
            $text = implode("\n", $n->toMail($this->hr)->introLines);

            return str_contains($text, 'Siti Aminah')
                && ! str_contains($text, 'Budi') && ! str_contains($text, 'Aina') && ! str_contains($text, 'session') && ! str_contains($text, '2026');
        });

        Notification::assertNotSentTo($this->budi->user, MemberLeft::class);
    }

    public function test_an_admin_removing_a_member_does_the_same_without_telling_the_admins(): void
    {
        Notification::fake();
        $later = $this->sessionAt($this->budi, '2026-11-20 14:00');

        $result = $this->ending()->remove($this->closed, $this->row()->id, $this->hr);

        $this->assertSame(1, $result['cancelled']);
        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
        $this->assertSame('Removed by the organization', $this->row()->offboard_reason);
        Notification::assertSentTo($this->me, MembershipEnded::class, fn ($n) => str_contains(implode("\n", $n->toMail($this->me)->introLines), 'has ended your membership'));
        Notification::assertNotSentTo($this->hr, MemberLeft::class);

        $log = AuditLog::where('action', 'member.removed')->sole();
        $this->assertSame($this->me->id, $log->target_id);
        $this->assertSame($this->hr->id, $log->actor_user_id);
        $this->assertSame([$later->id], $log->metadata['cancelled_booking_ids']);
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
        $this->assertStringNotContainsString('Siti', json_encode($log->metadata));
    }

    public function test_leaving_is_audited_against_the_organization(): void
    {
        $this->ending()->leave($this->me);

        $log = AuditLog::where('action', 'member.left')->sole();
        $this->assertSame($this->closed->id, $log->target_id);
        $this->assertSame($this->me->id, $log->actor_user_id);
    }

    public function test_it_can_not_happen_twice_or_to_the_wrong_people(): void
    {
        $row = $this->row();
        $this->ending()->remove($this->closed, $row->id, $this->hr);

        $again = fn () => $this->ending()->remove($this->closed, $row->id, $this->hr);
        $this->assertThrows($again, MembershipNotEndable::class);
        $this->assertThrows(fn () => $this->ending()->leave($this->me), MembershipNotEndable::class);

        // a counselor of the organization is not removed this way
        $staff = Membership::where('user_id', $this->aina->user_id)->where('org_id', $this->closed->id)->firstOrFail();
        $this->assertThrows(fn () => $this->ending()->remove($this->closed, $staff->id, $this->hr), MembershipNotEndable::class);
        $this->assertSame(MembershipStatus::Active, $staff->fresh()->status);

        // a row of another organization is not found
        $theirs = Membership::create(['user_id' => $this->me->id, 'org_id' => $this->open->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->ending()->remove($this->closed, $theirs->id, $this->hr);
    }

    public function test_a_waiting_request_is_withdrawn_quietly_and_can_not_be_removed_by_the_organization(): void
    {
        Notification::fake();
        $waiter = User::factory()->create(['email_verified_at' => now()]);
        $code = OrgInviteCode::issue($this->closed, null, null, null);
        $pending = app(MembershipService::class)->joinViaInviteCode($waiter, $code->code);

        $this->assertThrows(fn () => $this->ending()->remove($this->closed, $pending->id, $this->hr), MembershipNotEndable::class);

        $result = $this->ending()->leave($waiter);

        $this->assertSame(0, $result['cancelled']);
        $this->assertSame(MembershipStatus::Offboarded, $pending->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_someone_who_left_can_join_another_organization(): void
    {
        $this->ending()->leave($this->me);
        $this->me->update(['email_verified_at' => now()]);

        $joined = app(MembershipService::class)->joinViaInviteCode($this->me, OrgInviteCode::issue($this->open, null, null, null)->code);

        $this->assertSame(MembershipStatus::PendingApproval, $joined->status);
        $this->assertSame($this->open->id, $joined->org_id);
    }

    public function test_an_organization_admin_removes_a_member_from_the_page_and_other_organizations_are_out_of_reach(): void
    {
        Notification::fake();
        $row = $this->row();
        $session = ['current_org_id' => $this->closed->id, 'current_role' => 'org_admin'];

        $this->actingAs($this->hr)->withSession($session)->post(route('org.members.remove', $row->id))->assertSessionHas('status');
        $this->assertSame(MembershipStatus::Offboarded, $row->fresh()->status);

        $this->actingAs($this->hr)->withSession($session)->post(route('org.members.remove', $row->id))->assertSessionHas('error');

        $stranger = $this->client([$this->open], signIn: false);
        $theirs = Membership::where('user_id', $stranger->id)->firstOrFail();
        $this->actingAs($this->hr)->withSession($session)->post(route('org.members.remove', $theirs->id))->assertNotFound();
        $this->assertSame(MembershipStatus::Active, $theirs->fresh()->status);
    }

    public function test_a_failing_mail_does_not_undo_anything(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);
        $later = $this->sessionAt($this->budi, '2026-11-20 14:00');

        $this->ending()->leave($this->me);

        $this->assertSame(MembershipStatus::Offboarded, $this->row()->status);
        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
    }
}
