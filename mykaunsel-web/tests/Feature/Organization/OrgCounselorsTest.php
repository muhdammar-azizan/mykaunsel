<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\Membership;
use App\Models\OrgCounselorInvitation;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\ClientSessionNotice;
use App\Notifications\CounselorInvitation;
use App\Notifications\CounselorRemovedFromOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** An organization admin's Counselors page, and the counselor answering an invitation. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgCounselorsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hr()
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
    }

    private function asCounselor(CounselorProfile $profile)
    {
        return $this->actingAs($profile->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    /** An independent counselor (platform organization) with a confirmed address. */
    private function independent(string $name = 'Aina', array $options = []): CounselorProfile
    {
        $profile = $this->counselor($name, [$this->platform], $options);
        $profile->user->forceFill(['email_verified_at' => now()])->save();

        return $profile;
    }

    private function invitation(string $email, ?Organization $org = null): OrgCounselorInvitation
    {
        return OrgCounselorInvitation::create(['org_id' => ($org ?? $this->acme)->id, 'email' => $email, 'expires_at' => now()->addDays(30)]);
    }

    private function audit(string $action)
    {
        return AuditLog::where('action', $action)->get();
    }

    // ---- the page -----------------------------------------------------------------------------------------------

    public function test_the_page_lists_this_organizations_counselors_only_with_their_verification(): void
    {
        $mine = $this->counselor('Aina', [$this->acme]);
        $this->counselor('Bakar', [$this->org('Beta', AccessModel::Closed, OrgType::Corporate)]);
        $this->counselor('Cahaya', [$this->acme], ['status' => VerificationStatus::Suspended]);

        $this->hr()->get(route('org.counselors'))->assertOk()
            ->assertSee('Your counselors (2)')->assertSee($mine->user->name)->assertSee('Cahaya')->assertSee('Verified')->assertSee('Suspended')->assertDontSee('Bakar');
    }

    public function test_only_an_organization_admin_opens_it(): void
    {
        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.counselors'))->assertForbidden();
    }

    // ---- inviting -------------------------------------------------------------------------------------------------

    public function test_inviting_makes_one_invitation_sends_the_mail_and_audits_without_the_address(): void
    {
        Notification::fake();

        $this->hr()->post(route('org.counselors.invite'), ['email' => '  Aina@Counselor.Example '])->assertSessionHas('status');

        $invitation = OrgCounselorInvitation::sole();
        $this->assertSame('aina@counselor.example', $invitation->email);
        Notification::assertSentOnDemand(CounselorInvitation::class);
        $this->assertStringNotContainsString('aina@', json_encode($this->audit('counselor.invited')->toArray()));

        $this->hr()->post(route('org.counselors.invite'), ['email' => 'aina@counselor.example'])->assertSessionHasErrors('email');
        $this->assertSame(1, OrgCounselorInvitation::count());
    }

    public function test_someone_who_already_works_here_can_not_be_invited_and_an_invitation_can_be_cancelled(): void
    {
        $aina = $this->counselor('Aina', [$this->acme]);
        $this->hr()->post(route('org.counselors.invite'), ['email' => $aina->user->email])->assertSessionHasErrors('email');

        $invitation = $this->invitation('new@counselor.example');
        $this->hr()->post(route('org.counselor-invitations.revoke', $invitation->id))->assertSessionHas('status');
        $this->assertNotNull($invitation->fresh()->revoked_at);

        $other = $this->invitation('x@counselor.example', $this->org('Beta', AccessModel::Closed, OrgType::Corporate));
        $this->hr()->post(route('org.counselor-invitations.revoke', $other->id))->assertNotFound();
        $this->assertNull($other->fresh()->revoked_at);
    }

    // ---- the counselor answers ------------------------------------------------------------------------------------

    public function test_an_approved_counselor_accepts_and_now_works_for_the_organization_as_well(): void
    {
        $aina = $this->independent();
        $invitation = $this->invitation($aina->user->email);

        $this->asCounselor($aina)->get(route('counselor.dashboard'))->assertOk()->assertSee('Acme Berhad')->assertSee('invited you to be one of its counselors');
        $this->asCounselor($aina)->post(route('counselor.invitations.accept', $invitation->public_id))->assertSessionHas('status');

        $membership = Membership::where('user_id', $aina->user_id)->where('org_id', $this->acme->id)->sole();
        $this->assertSame(MembershipRole::Counselor, $membership->role);
        $this->assertSame(MembershipStatus::Active, $membership->status);
        $this->assertTrue(Membership::where('user_id', $aina->user_id)->where('org_id', $this->platform->id)->exists());
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->hr()->get(route('org.counselors'))->assertSee('Your counselors (1)');
    }

    public function test_a_counselor_that_is_not_approved_yet_can_not_accept(): void
    {
        $aina = $this->independent('Aina', ['status' => VerificationStatus::Pending]);
        $invitation = $this->invitation($aina->user->email);

        $this->asCounselor($aina)->post(route('counselor.invitations.accept', $invitation->public_id))->assertSessionHasErrors('invitation');
        $this->assertFalse(Membership::where('user_id', $aina->user_id)->where('org_id', $this->acme->id)->exists());
    }

    public function test_an_organization_that_does_not_allow_independent_practice_refuses_an_independent_counselor(): void
    {
        $this->acme->update(['allow_counselor_freelance' => false]);
        $aina = $this->independent();
        $invitation = $this->invitation($aina->user->email);

        $this->asCounselor($aina)->post(route('counselor.invitations.accept', $invitation->public_id))->assertSessionHasErrors('invitation');
        $this->assertNull($invitation->fresh()->accepted_at);

        $this->acme->update(['allow_counselor_freelance' => true]);
        $this->asCounselor($aina)->post(route('counselor.invitations.accept', $invitation->public_id))->assertSessionHas('status');
    }

    public function test_someone_elses_invitation_is_a_404_and_declining_closes_it(): void
    {
        $aina = $this->independent('Aina');
        $bakar = $this->independent('Bakar');
        $invitation = $this->invitation($aina->user->email);

        $this->asCounselor($bakar)->post(route('counselor.invitations.accept', $invitation->public_id))->assertNotFound();
        $this->asCounselor($bakar)->post(route('counselor.invitations.decline', $invitation->public_id))->assertNotFound();

        $this->asCounselor($aina)->post(route('counselor.invitations.decline', $invitation->public_id))->assertSessionHas('status');
        $this->assertNotNull($invitation->fresh()->declined_at);
        $this->asCounselor($aina)->post(route('counselor.invitations.accept', $invitation->public_id))->assertNotFound();
    }

    // ---- removing -------------------------------------------------------------------------------------------------

    public function test_removing_a_counselor_cancels_later_sessions_here_tells_everyone_and_leaves_other_work_alone(): void
    {
        Notification::fake();
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $aina = $this->counselor('Aina', [$this->acme, $beta]);
        $membership = Membership::where('user_id', $aina->user_id)->where('org_id', $this->acme->id)->sole();

        $client = User::factory()->create(['name' => 'Siti Client']);
        $later = $this->book($this->slot($aina, $this->acme, '2026-11-05 10:00'), $client);
        $past = $this->book($this->slot($aina, $this->acme, '2026-10-28 10:00'), $client, BookingStatus::Completed);
        $elsewhere = $this->book($this->slot($aina, $beta, '2026-11-06 10:00'), User::factory()->create());
        $free = $this->slot($aina, $this->acme, '2026-11-07 10:00');
        $line = CounselorWeeklyHour::create(['counselor_user_id' => $aina->user_id, 'context_org_id' => $this->acme->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00', 'slot_minutes' => 60, 'session_mode' => 'online']);

        $this->hr()->post(route('org.counselors.remove', $membership->id))->assertSessionHas('status');

        $this->assertSame(MembershipStatus::Offboarded, $membership->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $later->fresh()->status);
        $this->assertSame('system', $later->fresh()->cancelled_by);
        $this->assertTrue((bool) CalendarEntry::find($later->calendar_entry_id)->is_available);
        $this->assertSame(BookingStatus::Completed, $past->fresh()->status);
        $this->assertSame(BookingStatus::Confirmed, $elsewhere->fresh()->status);
        $this->assertNull(CalendarEntry::find($free->id));
        $this->assertNull(CounselorWeeklyHour::find($line->id));
        $this->assertTrue(Membership::where('user_id', $aina->user_id)->where('org_id', $beta->id)->where('status', 'active')->exists());

        Notification::assertSentTo($client, ClientSessionNotice::class);
        Notification::assertSentTo($aina->user, CounselorRemovedFromOrganization::class);
        $this->assertSame(1, UserNotification::query()->ownedBy($client)->where('type', 'booking.cancelled')->count());
        $this->assertSame(1, $this->audit('counselor.removed_from_org')->count());
    }

    public function test_removing_works_once_and_only_inside_the_own_organization(): void
    {
        $aina = $this->counselor('Aina', [$this->acme]);
        $membership = Membership::where('user_id', $aina->user_id)->where('org_id', $this->acme->id)->sole();
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $bakar = $this->counselor('Bakar', [$beta]);
        $theirs = Membership::where('user_id', $bakar->user_id)->where('org_id', $beta->id)->sole();

        $this->hr()->post(route('org.counselors.remove', $theirs->id))->assertNotFound();
        $this->assertSame(MembershipStatus::Active, $theirs->fresh()->status);

        $this->hr()->post(route('org.counselors.remove', $membership->id))->assertSessionHas('status');
        $this->hr()->post(route('org.counselors.remove', $membership->id))->assertSessionHasErrors('counselor');
    }

    // ---- the two settings -----------------------------------------------------------------------------------------

    public function test_the_two_settings_are_saved_and_audited(): void
    {
        $this->hr()->put(route('org.counselors.settings'), ['access_model' => 'open'])->assertSessionHas('status');

        $this->acme->refresh();
        $this->assertSame(AccessModel::Open, $this->acme->access_model);
        $this->assertFalse($this->acme->allow_counselor_freelance);
        $this->assertSame(1, $this->audit('organization.counselor_settings_updated')->count());

        $this->hr()->put(route('org.counselors.settings'), ['access_model' => 'open']);
        $this->assertSame(1, $this->audit('organization.counselor_settings_updated')->count());

        $this->hr()->put(route('org.counselors.settings'), ['access_model' => 'nonsense'])->assertSessionHasErrors('access_model');
    }
}
