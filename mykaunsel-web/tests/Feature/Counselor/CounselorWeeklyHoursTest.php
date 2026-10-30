<?php

namespace Tests\Feature\Counselor;

use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\CounselorWeeklyHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * Weekly hours: a pattern the counselor states once, and "open the next weeks" that turns it into ordinary bookable times through the
 * planner's own rules. The clock stands at Monday 2 November 2026, 09:00 Malaysian time (see AvailabilityFixtures).
 */
class CounselorWeeklyHoursTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina', [$this->platform, $this->open]);
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

    private function line(array $over = [], ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.planner.hours.store'), $over + [
            'context' => $this->platform->id, 'weekday' => 1, 'from' => '10:00', 'to' => '13:00', 'duration' => 60, 'mode' => 'online',
        ]);
    }

    private function fill(int $weeks, ?CounselorProfile $who = null)
    {
        return $this->as($who)->post(route('counselor.planner.hours.fill'), ['weeks' => $weeks]);
    }

    /** @return list<string> */
    private function starts(): array
    {
        return CalendarEntry::query()->where('entry_type', 'available_slot')->orderBy('entry_date')->orderBy('start_time')->get()
            ->map(fn ($e) => $e->entry_date->format('Y-m-d').' '.substr((string) $e->start_time, 0, 5))->all();
    }

    // ---- the lines -----------------------------------------------------------------------------------------------------

    public function test_a_line_is_saved_and_shown_but_opens_nothing_by_itself(): void
    {
        $this->line()->assertSessionHasNoErrors()->assertSessionHas('status');

        $row = CounselorWeeklyHour::sole();
        $this->assertSame($this->aina->user_id, $row->counselor_user_id);
        $this->assertSame(1, $row->weekday);
        $this->assertSame(60, $row->slot_minutes);
        $this->assertSame(26, strlen($row->public_id));
        $this->assertSame(0, CalendarEntry::count(), 'a pattern is not bookable');

        $this->as()->get(route('counselor.planner'))->assertOk()->assertSee('Monday 10:00 – 13:00')->assertSee('Open the next weeks');
    }

    public function test_a_line_is_checked(): void
    {
        $this->line(['weekday' => 8])->assertSessionHasErrors('weekday');
        $this->line(['duration' => 61])->assertSessionHasErrors('duration');
        $this->line(['from' => '10:07'])->assertSessionHasErrors('planner');
        $this->line(['from' => '13:00', 'to' => '10:00'])->assertSessionHasErrors('planner');
        $this->line(['from' => '10:00', 'to' => '10:30', 'duration' => 60])->assertSessionHasErrors('planner');   // no room for one time
        $this->line(['context' => $this->closed->id])->assertSessionHasErrors('planner');
        $this->line(['mode' => 'physical'])->assertSessionHasErrors('planner');                                     // no place
        $this->assertSame(0, CounselorWeeklyHour::count());
    }

    public function test_in_person_needs_a_place_of_that_organization_and_online_keeps_none(): void
    {
        $place = $this->place($this->open, 'Kuantan', 'Pahang');

        $this->line(['context' => $this->open->id, 'mode' => 'physical', 'location' => $place->id])->assertSessionHasNoErrors();
        $this->line(['context' => $this->open->id, 'weekday' => 2, 'mode' => 'online', 'location' => $place->id])->assertSessionHasNoErrors();

        $this->assertSame($place->id, CounselorWeeklyHour::where('weekday', 1)->sole()->location_id);
        $this->assertNull(CounselorWeeklyHour::where('weekday', 2)->sole()->location_id);
    }

    public function test_lines_of_the_same_day_may_not_overlap_in_any_context(): void
    {
        $this->line()->assertSessionHasNoErrors();

        $this->line(['from' => '12:00', 'to' => '14:00'])->assertSessionHasErrors('planner');
        $this->line(['context' => $this->open->id, 'from' => '09:00', 'to' => '11:00'])->assertSessionHasErrors('planner');
        $this->line(['from' => '13:00', 'to' => '14:00'])->assertSessionHasNoErrors();              // touching is fine
        $this->line(['weekday' => 2])->assertSessionHasNoErrors();                                  // another day is fine

        $this->assertSame(3, CounselorWeeklyHour::count());
    }

    public function test_the_number_of_lines_is_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            CounselorWeeklyHour::create([
                'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'weekday' => ($i % 7) + 1,
                'start_time' => sprintf('%02d:00:00', 6 + intdiv($i, 7) * 2), 'end_time' => sprintf('%02d:30:00', 6 + intdiv($i, 7) * 2),
                'slot_minutes' => 30, 'session_mode' => 'online',
            ]);
        }

        $this->line(['weekday' => 6, 'from' => '20:00', 'to' => '21:00'])->assertSessionHasErrors('planner');
        $this->assertSame(30, CounselorWeeklyHour::count());
    }

    public function test_a_line_can_be_removed_and_the_times_it_opened_stay(): void
    {
        $this->line();
        $this->fill(1);
        $opened = CalendarEntry::count();

        $this->as()->delete(route('counselor.planner.hours.destroy', CounselorWeeklyHour::sole()->public_id))->assertSessionHasNoErrors();

        $this->assertSame(0, CounselorWeeklyHour::count());
        $this->assertSame($opened, CalendarEntry::count());
    }

    public function test_another_counselors_line_and_an_unknown_one_are_the_same_404(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->line([], $bakar);
        $theirs = CounselorWeeklyHour::sole();

        $this->as()->delete(route('counselor.planner.hours.destroy', $theirs->public_id))->assertNotFound();
        $this->as()->delete(route('counselor.planner.hours.destroy', '01hzzzzzzzzzzzzzzzzzzzzzzz'))->assertNotFound();
        $this->assertSame(1, CounselorWeeklyHour::count());
    }

    // ---- opening the weeks ---------------------------------------------------------------------------------------------

    public function test_filling_opens_one_time_per_slot_per_matching_day_and_never_in_the_past(): void
    {
        $this->line();                                           // Mondays 10:00-13:00, one-hour times

        $this->fill(2)->assertSessionHas('status');

        $this->assertSame([
            '2026-11-02 10:00', '2026-11-02 11:00', '2026-11-02 12:00',     // today: 10:00 is still ahead of 09:00
            '2026-11-09 10:00', '2026-11-09 11:00', '2026-11-09 12:00',
        ], $this->starts());
    }

    public function test_a_time_already_gone_by_today_is_not_opened_and_not_counted_as_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 11:30', 'Asia/Kuala_Lumpur'));
        $this->line();

        $this->fill(1)->assertSessionHas('status', fn ($status) => str_contains($status, '1 times opened, 0 skipped'));

        $this->assertSame(['2026-11-02 12:00'], $this->starts());
    }

    public function test_filling_again_opens_nothing_twice(): void
    {
        $this->line();
        $this->fill(2);
        $before = $this->starts();

        $this->fill(2)->assertSessionHas('status', fn ($status) => str_contains($status, '0 times opened, 6 skipped'));

        $this->assertSame($before, $this->starts());
    }

    public function test_what_does_not_fit_is_skipped_and_the_rest_is_opened(): void
    {
        $this->line();
        $this->book($this->slot($this->aina, $this->platform, '2026-11-09 11:00'));      // booked: its neighbours are inside the buffer
        $this->block($this->aina, '2026-11-02 12:00');

        $this->fill(2);

        $starts = $this->starts();
        $this->assertContains('2026-11-02 10:00', $starts);
        $this->assertNotContains('2026-11-02 11:00', $starts, 'inside the buffer of the personal block');
        $this->assertNotContains('2026-11-02 12:00', $starts, 'on a personal block');
        $this->assertNotContains('2026-11-09 10:00', $starts, 'inside the buffer of a booked session');
        $this->assertNotContains('2026-11-09 12:00', $starts, 'inside the buffer of a booked session');
    }

    public function test_every_time_filled_follows_the_planners_context_mode_and_place(): void
    {
        $place = $this->place($this->open, 'Kuantan', 'Pahang');
        $this->line(['context' => $this->open->id, 'mode' => 'physical', 'location' => $place->id, 'weekday' => 2, 'from' => '14:00', 'to' => '15:00']);

        $this->fill(1);

        $entry = CalendarEntry::sole();
        $this->assertSame($this->open->id, $entry->context_org_id);
        $this->assertSame($place->id, $entry->location_id);
        $this->assertSame('physical', $entry->session_mode->value);
        $this->assertTrue($entry->is_available);
        $this->assertSame('2026-11-03', $entry->entry_date->format('Y-m-d'));
    }

    public function test_a_line_whose_context_is_gone_is_skipped_not_fatal(): void
    {
        $this->line(['context' => $this->open->id]);
        \App\Models\Membership::where('user_id', $this->aina->user_id)->where('org_id', $this->open->id)->delete();

        $this->fill(1)->assertSessionHas('status', fn ($status) => str_contains($status, '0 times opened'));

        $this->assertSame(0, CalendarEntry::count());
    }

    public function test_the_number_of_weeks_is_limited_and_it_only_fills_the_counselors_own_calendar(): void
    {
        $this->line();
        $bakar = $this->counselor('Bakar', [$this->platform]);

        $this->fill(9)->assertSessionHasErrors('weeks');
        $this->fill(0)->assertSessionHasErrors('weeks');
        $this->fill(1, $bakar);                                    // Bakar has no lines

        $this->assertSame(0, CalendarEntry::count());
    }

    public function test_only_counselors_may_use_it(): void
    {
        $this->actingAs(User::factory()->create())->post(route('counselor.planner.hours.store'), [])->assertStatus(302);
        $this->post(route('counselor.planner.hours.fill'), ['weeks' => 1])->assertRedirect();
        $this->assertSame(0, CounselorWeeklyHour::count());
    }

    public function test_times_opened_this_way_are_bookable_in_the_clients_app(): void
    {
        $this->line(['weekday' => 4, 'from' => '10:00', 'to' => '11:00']);
        $this->fill(1);

        $this->client();
        $this->assertContains('2026-11-05 10:00', $this->startsOf($this->availability($this->aina), 'available'));
    }
}
