<?php

namespace Tests\Feature\Counselor;

use App\Enums\CalendarEntryType;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** A counselor blocks time (leave, holidays) on the Schedule page. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorBlocksTest extends TestCase
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

    private function blocks(?CounselorProfile $who = null)
    {
        return CalendarEntry::query()->where('counselor_user_id', ($who ?? $this->aina)->user_id)->where('entry_type', CalendarEntryType::PersonalBlock->value);
    }

    public function test_a_range_of_whole_days_makes_one_block_per_day(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'to' => '2026-11-12', 'all_day' => 1, 'title' => 'Annual leave'])
            ->assertSessionHas('status');

        $rows = $this->blocks()->orderBy('entry_date')->get();
        $this->assertCount(3, $rows);
        $this->assertSame(1440, (int) $rows[0]->duration_minutes);
        $this->assertSame('Annual leave', $rows[0]->title);
        $this->assertSame('2026-11-12', $rows[2]->entry_date->format('Y-m-d'));
    }

    public function test_part_of_a_day_needs_a_start_and_an_end_after_it(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'start' => '14:00', 'end' => '16:30'])->assertSessionHas('status');
        $this->assertSame(150, (int) $this->blocks()->sole()->duration_minutes);

        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-11', 'start' => '16:00', 'end' => '15:00'])->assertSessionHasErrors('block');
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-11'])->assertSessionHasErrors(['start', 'end']);
        $this->assertSame(1, $this->blocks()->count());
    }

    public function test_the_past_a_backwards_range_and_too_long_a_range_are_refused(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-01', 'all_day' => 1])->assertSessionHasErrors('block');
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-12', 'to' => '2026-11-10', 'all_day' => 1])->assertSessionHasErrors('block');
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-03', 'to' => '2027-02-01', 'all_day' => 1])->assertSessionHasErrors('block');

        $this->assertSame(0, $this->blocks()->count());
    }

    public function test_a_block_over_a_booked_session_is_refused_and_nothing_is_made(): void
    {
        $this->book($this->slot($this->aina, $this->platform, '2026-11-11 10:00'), User::factory()->create());

        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'to' => '2026-11-12', 'all_day' => 1])
            ->assertSessionHasErrors('block');

        $this->assertSame(0, $this->blocks()->count());
    }

    public function test_a_block_hides_a_free_time_until_it_is_removed(): void
    {
        $slot = $this->slot($this->aina, $this->platform, '2026-11-11 10:00');
        $availability = app(AvailabilityService::class);

        $this->assertTrue($availability->slotBookable($slot));

        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-11', 'all_day' => 1]);
        $this->assertFalse($availability->slotBookable($slot->fresh()));

        $this->as()->delete(route('counselor.planner.blocks.destroy', $this->blocks()->sole()->public_id))->assertSessionHas('status');
        $this->assertTrue($availability->slotBookable($slot->fresh()));
    }

    public function test_the_schedule_page_lists_my_blocks_only_and_someone_elses_is_a_404(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $this->as($bakar)->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'all_day' => 1, 'title' => 'Bakars secret']);
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-11', 'all_day' => 1, 'title' => 'My leave']);

        $this->as()->get(route('counselor.planner'))->assertOk()->assertSee('Block a time');

        // The blocks themselves come to the calendar as events: mine, with my own note, and never anyone else's.
        $events = $this->as()->getJson(route('counselor.schedule.events', ['start' => '2026-11-09T00:00:00+08:00', 'end' => '2026-11-16T00:00:00+08:00']))->assertOk()->json();
        $this->assertSame(['My leave'], array_column($events, 'title'));
        $this->assertStringNotContainsString('Bakars secret', json_encode($events));

        $theirs = $this->blocks($bakar)->sole();
        $this->as()->delete(route('counselor.planner.blocks.destroy', $theirs->public_id))->assertNotFound();
        $this->assertSame(1, $this->blocks($bakar)->count());
    }
}
