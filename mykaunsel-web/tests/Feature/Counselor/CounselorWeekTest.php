<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The weekly calendar (read only). Clock: Monday 2 November 2026, 09:00 Malaysian time, so this week is 2-8 November. */
class CounselorWeekTest extends TestCase
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

    public function test_this_week_is_shown_by_default_in_malaysian_time_with_today_marked(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-04 10:00');

        $page = $this->as()->get(route('counselor.week'))->assertOk();

        $page->assertSee('2 Nov – 8 Nov 2026')->assertSee('Monday')->assertSee('Sunday')->assertSee('10:00 AM – 11:00 AM')->assertSee('Malaysia time');
        $this->assertSame(1, substr_count($page->getContent(), '>Today<'), 'only Monday 2 November is marked as today');
        $page->assertDontSee('This week');   // already on it
    }

    public function test_the_day_changes_at_malaysian_midnight_not_the_servers(): void
    {
        // 23:30 on Sunday 8 November in Malaysia is 15:30 UTC: still this week, and Sunday is today.
        Carbon::setTestNow(Carbon::parse('2026-11-08 23:30:00', 'Asia/Kuala_Lumpur'));
        $this->as()->get(route('counselor.week'))->assertSee('2 Nov – 8 Nov 2026');

        // One hour later it is Monday 9 November in Malaysia (16:30 UTC, still Sunday on the server): next week.
        Carbon::setTestNow(Carbon::parse('2026-11-09 00:30:00', 'Asia/Kuala_Lumpur'));
        $this->as()->get(route('counselor.week'))->assertSee('9 Nov – 15 Nov 2026');
    }

    public function test_previous_and_next_weeks(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-11 10:00');

        $next = $this->as()->get(route('counselor.week', ['week' => '2026-11-11']))->assertOk();   // any day of the week works
        $next->assertSee('9 Nov – 15 Nov 2026')->assertSee('10:00 AM – 11:00 AM')->assertSee('This week')->assertSee('Previous week');
        $this->assertSame(0, substr_count($next->getContent(), '>Today<'));

        $this->as()->get(route('counselor.week', ['week' => '2026-10-26']))->assertOk()->assertSee('26 Oct – 1 Nov 2026')->assertDontSee('10:00 AM – 11:00 AM');
    }

    public function test_a_bad_or_out_of_reach_week_shows_this_week(): void
    {
        foreach (['nonsense', '2026-02-30', '2010-01-01', '2099-01-01', '2026-11-4'] as $bad) {
            $this->as()->get(route('counselor.week', ['week' => $bad]))->assertOk()->assertSee('2 Nov – 8 Nov 2026');
        }

        $this->as()->get('/counselor/calendar?week[]=x')->assertOk()->assertSee('2 Nov – 8 Nov 2026');
    }

    public function test_a_booked_session_links_to_its_page_and_never_shows_the_client(): void
    {
        $client = User::factory()->create(['name' => 'Zulkifli Clientname']);
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'), $client);

        $page = $this->as()->get(route('counselor.week'))->assertOk();

        $page->assertSee('Booked session')->assertSee(route('counselor.appointments.show', $booking->public_id), false)
            ->assertDontSee('Zulkifli')->assertDontSee($client->email);
    }

    public function test_open_times_blocks_and_hidden_reasons_are_shown(): void
    {
        $this->block($this->aina, '2026-11-06 11:00');                                         // titled "Private dentist visit" by the fixture
        $this->slot($this->aina, $this->platform, '2026-11-06 10:00');                         // runs into the block's buffer
        $this->slot($this->aina, $this->platform, '2026-11-07 10:00');                         // clear

        $page = $this->as()->get(route('counselor.week'))->assertOk();

        $page->assertSee('Blocked')->assertSee('Private dentist visit')->assertSee('Hidden from clients: blocked time');
        $this->assertSame(1, substr_count($page->getContent(), 'Hidden from clients'), 'the clear time carries no badge');
    }

    public function test_only_my_own_calendar_is_shown(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->slot($bakar, $this->platform, '2026-11-04 15:00');
        $this->block($bakar, '2026-11-05 09:00');

        $this->as()->get(route('counselor.week'))->assertOk()->assertDontSee('3:00 PM')->assertDontSee('Private dentist visit');
    }

    public function test_the_menu_has_a_calendar_item_and_a_client_cannot_open_it(): void
    {
        $this->as()->get(route('counselor.week'))->assertSee(route('counselor.week'), false);

        $client = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($client)->get(route('counselor.week'))->assertForbidden();
    }
}
