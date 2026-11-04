<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The Schedule page: tools on one side, the calendar on the other, and the events the calendar asks for (docs/AVAILABILITY.md, "The Schedule
 * calendar"). Read only. The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class CounselorScheduleEventsTest extends TestCase
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

    private function ask(string $from = '2026-11-02', string $to = '2026-11-09', ?CounselorProfile $who = null)
    {
        return $this->as($who)->getJson(route('counselor.schedule.events', ['start' => $from.'T00:00:00+08:00', 'end' => $to.'T00:00:00+08:00']));
    }

    // ---- the page -----------------------------------------------------------------------------------------------------------------------

    public function test_the_page_has_the_tools_and_the_calendar_side_by_side_and_no_list_of_times(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $this->block($this->aina, '2026-11-06 09:00');

        $page = $this->as()->get(route('counselor.planner'))->assertOk();

        $page->assertSee('Open a time')->assertSee('Weekly hours')->assertSee('Block a time')->assertSee('id="schedule-calendar"', false)
            ->assertSee('data-timezone="Asia/Kuala_Lumpur"', false)
            ->assertDontSee('Your times')->assertDontSee('No times opened yet.')->assertDontSee('Private dentist visit');
        $this->assertStringContainsString('xl:grid-cols-[400px_minmax(0,1fr)]', $page->getContent());
    }

    public function test_every_time_is_picked_from_a_list_of_the_times_that_can_be_used(): void
    {
        $page = $this->as()->get(route('counselor.planner'))->assertOk()->getContent();

        $this->assertStringNotContainsString('type="time"', $page, 'no free typing: nothing can be "not a valid value"');
        foreach (['start', 'h-from', 'h-to', 'b-start', 'b-end'] as $id) {
            $this->assertStringContainsString('<select id="'.$id.'"', $page, $id);
        }

        // Every five minutes, shown as 08:05 AM and sent as 08:05; the day is 06:00-23:00 and a start is no later than 22:30 (the shortest time is 30 minutes).
        $this->assertStringContainsString('<option value="06:00">06:00 AM</option>', $page);
        $this->assertStringContainsString('<option value="10:25">10:25 AM</option>', $page);
        $this->assertStringContainsString('<option value="13:05">01:05 PM</option>', $page);
        $this->assertStringContainsString('<option value="23:00">11:00 PM</option>', $page, 'an end can be 23:00');
        $this->assertStringNotContainsString('value="10:28"', $page);
        $this->assertStringNotContainsString('value="05:55"', $page);
        $this->assertStringNotContainsString('value="23:05"', $page);

        $this->assertSame(1, preg_match('/<select id="start".*?<\/select>/s', $page, $start));
        $this->assertStringContainsString('value="22:30"', $start[0]);
        $this->assertStringNotContainsString('value="22:35"', $start[0]);
    }

    public function test_a_refused_time_comes_back_chosen_not_lost(): void
    {
        // 10:28 can not be sent from the list, but a hand-made request can: the page keeps what was sent as the chosen option.
        $page = $this->followingRedirects()->as()->from(route('counselor.planner'))->post(route('counselor.planner.store'), [
            'context' => $this->platform->id, 'date' => '2026-11-10', 'start' => '10:28', 'duration' => 60, 'mode' => 'online',
        ])->getContent();

        $this->assertStringContainsString('<option value="10:28" selected>10:28 AM</option>', $page);
    }

    public function test_the_hours_of_the_day_come_from_the_configuration_for_the_pickers_and_the_calendar_alike(): void
    {
        config(['counselors.schedule.day_from' => '08:00', 'counselors.schedule.day_to' => '20:00']);

        $page = $this->as()->get(route('counselor.planner'))->assertOk()->getContent();

        $this->assertStringContainsString('data-slot-min="08:00:00" data-slot-max="20:00:00"', $page);
        $this->assertStringNotContainsString('<option value="07:55">', $page);
        $this->assertStringContainsString('<option value="08:00">08:00 AM</option>', $page);
        $this->assertStringNotContainsString('<option value="20:05">', $page);
    }

    public function test_the_three_tools_share_one_name_so_only_one_is_open_at_a_time(): void
    {
        $page = $this->as()->get(route('counselor.planner'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($page, '<details name="schedule-tools"'));
        $this->assertSame(1, preg_match_all('/<details name="schedule-tools" class="[^"]*" open>/', $page), 'one is open to begin with: Open a time');
    }

    public function test_the_tool_whose_form_failed_is_the_one_left_open(): void
    {
        // A block in the past is refused and sent back to the Schedule page with what was typed: "Block a time" is the one still open.
        $page = $this->followingRedirects()->as()->from(route('counselor.planner'))->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-01', 'all_day' => 1])->getContent();

        $this->assertSame(1, preg_match_all('/<details name="schedule-tools" class="[^"]*" open>/', $page));
        $this->assertMatchesRegularExpression('/<details name="schedule-tools" class="[^"]*" open>\s*<summary[^>]*>Block a time/', $page);
    }

    public function test_the_calendar_can_open_on_a_week_and_the_old_calendar_address_leads_to_it(): void
    {
        $this->as()->get(route('counselor.planner', ['date' => '2026-11-11']))->assertOk()->assertSee('data-initial-date="2026-11-11"', false);
        $this->as()->get(route('counselor.planner', ['date' => 'nonsense']))->assertOk()->assertDontSee('data-initial-date', false);

        $this->as()->get('/counselor/calendar?week=2026-11-11')->assertRedirect(route('counselor.planner', ['date' => '2026-11-11']));
        $this->as()->get('/counselor/calendar?week=nonsense')->assertRedirect(route('counselor.planner'));
        $this->as()->get(route('counselor.week'))->assertRedirect(route('counselor.planner'));
    }

    public function test_the_menu_has_one_schedule_item_and_no_separate_calendar(): void
    {
        $labels = array_column(config('portal-nav.counselor'), 'label');

        $this->assertContains('Schedule', $labels);
        $this->assertNotContains('Calendar', $labels);
    }

    // ---- the events ---------------------------------------------------------------------------------------------------------------------

    public function test_every_kind_of_item_comes_with_its_time_in_malaysia_time_and_what_can_be_done_with_it(): void
    {
        $open = $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-06 14:00'), User::factory()->create(['name' => 'Siti Client']));
        $everBooked = $this->slot($this->aina, $this->platform, '2026-11-04 10:00');
        $this->book($everBooked, User::factory()->create(), BookingStatus::Cancelled, clearFlag: false);
        $block = $this->block($this->aina, '2026-11-07 09:00');

        $events = collect($this->ask()->assertOk()->json())->keyBy('id');

        // An open time: Malaysian time with its offset, and "Remove this time".
        $slot = $events['slot:'.$open->public_id];
        $this->assertSame(['Open', '2026-11-05T10:00:00+08:00', '2026-11-05T11:00:00+08:00', false, false], [$slot['title'], $slot['start'], $slot['end'], $slot['allDay'], $slot['editable']]);
        $this->assertSame([['label' => 'Remove this time', 'method' => 'DELETE', 'url' => route('counselor.planner.destroy', $open->public_id)]], $slot['extendedProps']['actions']);

        // A time that has had a booking stays: nothing to remove.
        $this->assertSame([], $events['slot:'.$everBooked->public_id]['extendedProps']['actions']);

        // A booked session: only "Booked session", a link to its page, never the client.
        $session = $events['session:'.$booking->calendarEntry->public_id];
        $this->assertSame('Booked session', $session['title']);
        $this->assertSame([['label' => 'Open this booking', 'method' => 'GET', 'url' => route('counselor.appointments.show', $booking->public_id)]], $session['extendedProps']['actions']);
        $this->assertStringNotContainsString('Siti', json_encode($events->all()));

        // A block: the counselor's own note as its title and "Remove this block".
        $item = $events['block:'.$block->public_id];
        $this->assertSame('Private dentist visit', $item['title']);
        $this->assertSame('Remove this block', $item['extendedProps']['actions'][0]['label']);
        $this->assertSame(route('counselor.planner.blocks.destroy', $block->public_id), $item['extendedProps']['actions'][0]['url']);
    }

    public function test_the_time_stays_malaysian_whatever_the_application_zone_is(): void
    {
        config(['app.timezone' => 'America/New_York']);
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');

        $this->assertSame('2026-11-05T10:00:00+08:00', $this->ask()->json('0.start'));
    }

    public function test_a_whole_day_block_is_an_all_day_item_and_a_block_without_a_note_says_blocked(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'all_day' => 1])->assertSessionHasNoErrors();

        $events = $this->ask('2026-11-09', '2026-11-16')->assertOk()->json();

        $this->assertCount(1, $events);
        $this->assertSame(['Blocked', true, '2026-11-10', '2026-11-11'], [$events[0]['title'], $events[0]['allDay'], $events[0]['start'], $events[0]['end']]);
    }

    public function test_the_weekly_hours_are_a_light_background_that_can_not_be_clicked(): void
    {
        CounselorWeeklyHour::create(['counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'weekday' => 4, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'slot_minutes' => 60, 'session_mode' => 'online']);

        $events = $this->ask('2026-11-02', '2026-11-16')->assertOk()->json();

        $this->assertCount(2, $events, 'Thursday 5 and Thursday 12 November');
        $this->assertSame(['background', ['sc-hours'], 'hours', '2026-11-05T09:00:00+08:00', '2026-11-05T12:00:00+08:00'], [$events[0]['display'], $events[0]['classNames'], $events[0]['extendedProps']['kind'], $events[0]['start'], $events[0]['end']]);
        $this->assertArrayNotHasKey('actions', $events[0]['extendedProps']);
    }

    public function test_a_time_hidden_from_clients_says_why_in_words(): void
    {
        $this->block($this->aina, '2026-11-05 11:00');
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');

        $slot = collect($this->ask()->json())->firstWhere('extendedProps.kind', 'open');

        $this->assertSame('Hidden from clients: blocked time', $slot['extendedProps']['hiddenLabel']);
        $this->assertSame(['sc-open', 'sc-hidden'], $slot['classNames']);
        $this->assertStringContainsString('Clients can not see it', $slot['extendedProps']['hiddenExplain']);
    }

    public function test_only_the_days_asked_for_and_only_my_own_entries_come_back(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $this->slot($this->aina, $this->platform, '2026-11-20 10:00');         // outside the stretch asked for
        $this->slot($bakar, $this->platform, '2026-11-05 15:00');
        $this->block($bakar, '2026-11-06 09:00');

        $events = $this->ask()->assertOk()->json();

        $this->assertCount(1, $events);
        $this->assertSame('2026-11-05T10:00:00+08:00', $events[0]['start']);
    }

    // ---- who may ask, and how much ------------------------------------------------------------------------------------------------------

    public function test_a_stretch_must_be_real_and_not_longer_than_45_days(): void
    {
        $this->as()->getJson(route('counselor.schedule.events'))->assertUnprocessable();
        $this->as()->getJson(route('counselor.schedule.events', ['start' => 'nonsense', 'end' => 'also nonsense']))->assertStatus(422);
        $this->ask('2026-11-09', '2026-11-02')->assertStatus(422);                       // the end before the start
        $this->ask('2026-11-02', '2026-11-02')->assertStatus(422);                       // an empty stretch
        $this->ask('2026-10-01', '2026-11-20')->assertStatus(422);                       // 50 days
        $this->ask('2026-10-05', '2026-11-19')->assertOk();                              // 45 days
    }

    public function test_it_is_never_cached_and_only_a_counselor_may_ask(): void
    {
        $this->ask()->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $client = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($client)->getJson(route('counselor.schedule.events', ['start' => '2026-11-02T00:00:00+08:00', 'end' => '2026-11-09T00:00:00+08:00']))->assertForbidden();

        auth()->logout();
        $this->getJson(route('counselor.schedule.events', ['start' => '2026-11-02T00:00:00+08:00', 'end' => '2026-11-09T00:00:00+08:00']))->assertUnauthorized();
    }

    public function test_a_suspended_counselor_still_sees_the_calendar_of_what_is_already_booked(): void
    {
        $this->slot($this->aina, $this->platform, '2026-11-05 10:00');
        $this->aina->forceFill(['verification_status' => \App\Enums\VerificationStatus::Suspended])->save();

        $this->as()->get(route('counselor.planner'))->assertOk();
        $this->assertCount(1, $this->ask()->assertOk()->json());
    }
}
