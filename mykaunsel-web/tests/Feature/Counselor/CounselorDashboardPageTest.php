<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The counselor portal Dashboard. The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures). */
class CounselorDashboardPageTest extends TestCase
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

    private function mine(string $when, BookingStatus $status = BookingStatus::Confirmed, ?CounselorProfile $who = null, string $client = 'Siti Client')
    {
        return $this->book($this->slot($who ?? $this->aina, $this->platform, $when), User::factory()->create(['name' => $client]), $status);
    }

    public function test_an_empty_dashboard_says_so_without_inventing_anything(): void
    {
        $this->as()->get(route('counselor.dashboard'))->assertOk()
            ->assertSee('Welcome, Aina')->assertSee('No sessions today.')->assertSee('No requests waiting.')->assertSee('You have no free time open for booking.');
    }

    public function test_today_next_and_the_numbers_come_from_the_counselors_own_sessions(): void
    {
        $this->mine('2026-11-02 15:00', client: 'Todays Client');
        $this->mine('2026-11-05 10:00', client: 'Later Client');
        $this->mine('2026-11-02 07:00', client: 'Ended Client');                        // ended, still to be marked
        $this->mine('2026-11-05 11:00', who: $this->counselor('Bakar', [$this->platform]), client: 'Someone Else');

        $page = $this->as()->get(route('counselor.dashboard'))->assertOk();

        $page->assertSee('Todays Client')->assertDontSee('Later Client')->assertDontSee('Someone Else');
        $page->assertSee('Sessions today')->assertSee('Waiting to be marked');
    }

    public function test_a_session_that_ended_today_is_still_in_todays_list_in_time_order_and_says_it_needs_marking(): void
    {
        $this->mine('2026-11-02 15:00', client: 'Afternoon Client');
        $this->mine('2026-11-02 07:00', client: 'Morning Client');                       // ended, still to be marked
        $this->mine('2026-11-02 08:00', client: 'Completed Client', status: BookingStatus::Completed);   // already marked: not in the list

        $page = $this->as()->get(route('counselor.dashboard'))->assertOk();

        $page->assertSeeInOrder(['Morning Client', 'Ended: needs marking', 'Afternoon Client']);
        $page->assertDontSee('Completed Client');
        $this->assertSame(1, substr_count($page->getContent(), 'Ended: needs marking'));

        // The number on the card is the same two sessions the Bookings page shows under Needs marking and Upcoming for today.
        $page->assertSeeInOrder(['Sessions today', '2']);
    }

    public function test_the_dashboard_links_to_the_schedule_the_bookings_the_requests_and_the_organizations(): void
    {
        $page = $this->as()->get(route('counselor.dashboard'))->assertOk()->assertSee('Quick links');

        foreach (['counselor.planner', 'counselor.appointments', 'counselor.requests', 'counselor.organizations'] as $route) {
            $page->assertSee(route($route), false);
        }
    }

    public function test_the_next_session_is_shown_when_nothing_is_today(): void
    {
        $this->mine('2026-11-05 10:00', client: 'Later Client');

        $this->as()->get(route('counselor.dashboard'))->assertOk()->assertSee('No sessions today.')->assertSee('Next session:')->assertSee('Later Client')->assertSee('Thu, 5 Nov 2026');
    }

    public function test_a_suspended_profile_and_an_expired_pa_are_noticed(): void
    {
        $this->aina->forceFill(['verification_status' => 'suspended', 'pa_valid_until' => '2026-10-01'])->save();

        $this->as()->get(route('counselor.dashboard'))->assertOk()
            ->assertSee('Your profile is suspended')->assertSee('has expired');
    }

    public function test_someone_who_is_not_a_member_is_turned_away(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'employee'])
            ->get(route('counselor.dashboard'))->assertRedirect();
    }
}
