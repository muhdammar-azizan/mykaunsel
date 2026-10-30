<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The random public id of a booking: made at creation, never guessable, and the migration is safe on data that already exists. */
class BookingPublicIdTest extends TestCase
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

    private function migration(): object
    {
        return require database_path('migrations/2026_11_06_000001_add_public_id_to_bookings.php');
    }

    private function bookings(int $count): void
    {
        $aina = $this->counselor('Aina', [$this->platform]);

        foreach (range(1, $count) as $i) {
            $this->book($this->slot($aina, $this->platform, '2026-11-03 '.(8 + $i).':00'));
        }
    }

    public function test_every_new_booking_gets_a_random_lowercase_ulid(): void
    {
        $this->bookings(8);

        $ids = Booking::pluck('public_id');
        $this->assertCount(8, $ids->unique());

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $id);
        }
    }

    public function test_it_cannot_be_set_by_mass_assignment_and_exists_even_with_events_off(): void
    {
        $this->assertNotContains('public_id', (new Booking)->getFillable());

        $this->bookings(1);
        $made = Booking::withoutEvents(fn () => Booking::create([
            'user_id' => Booking::first()->user_id, 'counselor_user_id' => Booking::first()->counselor_user_id, 'booking_mode' => 'slot_based',
            'status' => 'cancelled', 'session_mode' => 'online', 'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]));

        $this->assertNotSame('aaaaaaaaaaaaaaaaaaaaaaaaaa', $made->fresh()->public_id);
        $this->assertNotNull($made->fresh()->public_id);
    }

    public function test_the_column_is_unique(): void
    {
        $this->bookings(2);
        [$one, $two] = Booking::orderBy('id')->get();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('bookings')->where('id', $two->id)->update(['public_id' => $one->public_id]);
    }

    public function test_the_migration_gives_existing_rows_an_id_and_can_be_run_again(): void
    {
        $this->bookings(7);

        $migration = $this->migration();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('bookings', 'public_id'));
        $this->assertSame(7, DB::table('bookings')->count());

        $events = [];
        Booking::updating(function () use (&$events) {
            $events[] = 'updating';
        });

        $migration->up();

        $this->assertSame([], $events, 'no model events');
        $this->assertSame(0, DB::table('bookings')->whereNull('public_id')->count());
        $this->assertSame(7, DB::table('bookings')->distinct()->count('public_id'));
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'like', '%booking%')->count(), 'nothing is audited');

        $ids = DB::table('bookings')->orderBy('id')->pluck('public_id')->all();
        $migration->up();
        $this->assertSame($ids, DB::table('bookings')->orderBy('id')->pluck('public_id')->all(), 'running it again changes nothing');

        $this->assertTrue(collect(Schema::getIndexes('bookings'))->contains(fn ($i) => $i['unique'] && $i['columns'] === ['public_id']));
        Booking::flushEventListeners();
    }

    public function test_the_migration_finishes_a_half_done_run(): void
    {
        $this->bookings(2);
        [$kept, $missing] = Booking::orderBy('id')->get();

        $migration = $this->migration();
        $migration->down();
        Schema::table('bookings', fn ($table) => $table->char('public_id', 26)->nullable());
        DB::table('bookings')->where('id', $kept->id)->update(['public_id' => '01kkkkkkkkkkkkkkkkkkkkkkkk']);

        $migration->up();

        $this->assertSame('01kkkkkkkkkkkkkkkkkkkkkkkk', DB::table('bookings')->where('id', $kept->id)->value('public_id'), 'an id that exists is kept');
        $this->assertNotNull(DB::table('bookings')->where('id', $missing->id)->value('public_id'));
    }
}
