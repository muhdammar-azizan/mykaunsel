<?php

namespace Tests\Feature\Api;

use App\Enums\BookingStatus;
use App\Models\CalendarEntry;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The one function that decides whether a time fits on a counselor's calendar (also used later by the confirm step, slot creation and the
 * approval of a requested time): the buffer is added to BOTH ends of every other entry, whatever its context, and the answer comes from one query.
 */
class AvailabilityServiceTest extends TestCase
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

    private function service(): AvailabilityService
    {
        return app(AvailabilityService::class);
    }

    private function free(\App\Models\CounselorProfile $counselor, string $start, int $minutes = 60, ?int $except = null): bool
    {
        return $this->service()->isFree($counselor->user_id, Carbon::parse($start, 'Asia/Kuala_Lumpur'), $minutes, $except);
    }

    public function test_the_buffer_is_added_to_both_ends_of_a_booked_session(): void
    {
        $aina = $this->counselor('Aina', [$this->open], ['buffer' => 15]);
        $this->book($this->slot($aina, $this->open, '2026-11-03 10:00'));            // 10:00-11:00, with buffer 09:45-11:15

        // Before it: a 60 minute time must END by 09:45.
        $this->assertTrue($this->free($aina, '2026-11-03 08:45'), 'ends exactly at 09:45');
        $this->assertFalse($this->free($aina, '2026-11-03 08:46'), 'ends a minute inside');
        // After it: a time must START from 11:15.
        $this->assertTrue($this->free($aina, '2026-11-03 11:15'), 'starts exactly at 11:15');
        $this->assertFalse($this->free($aina, '2026-11-03 11:14'), 'starts a minute inside');
        // On top of it and around it.
        $this->assertFalse($this->free($aina, '2026-11-03 10:00'));
        $this->assertFalse($this->free($aina, '2026-11-03 10:20', 10), 'a short time inside it');
        $this->assertFalse($this->free($aina, '2026-11-03 09:00', 240), 'a long time around it');
        $this->assertTrue($this->free($aina, '2026-11-04 10:00'), 'another day');
    }

    public function test_a_personal_block_gets_the_buffer_too(): void
    {
        $aina = $this->counselor('Aina', [$this->open], ['buffer' => 15]);
        $this->block($aina, '2026-11-03 14:00', 30);                                  // 14:00-14:30, with buffer 13:45-14:45

        $this->assertTrue($this->free($aina, '2026-11-03 14:45'));
        $this->assertFalse($this->free($aina, '2026-11-03 14:44'));
        $this->assertTrue($this->free($aina, '2026-11-03 12:45'));
        $this->assertFalse($this->free($aina, '2026-11-03 12:46'));
    }

    public function test_the_context_does_not_matter_only_the_counselor_and_the_time(): void
    {
        $aina = $this->counselor('Aina', [$this->platform, $this->open]);
        $this->book($this->slot($aina, $this->platform, '2026-11-03 10:00'));
        $inClinic = $this->slot($aina, $this->open, '2026-11-03 10:30');

        $this->assertFalse($this->free($aina, '2026-11-03 10:30'));
        $this->assertCount(0, $this->service()->withoutConflicts(collect([$inClinic])));
    }

    public function test_a_booked_session_of_another_counselor_does_not_matter(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $other = $this->counselor('Other', [$this->open]);
        $this->book($this->slot($other, $this->open, '2026-11-03 10:00'));
        $this->block($other, '2026-11-03 10:00');

        $this->assertTrue($this->free($aina, '2026-11-03 10:00'));
    }

    public function test_what_counts_as_busy(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->slot($aina, $this->open, '2026-11-03 09:00');                                             // a free slot: not busy
        $this->book($this->slot($aina, $this->open, '2026-11-03 12:00'));                                 // booked: busy
        $this->slot($aina, $this->open, '2026-11-03 15:00', available: false);                           // flag off: busy (a layer)
        $rowOnly = $this->slot($aina, $this->open, '2026-11-03 18:00');
        $this->book($rowOnly, clearFlag: false);                                                          // booking row only: busy (a layer)
        $this->book($this->slot($aina, $this->open, '2026-11-03 21:00'), status: BookingStatus::Cancelled); // cancelled: free again

        $this->assertTrue($this->free($aina, '2026-11-03 09:00'), 'a free slot does not block its own hour');
        $this->assertFalse($this->free($aina, '2026-11-03 12:00'));
        $this->assertFalse($this->free($aina, '2026-11-03 15:00'));
        $this->assertFalse($this->free($aina, '2026-11-03 18:00'));
        $this->assertTrue($this->free($aina, '2026-11-03 21:00'));
        foreach ([BookingStatus::Completed, BookingStatus::NoShow, BookingStatus::Confirmed] as $status) {
            $slot = $this->slot($aina, $this->open, '2026-11-04 10:00');
            $this->book($slot, status: $status);
            $this->assertFalse($this->free($aina, '2026-11-04 10:00'), $status->value.' still holds the time');
            CalendarEntry::where('counselor_user_id', $aina->user_id)->whereDate('entry_date', '2026-11-04')->each(fn ($e) => $e->bookings()->delete());
            CalendarEntry::where('counselor_user_id', $aina->user_id)->whereDate('entry_date', '2026-11-04')->delete();
        }
    }

    public function test_the_slot_being_booked_can_be_left_out_of_the_check(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $slot = $this->slot($aina, $this->open, '2026-11-03 10:00');
        $this->book($slot);

        $this->assertFalse($this->free($aina, '2026-11-03 10:00'));
        $this->assertTrue($this->free($aina, '2026-11-03 10:00', 60, $slot->id), 'ignoring its own entry (moving or re-checking it)');
    }

    public function test_the_time_is_judged_in_malaysian_time_whatever_zone_it_arrives_in(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $this->book($this->slot($aina, $this->open, '2026-11-03 10:00'));             // 02:00 UTC

        $this->assertFalse($this->service()->isFree($aina->user_id, Carbon::parse('2026-11-03 02:00:00', 'UTC'), 60));
        $this->assertTrue($this->service()->isFree($aina->user_id, Carbon::parse('2026-11-03 10:00:00', 'UTC'), 60), '10:00 UTC is 18:00 in Malaysia');
    }

    public function test_the_whole_list_is_checked_with_a_fixed_number_of_queries(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $bina = $this->counselor('Bina', [$this->open]);
        $slots = collect();

        $queries = function () use (&$slots) {
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->service()->withoutConflicts($slots);

            return $n;
        };

        foreach ([$aina, $bina] as $c) {
            $this->book($this->slot($c, $this->open, '2026-11-03 10:00'));
            $slots->push($this->slot($c, $this->open, '2026-11-03 14:00'));
        }
        $queries();
        $few = $queries();

        foreach (range(4, 20) as $day) {
            $d = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            foreach ([$aina, $bina] as $c) {
                $this->block($c, "2026-11-{$d} 10:00");
                $slots->push($this->slot($c, $this->open, "2026-11-{$d} 10:30"));
                $slots->push($this->slot($c, $this->open, "2026-11-{$d} 14:00"));
            }
        }

        $this->assertSame($few, $queries(), 'two queries (entries and buffers) for 2 slots or for 70');
        $this->assertSame(2, $few);
        $this->assertCount(36, $this->service()->withoutConflicts($slots), 'every 10:30 slot is under its block: 2 counselors x 18 days of 14:00, plus 2 on day 3');
    }

    public function test_an_empty_list_costs_nothing(): void
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $this->assertCount(0, $this->service()->withoutConflicts(collect()));
        $this->assertSame(0, $n);
    }

    public function test_the_window_helpers(): void
    {
        $this->assertSame('2026-11-02T11:00:00+08:00', $this->service()->earliestStart()->toIso8601String());
        $this->assertSame('2026-12-02T00:00:00+08:00', $this->service()->lastDate()->toIso8601String());
        $this->assertSame('Asia/Kuala_Lumpur', $this->service()->timezone());
    }
}
