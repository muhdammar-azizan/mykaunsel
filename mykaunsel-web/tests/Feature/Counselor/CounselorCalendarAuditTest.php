<?php

namespace Tests\Feature\Counselor;

use App\Models\AuditLog;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor's calendar is audited: one entry per action, one summary for "Open the next weeks", written in the same transaction,
 * with ids, dates, lengths and counts only (never a block's title, a client, or a list of time ids).
 * Clock: Monday 2 November 2026, 09:00 Malaysian time.
 */
class CounselorCalendarAuditTest extends TestCase
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

    private function as()
    {
        return $this->actingAs($this->aina->user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'counselor']);
    }

    private function logs(string $action)
    {
        return AuditLog::where('action', $action)->where('target_type', 'counselor')->where('target_id', $this->aina->id)->get();
    }

    private function open(array $over = [])
    {
        return $this->as()->post(route('counselor.planner.store'), $over + [
            'context' => $this->platform->id, 'date' => '2026-11-10', 'start' => '10:00', 'duration' => 60, 'mode' => 'online',
        ]);
    }

    public function test_opening_and_removing_a_time_is_audited_once_each(): void
    {
        $this->open()->assertSessionHasNoErrors();

        $opened = $this->logs('calendar.time_opened')->sole();
        $this->assertSame('2026-11-10', $opened->metadata['date']);
        $this->assertSame('10:00', $opened->metadata['start']);
        $this->assertSame(60, $opened->metadata['minutes']);
        $this->assertSame($this->aina->user_id, $opened->actor_user_id);

        $this->as()->delete(route('counselor.planner.destroy', CalendarEntry::sole()->public_id))->assertSessionHasNoErrors();

        $removed = $this->logs('calendar.time_removed')->sole();
        $this->assertSame('2026-11-10', $removed->metadata['date']);
    }

    public function test_a_refused_time_leaves_no_audit_entry(): void
    {
        $this->open(['date' => '2026-11-01'])->assertSessionHasErrors('planner');

        $this->assertSame(0, AuditLog::where('action', 'like', 'calendar.%')->count());
    }

    public function test_a_block_is_one_entry_for_the_whole_range_and_never_records_its_title(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-10', 'to' => '2026-11-12', 'all_day' => 1, 'title' => 'Secret clinic visit'])
            ->assertSessionHasNoErrors();

        $entry = $this->logs('calendar.block_added')->sole();
        $this->assertSame(3, $entry->metadata['days']);
        $this->assertSame('2026-11-10', $entry->metadata['from']);
        $this->assertSame('2026-11-12', $entry->metadata['to']);
        $this->assertTrue($entry->metadata['all_day']);
        $this->assertStringNotContainsString('Secret clinic visit', json_encode($entry->getAttributes()));

        $this->as()->delete(route('counselor.planner.blocks.destroy', CalendarEntry::query()->orderBy('entry_date')->first()->public_id))->assertSessionHasNoErrors();

        $this->assertSame('2026-11-10', $this->logs('calendar.block_removed')->sole()->metadata['date']);
    }

    public function test_a_refused_block_leaves_no_audit_entry(): void
    {
        $this->as()->post(route('counselor.planner.blocks.store'), ['from' => '2026-11-01', 'all_day' => 1, 'title' => 'Past'])->assertSessionHasErrors('block');

        $this->assertSame(0, AuditLog::where('action', 'like', 'calendar.%')->count());
    }

    public function test_weekly_hours_lines_and_one_fill_summary(): void
    {
        $this->as()->post(route('counselor.planner.hours.store'), [
            'context' => $this->platform->id, 'weekday' => 1, 'from' => '10:00', 'to' => '13:00', 'duration' => 60, 'mode' => 'online',
        ])->assertSessionHasNoErrors();

        $line = $this->logs('calendar.hours_line_added')->sole();
        $this->assertSame('10:00', $line->metadata['from']);
        $this->assertSame('13:00', $line->metadata['to']);

        $this->as()->post(route('counselor.planner.hours.fill'), ['weeks' => 2])->assertSessionHasNoErrors();

        // 3 times a week for 2 weeks, but ONE entry; no entry per time.
        $this->assertSame(0, $this->logs('calendar.time_opened')->count());
        $summary = $this->logs('calendar.weeks_opened')->sole();
        $this->assertSame(2, $summary->metadata['weeks']);
        $this->assertSame(CalendarEntry::count(), $summary->metadata['created']);
        $this->assertSame('2026-11-02', $summary->metadata['from']);
        $this->assertSame('2026-11-15', $summary->metadata['to']);
        $this->assertSame([$this->platform->id], $summary->metadata['context_org_ids']);
        $this->assertArrayNotHasKey('ids', $summary->metadata);

        $this->as()->delete(route('counselor.planner.hours.destroy', \App\Models\CounselorWeeklyHour::sole()->public_id))->assertSessionHasNoErrors();
        $this->assertSame(1, $this->logs('calendar.hours_line_removed')->count());
    }

    public function test_a_fill_refused_for_being_too_big_writes_nothing_at_all(): void
    {
        \App\Models\CounselorWeeklyHour::create([
            'counselor_user_id' => $this->aina->user_id, 'context_org_id' => $this->platform->id, 'weekday' => 1,
            'start_time' => '06:00:00', 'end_time' => '23:00:00', 'slot_minutes' => 15, 'session_mode' => 'online',
        ]);

        $this->as()->post(route('counselor.planner.hours.fill'), ['weeks' => 8])->assertSessionHasErrors('planner');

        $this->assertSame(0, CalendarEntry::count());
        $this->assertSame(0, AuditLog::where('action', 'like', 'calendar.%')->count());
    }
}
