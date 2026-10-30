<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Models\Booking;
use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The real races of the confirm step: several PROCESSES book at the same moment, each through the whole application. This needs a database
 * that really runs transactions side by side (MySQL/MariaDB), so it is SKIPPED on SQLite, which is what the normal test run uses.
 *
 * It does NOT use RefreshDatabase (the child processes must see its rows, which are committed) and it uses the REAL clock (children cannot
 * share a test clock): its slots are a few days ahead. It creates its own rows with a unique marker and removes them afterwards. It runs
 * against the throw-away test database that the suite insists on (tests/TestCase.php, docs/MYSQL_TESTING.md).
 */
class BookingConcurrencyTest extends TestCase
{
    use AvailabilityFixtures;

    private array $orgIds = [];

    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Needs MySQL/MariaDB: SQLite cannot run two transactions at the same time. It runs by itself when the suite is run on MySQL (docs/MYSQL_TESTING.md).');
        }
    }

    protected function tearDown(): void
    {
        if ($this->userIds !== []) {
            $profileIds = CounselorProfile::query()->whereIn('user_id', $this->userIds)->pluck('id');

            DB::table('jobs')->where('payload', 'like', '%BookingCancelledCounselor%')->delete();
            DB::table('booking_idempotency_keys')->whereIn('user_id', $this->userIds)->delete();
            DB::table('bookings')->whereIn('user_id', $this->userIds)->orWhereIn('counselor_user_id', $this->userIds)->delete();
            DB::table('calendar_entries')->whereIn('counselor_user_id', $this->userIds)->delete();
            DB::table('counselor_languages')->whereIn('counselor_profile_id', $profileIds)->delete();
            DB::table('counselor_specializations')->whereIn('counselor_profile_id', $profileIds)->delete();
            DB::table('counselor_profiles')->whereIn('id', $profileIds)->delete();
            DB::table('memberships')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        if ($this->orgIds !== []) {
            Organization::query()->whereIn('id', $this->orgIds)->delete();
        }

        parent::tearDown();
    }

    private function world(): array
    {
        $marker = 'CONC-'.bin2hex(random_bytes(3));
        $orgA = $this->org("{$marker} A", AccessModel::Open);
        $orgB = $this->org("{$marker} B", AccessModel::Open);
        $this->orgIds = [$orgA->id, $orgB->id];

        $aina = $this->counselor("{$marker} Aina", [$orgA, $orgB]);
        $this->userIds[] = $aina->user_id;

        return [$marker, $orgA, $orgB, $aina];
    }

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->userIds[] = $user->id;

        return $user;
    }

    /** A Malaysian time a few days ahead, as the fixtures' "Y-m-d H:i" text. */
    private function when(int $days, string $time): string
    {
        return Carbon::now('Asia/Kuala_Lumpur')->addDays($days)->format('Y-m-d').' '.$time;
    }

    /**
     * Start one child per request at the same moment and collect what each printed.
     *
     * @param  list<array{user: int, slot: CalendarEntry, key?: string}>  $requests
     * @return list<string>
     */
    private function race(array $requests): array
    {
        $start = microtime(true) + 4.0;
        $children = [];

        foreach ($requests as $request) {
            $slot = $request['slot'];
            $at = Carbon::parse($slot->entry_date->format('Y-m-d').' '.$slot->start_time, 'Asia/Kuala_Lumpur');
            $cmd = [PHP_BINARY, base_path('tests/Support/book_child.php'), (string) $request['user'], $slot->public_id, $request['key'] ?? (string) Str::uuid(),
                $at->toIso8601String(), (string) $slot->duration_minutes, 'online', (string) $start];
            $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $children[] = [$process, $pipes];
        }

        $answers = [];

        foreach ($children as [$process, $pipes]) {
            $answers[] = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        return $answers;
    }

    public function test_two_people_booking_the_same_slot_at_once_exactly_one_succeeds(): void
    {
        [, $orgA, , $aina] = $this->world();
        $slot = $this->slot($aina, $orgA, $this->when(3, '10:00'));
        [$one, $two, $three] = [$this->person(), $this->person(), $this->person()];

        $answers = $this->race([['user' => $one->id, 'slot' => $slot], ['user' => $two->id, 'slot' => $slot], ['user' => $three->id, 'slot' => $slot]]);

        $this->assertSame(1, count(array_keys($answers, '201 -', true)), json_encode($answers));
        $this->assertSame(2, count(array_keys($answers, '409 slot_taken', true)), json_encode($answers));
        $this->assertSame(1, Booking::where('calendar_entry_id', $slot->id)->where('status', 'confirmed')->count());
        $this->assertFalse($slot->fresh()->is_available);
    }

    public function test_two_overlapping_slots_of_one_counselor_in_two_contexts_exactly_one_succeeds(): void
    {
        [, $orgA, $orgB, $aina] = $this->world();
        $inA = $this->slot($aina, $orgA, $this->when(3, '10:00'));
        $inB = $this->slot($aina, $orgB, $this->when(3, '10:30'));   // overlaps the first: the counselor cannot be in two places
        [$one, $two] = [$this->person(), $this->person()];

        $answers = $this->race([['user' => $one->id, 'slot' => $inA], ['user' => $two->id, 'slot' => $inB]]);

        sort($answers);
        $this->assertSame(['201 -', '409 slot_taken'], $answers, json_encode($answers));
        $this->assertSame(1, Booking::whereIn('calendar_entry_id', [$inA->id, $inB->id])->where('status', 'confirmed')->count());
    }

    public function test_one_person_firing_two_bookings_at_once_one_below_the_cap_exactly_one_succeeds(): void
    {
        [, $orgA, , $aina] = $this->world();
        $person = $this->person();

        // Two upcoming sessions already: the cap is three.
        $this->book($this->slot($aina, $orgA, $this->when(10, '09:00')), $person);
        $this->book($this->slot($aina, $orgA, $this->when(11, '09:00')), $person);
        $a = $this->slot($aina, $orgA, $this->when(3, '10:00'));
        $b = $this->slot($aina, $orgA, $this->when(4, '10:00'));

        $answers = $this->race([['user' => $person->id, 'slot' => $a], ['user' => $person->id, 'slot' => $b]]);

        sort($answers);
        $this->assertSame(['201 -', '409 upcoming_limit_reached'], $answers, json_encode($answers));
        $this->assertSame(3, Booking::where('user_id', $person->id)->where('status', 'confirmed')->count());
    }

    public function test_the_same_idempotency_key_sent_twice_at_once_makes_one_booking(): void
    {
        [, $orgA, , $aina] = $this->world();
        $slot = $this->slot($aina, $orgA, $this->when(3, '10:00'));
        $person = $this->person();
        $key = (string) Str::uuid();

        $answers = $this->race([['user' => $person->id, 'slot' => $slot, 'key' => $key], ['user' => $person->id, 'slot' => $slot, 'key' => $key]]);

        $this->assertSame(1, Booking::where('calendar_entry_id', $slot->id)->count(), 'one booking: '.json_encode($answers));
        $this->assertContains('201 -', $answers, json_encode($answers));

        foreach ($answers as $answer) {
            $this->assertContains($answer, ['201 -', '409 request_in_progress'], json_encode($answers));
        }
    }

    /**
     * Start commands (already built as argument lists, the last argument being the shared start time) at the same moment.
     *
     * @param  list<list<string>>  $commands
     * @return list<string>
     */
    private function raceCommands(array $commands): array
    {
        $start = microtime(true) + 4.0;
        $children = [];

        foreach ($commands as $cmd) {
            $cmd[] = (string) $start;
            // The children do not read phpunit.xml: ask for the database queue, so the e-mails they queue can be counted.
            $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), array_merge(getenv(), ['QUEUE_CONNECTION' => 'database']));
            $children[] = [$process, $pipes];
        }

        $answers = [];

        foreach ($children as [$process, $pipes]) {
            $answers[] = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }

        return $answers;
    }

    public function test_cancelling_the_same_booking_twice_at_once_changes_it_once_and_tells_the_counselor_once(): void
    {
        [, $orgA, , $aina] = $this->world();
        $slot = $this->slot($aina, $orgA, $this->when(5, '10:00'));
        $person = $this->person();
        $booking = $this->book($slot, $person);
        DB::table('jobs')->where('payload', 'like', '%BookingCancelledCounselor%')->delete();

        $cmd = [PHP_BINARY, base_path('tests/Support/cancel_child.php'), (string) $person->id, $booking->public_id];
        $answers = $this->raceCommands([$cmd, $cmd]);

        $this->assertSame(['200 -', '200 -'], $answers, json_encode($answers));
        $this->assertSame('cancelled', $booking->fresh()->status->value);
        $this->assertTrue($slot->fresh()->is_available);
        $this->assertSame(1, DB::table('jobs')->where('payload', 'like', '%BookingCancelledCounselor%')->count(), 'one e-mail, not two');
    }

    public function test_cancelling_while_someone_books_another_time_of_the_same_counselor_does_not_deadlock(): void
    {
        [, $orgA, , $aina] = $this->world();
        $held = $this->slot($aina, $orgA, $this->when(5, '10:00'));
        $other = $this->slot($aina, $orgA, $this->when(6, '10:00'));
        [$canceller, $booker] = [$this->person(), $this->person()];
        $booking = $this->book($held, $canceller);

        $at = Carbon::parse($other->entry_date->format('Y-m-d').' '.$other->start_time, 'Asia/Kuala_Lumpur');
        $answers = $this->raceCommands([
            [PHP_BINARY, base_path('tests/Support/cancel_child.php'), (string) $canceller->id, $booking->public_id],
            [PHP_BINARY, base_path('tests/Support/book_child.php'), (string) $booker->id, $other->public_id, (string) Str::uuid(), $at->toIso8601String(), '60', 'online'],
        ]);

        sort($answers);
        $this->assertSame(['200 -', '201 -'], $answers, json_encode($answers));
        $this->assertTrue($held->fresh()->is_available);
        $this->assertFalse($other->fresh()->is_available);
    }
}
