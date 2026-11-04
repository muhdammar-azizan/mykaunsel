<?php

namespace Tests\Feature\Counselor;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** What a counselor can open depends on where their application stands (App\Http\Middleware\EnsureCounselorStanding). Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorStandingTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A counselor who applied on the web (independent), in the given standing. */
    private function applicant(VerificationStatus $status, array $extra = [], string $name = 'Aminah'): CounselorProfile
    {
        $profile = $this->counselor($name, [$this->platform], ['status' => $status]);
        $profile->forceFill(['verification_type' => VerificationType::PlatformVerified] + $extra)->save();

        return $profile;
    }

    private function as(CounselorProfile $profile)
    {
        return $this->actingAs($profile->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private const PORTAL_PAGES = ['counselor.dashboard', 'counselor.planner', 'counselor.appointments', 'counselor.requests', 'counselor.notes', 'counselor.reviews', 'counselor.profile'];

    // ---- Pending and Rejected: the status page only --------------------------------------------------------

    public function test_a_pending_or_rejected_applicant_is_sent_to_the_application_status_from_every_portal_page(): void
    {
        foreach ([VerificationStatus::Pending, VerificationStatus::Rejected] as $status) {
            $profile = $this->applicant($status, [], "Applicant {$status->value}");

            foreach (self::PORTAL_PAGES as $page) {
                $this->as($profile)->get(route($page))->assertRedirect(route('counselors.signup.waiting-approval'));
            }

        }
    }

    public function test_signing_in_lands_a_pending_applicant_on_the_status_page(): void
    {
        $profile = $this->applicant(VerificationStatus::Pending);

        $this->as($profile)->followingRedirects()->get(route('dashboard'))->assertOk()->assertSee('Your application is under review');
    }

    public function test_a_rejected_applicant_sees_the_reason_and_the_way_to_send_the_documents_again(): void
    {
        $profile = $this->applicant(VerificationStatus::Rejected, ['rejection_reason' => 'The PA certificate is not readable.']);

        $this->as($profile)->get(route('counselors.signup.waiting-approval'))->assertOk()
            ->assertSee('Your application needs changes')->assertSee('The PA certificate is not readable.')->assertSee(route('counselors.signup.documents'), false)
            ->assertDontSee('Your profile is suspended');
        $this->as($profile)->get(route('counselors.signup.documents'))->assertOk();
    }

    // ---- Suspended: the portal stays open, with the reason ------------------------------------------------

    public function test_a_suspended_counselor_keeps_the_portal_and_is_told_why_with_a_link_to_the_details(): void
    {
        $profile = $this->applicant(VerificationStatus::Suspended, ['suspension_reason' => 'A complaint is being looked into.', 'suspension_source' => 'admin']);

        $this->as($profile)->get(route('counselor.dashboard'))->assertOk()
            ->assertSee('Your profile is suspended')->assertSee('See the details')->assertSee(route('counselors.signup.waiting-approval'), false);
        $this->as($profile)->get(route('counselor.appointments'))->assertOk();

        $this->as($profile)->get(route('counselors.signup.waiting-approval'))->assertOk()
            ->assertSee('Your profile is suspended')->assertSee('A complaint is being looked into.')->assertSee(route('counselor.dashboard'), false)
            ->assertDontSee('Your application needs changes');
    }

    public function test_a_counselor_suspended_for_an_expired_pa_is_told_to_renew_with_lkm(): void
    {
        $profile = $this->applicant(VerificationStatus::Suspended, ['suspension_reason' => 'Practising certificate expired.', 'suspension_source' => 'system']);

        $this->as($profile)->get(route('counselors.signup.waiting-approval'))->assertOk()->assertSee('Renew your practising certificate');
    }

    public function test_a_suspended_counselor_can_not_open_new_times_but_the_rest_of_the_planner_stays(): void
    {
        $profile = $this->applicant(VerificationStatus::Suspended);
        $slotsBefore = CalendarEntry::where('counselor_user_id', $profile->user_id)->count();

        $this->as($profile)->from(route('counselor.planner'))->post(route('counselor.planner.store'), ['date' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '11:00'])
            ->assertRedirect(route('counselor.planner'))->assertSessionHasErrors('planner');
        $this->as($profile)->from(route('counselor.planner'))->post(route('counselor.planner.hours.fill'), [])->assertSessionHasErrors('planner');
        $this->as($profile)->from(route('counselor.planner'))->post(route('counselor.planner.hours.store'), [])->assertSessionHasErrors('planner');

        $this->assertSame($slotsBefore, CalendarEntry::where('counselor_user_id', $profile->user_id)->count());
        $this->as($profile)->get(route('counselor.planner'))->assertOk();
    }

    // ---- everyone else is untouched -------------------------------------------------------------------------

    public function test_an_approved_applicant_has_the_whole_portal(): void
    {
        $profile = $this->applicant(VerificationStatus::Approved);

        foreach (self::PORTAL_PAGES as $page) {
            $this->as($profile)->get(route($page))->assertOk();
        }
    }

    public function test_a_counselor_an_organization_assigned_is_not_sent_to_an_application_page_they_never_had(): void
    {
        $profile = $this->counselor('Org One', [$this->platform], ['status' => VerificationStatus::Pending]);   // assigned by an organization, not an applicant

        $this->as($profile)->get(route('counselor.dashboard'))->assertOk()->assertSee('Your profile is pending')->assertDontSee('See the details');
    }

    public function test_the_application_pages_stay_open_to_the_applicant_and_closed_to_other_people(): void
    {
        $this->get(route('counselors.signup.waiting-approval'))->assertRedirect(route('login'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('counselors.signup.waiting-approval'))->assertNotFound();
    }
}
