<?php

namespace Tests\Feature\Api;

use App\Models\CalendarEntry;
use App\Models\CounselorProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The random public id of a calendar entry: made at creation, never guessable, and the migration is safe on data that already exists. */
class CalendarEntryPublicIdTest extends TestCase
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
        return require database_path('migrations/2026_11_05_000001_add_public_id_to_calendar_entries.php');
    }

    public function test_every_new_entry_gets_a_random_lowercase_ulid(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $ids = collect(range(1, 40))->map(fn ($i) => $this->slot($aina, $this->open, '2026-11-03 10:00')->public_id);

        $this->assertCount(40, $ids->unique());
        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $id);
        }
    }

    public function test_it_cannot_be_set_by_mass_assignment(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);

        $entry = CalendarEntry::create([
            'counselor_user_id' => $aina->user_id, 'entry_type' => 'available_slot', 'context_org_id' => $this->open->id, 'entry_date' => '2026-11-03',
            'start_time' => '10:00:00', 'duration_minutes' => 60, 'public_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaa',
        ]);

        $this->assertNotSame('aaaaaaaaaaaaaaaaaaaaaaaaaa', $entry->fresh()->public_id);
        $this->assertNotContains('public_id', (new CalendarEntry)->getFillable());
    }

    public function test_the_column_is_required_and_unique(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $one = $this->slot($aina, $this->open, '2026-11-03 10:00');
        $two = $this->slot($aina, $this->open, '2026-11-03 12:00');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('calendar_entries')->where('id', $two->id)->update(['public_id' => $one->public_id]);
    }

    public function test_the_ids_exist_even_when_model_events_are_off_like_in_the_seeders(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);

        $entry = CalendarEntry::withoutEvents(fn () => $this->slot($aina, $this->open, '2026-11-03 10:00'));

        $this->assertNotNull($entry->fresh()->public_id);
    }

    public function test_the_migration_gives_existing_rows_an_id_and_can_be_run_again(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        foreach (range(1, 7) as $i) {
            $this->slot($aina, $this->open, '2026-11-03 1'.$i.':00');
        }

        // Back to how the table was before the migration, with rows in it.
        $migration = $this->migration();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('calendar_entries', 'public_id'));
        $this->assertSame(7, DB::table('calendar_entries')->count());

        $before = DB::table('bookings')->count();
        $events = [];
        CalendarEntry::updating(function () use (&$events) {
            $events[] = 'updating';
        });

        $migration->up();

        $this->assertSame([], $events, 'no model events');
        $this->assertSame(0, DB::table('calendar_entries')->whereNull('public_id')->count());
        $this->assertSame(7, DB::table('calendar_entries')->distinct()->count('public_id'));
        $this->assertSame($before, DB::table('bookings')->count());
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'like', '%calendar%')->count(), 'nothing is audited');

        // Again: nothing changes, nothing breaks.
        $ids = DB::table('calendar_entries')->orderBy('id')->pluck('public_id')->all();
        $migration->up();
        $this->assertSame($ids, DB::table('calendar_entries')->orderBy('id')->pluck('public_id')->all());

        $this->assertTrue(collect(Schema::getIndexes('calendar_entries'))->contains(fn ($i) => $i['unique'] && $i['columns'] === ['public_id']));
    }

    public function test_the_migration_finishes_a_half_done_run(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $kept = $this->slot($aina, $this->open, '2026-11-03 10:00');
        $missing = $this->slot($aina, $this->open, '2026-11-03 12:00');

        // An interrupted earlier run: some rows have an id, some do not (the column is still nullable).
        $migration = $this->migration();
        $migration->down();
        Schema::table('calendar_entries', fn ($table) => $table->char('public_id', 26)->nullable());
        DB::table('calendar_entries')->where('id', $kept->id)->update(['public_id' => '01kkkkkkkkkkkkkkkkkkkkkkkk']);

        $migration->up();

        $this->assertSame('01kkkkkkkkkkkkkkkkkkkkkkkk', DB::table('calendar_entries')->where('id', $kept->id)->value('public_id'), 'an id that exists is kept');
        $this->assertNotNull(DB::table('calendar_entries')->where('id', $missing->id)->value('public_id'));
    }
}
