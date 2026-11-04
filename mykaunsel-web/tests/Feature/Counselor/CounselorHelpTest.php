<?php

namespace Tests\Feature\Counselor;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Models\CounselorProfile;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor's own Help page, and where Help is offered: the "?" in the header (the Help for the work done in this portal), the footer (the
 * general Help for everyone who uses MyKaunsel). The sidebar and the profile menu no longer carry a Help item.
 */
class CounselorHelpTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as(?CounselorProfile $profile = null)
    {
        return $this->actingAs(($profile ?? $this->aina)->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function orgAdmin()
    {
        $user = User::factory()->create(['name' => 'Hajar Admin', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->open->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $this->actingAs($user)->withSession(['current_org_id' => $this->open->id, 'current_role' => 'org_admin']);
    }

    public function test_a_counselor_opens_their_help_with_every_topic(): void
    {
        $page = $this->as()->get(route('counselor.help'))->assertOk();

        $page->assertSee('How this portal works, in plain words. All times are Malaysia time.')
            ->assertSeeInOrder(['Opening times', 'Weekly hours', 'Blocked times', 'The buffer and hidden times', 'Bookings, the meeting link and marking a session', 'Cancelling a session', 'Time requests', 'Organizations', 'Your practising certificate (PA)', 'What clients see about you', 'Notifications and e-mails', 'Your account', 'Session notes', 'If you need help']);
    }

    public function test_every_number_in_it_comes_from_the_configuration_and_the_services_that_enforce_it(): void
    {
        config([
            'booking.min_lead_hours' => 5, 'booking.horizon_days' => 21, 'booking.planner_horizon_days' => 99, 'booking.meeting_link_visible_minutes' => 22,
            'booking.cancellation_default_hours' => 36, 'booking.max_reschedules' => 4, 'booking.request_expiry_hours' => 72, 'booking.max_pending_requests' => 6,
            'counselors.pa_reminder_days' => [40, 5, 2], 'counselors.profile.buffer_minutes' => [0, 20, 40],
            'session-notes.retention_years' => 9, 'notifications.retention.read_days' => 12, 'notifications.retention.unread_days' => 34,
        ]);

        $this->as()->get(route('counselor.help'))->assertOk()
            ->assertSee('no sooner than 5 hours from now, and up to 21 days ahead. You can open times up to 99 days ahead.')
            ->assertSee('set on your Profile: 20, 40 minutes')
            ->assertSee('The client sees the link 22 minutes before the session starts.')
            ->assertSee('until 36 hours before it')->assertSee('move it up to 4 times')
            ->assertSee('A request waits up to 72 hours')->assertSee('up to 6 requests waiting at once')
            ->assertSee('in the portal 40, 5, 2 days before it ends')
            ->assertSee('after 12 days and unread ones after 34 days')
            ->assertSee('Notes are kept for 9 years');
    }

    public function test_the_page_can_not_keep_saying_a_number_the_system_no_longer_uses(): void
    {
        config(['booking.meeting_link_visible_minutes' => 22, 'booking.request_expiry_hours' => 72, 'session-notes.retention_years' => 9]);

        $this->as()->get(route('counselor.help'))
            ->assertDontSee('15 minutes before the session starts')
            ->assertDontSee('waits up to 48 hours')
            ->assertDontSee('kept for 7 years');
    }

    public function test_the_fixed_limits_are_the_ones_the_code_enforces(): void
    {
        $this->as()->get(route('counselor.help'))
            ->assertSee('for 1 to '.\App\Services\CounselorWeeklyHours::MAX_WEEKS.' weeks')
            ->assertSee('at most '.\App\Services\CounselorWeeklyHours::MAX_FILL.' times')
            ->assertSee('up to '.\App\Services\CounselorBlocks::MAX_DAYS.' days at once')
            ->assertSee('the last '.\App\Services\CounselorRequests::ANSWERED_DAYS.' days');
    }

    public function test_a_suspended_counselor_can_still_read_it_and_other_roles_and_visitors_can_not(): void
    {
        $this->aina->forceFill(['verification_status' => VerificationStatus::Suspended])->save();
        $this->as()->get(route('counselor.help'))->assertOk();

        $this->orgAdmin()->get(route('counselor.help'))->assertForbidden();

        auth()->logout();
        $this->flushSession();
        $this->get(route('counselor.help'))->assertRedirect(route('login'));
    }

    public function test_the_header_leads_a_counselor_to_their_help_and_the_footer_to_the_general_one(): void
    {
        $page = $this->as()->get(route('counselor.dashboard'))->assertOk();

        $page->assertSee('href="'.route('counselor.help').'" aria-label="Help"', false);

        // The counselor Help is offered once (the header); the general Help once (the footer). Not in the sidebar, not in the profile menu.
        $content = $page->getContent();
        $this->assertSame(1, substr_count($content, 'href="'.route('counselor.help').'"'));
        $this->assertSame(1, substr_count($content, 'href="'.route('help').'"'));
    }

    public function test_an_organization_admin_gets_the_general_help_in_the_header_and_the_footer(): void
    {
        $content = $this->orgAdmin()->get(route('org.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('counselor.help'), $content);
        $this->assertSame(2, substr_count($content, 'href="'.route('help').'"'), 'the header and the footer');
    }

    public function test_the_sidebar_no_longer_lists_help(): void
    {
        $labels = array_column(config('portal-nav.counselor'), 'label');

        $this->assertNotContains('Help', $labels);
        $this->assertContains('Organizations', $labels);
    }

    public function test_the_general_help_says_who_it_is_for_and_points_a_counselor_to_their_own(): void
    {
        $this->get(route('help'))->assertOk()
            ->assertSee('This is the general Help for everyone who uses MyKaunsel: clients, counselors and organizations.')
            ->assertDontSee('Help for counselors');

        $this->as()->get(route('help'))->assertOk()
            ->assertSee('Help for counselors')->assertSee(route('counselor.help'), false);
    }

    public function test_the_counselor_help_points_to_the_general_help_the_terms_the_privacy_and_the_helplines(): void
    {
        config(['api.support_email' => null, 'chat.helplines' => [['key' => 'x', 'name' => 'Test Line', 'number' => '12345']]]);

        $this->as()->get(route('counselor.help'))->assertOk()
            ->assertSee('General Help')->assertSee(route('help'), false)
            ->assertSee(route('terms'), false)->assertSee(route('privacy'), false)
            ->assertSee('Test Line')->assertSee('tel:12345', false)
            ->assertSee('To be confirmed: the support e-mail address');

        config(['api.support_email' => 'help@example.test']);
        $this->as()->get(route('counselor.help'))->assertSee('mailto:help@example.test', false)->assertDontSee('the support e-mail address');
    }
}
