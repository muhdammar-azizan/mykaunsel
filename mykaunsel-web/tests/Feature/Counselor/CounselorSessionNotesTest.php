<?php

namespace Tests\Feature\Counselor;

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CounselorProfile;
use App\Models\SessionNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** A counselor's private session notes (docs/SESSION_NOTES.md). Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class CounselorSessionNotesTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private const SECRET = 'Client mentioned a very private worry about exams';

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

    private function mine(string $when, BookingStatus $status = BookingStatus::Completed, ?CounselorProfile $who = null): Booking
    {
        return $this->book($this->slot($who ?? $this->aina, $this->platform, $when), User::factory()->create(['name' => 'Siti Client']), $status);
    }

    private function save(Booking $booking, string $body = self::SECRET, ?CounselorProfile $who = null)
    {
        return $this->as($who)->put(route('counselor.notes.save', $booking->public_id), ['body' => $body]);
    }

    public function test_a_note_is_saved_encrypted_and_only_its_counselor_reads_it_back(): void
    {
        $booking = $this->mine('2026-10-30 10:00');

        $this->save($booking)->assertRedirect(route('counselor.notes.show', $booking->public_id))->assertSessionHas('status');

        $raw = DB::table('session_notes')->where('booking_id', $booking->id)->value('body');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('private worry', $raw);
        $this->assertSame(self::SECRET, SessionNote::sole()->body);

        $this->as()->get(route('counselor.notes.show', $booking->public_id))->assertOk()->assertSee(self::SECRET)->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_saving_again_replaces_the_one_note_and_deleting_erases_it(): void
    {
        $booking = $this->mine('2026-10-30 10:00');

        $this->save($booking);
        $this->save($booking, 'A second version');

        $this->assertSame(1, SessionNote::count());
        $this->assertSame('A second version', SessionNote::sole()->body);

        $this->as()->delete(route('counselor.notes.destroy', $booking->public_id))->assertRedirect(route('counselor.notes'));
        $this->assertSame(0, SessionNote::count());
    }

    public function test_only_a_started_session_that_is_not_cancelled_can_have_a_note(): void
    {
        $future = $this->mine('2026-11-05 10:00', BookingStatus::Confirmed);
        $cancelled = $this->mine('2026-10-29 10:00', BookingStatus::Cancelled);

        $this->save($future)->assertNotFound();
        $this->save($cancelled)->assertNotFound();
        $this->as()->get(route('counselor.notes.show', $future->public_id))->assertNotFound();
        $this->assertSame(0, SessionNote::count());
    }

    public function test_nobody_else_can_read_write_or_delete_a_note(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $booking = $this->mine('2026-10-30 10:00');
        $this->save($booking);

        $this->as($bakar)->get(route('counselor.notes.show', $booking->public_id))->assertNotFound();
        $this->save($booking, 'Overwritten', $bakar)->assertNotFound();
        $this->as($bakar)->delete(route('counselor.notes.destroy', $booking->public_id))->assertNotFound();
        $this->assertSame(self::SECRET, SessionNote::sole()->body);

        $this->as($bakar)->get(route('counselor.notes'))->assertOk()->assertDontSee('Siti Client');
    }

    public function test_the_list_says_which_sessions_have_a_note_without_showing_any_text(): void
    {
        $withNote = $this->mine('2026-10-30 10:00');
        $this->mine('2026-10-29 10:00', BookingStatus::NoShow);
        $this->mine('2026-11-05 10:00', BookingStatus::Confirmed);
        $this->save($withNote);

        $page = $this->as()->get(route('counselor.notes'))->assertOk();

        $page->assertSee('Open note')->assertSee('Write a note')->assertDontSee(self::SECRET)->assertDontSee('Thu, 5 Nov 2026');
    }

    public function test_the_audit_log_records_that_a_note_was_saved_but_never_the_text(): void
    {
        $booking = $this->mine('2026-10-30 10:00');

        $this->save($booking);
        $this->as()->delete(route('counselor.notes.destroy', $booking->public_id));

        $this->assertSame(1, AuditLog::where('action', 'session_note.saved')->count());
        $this->assertSame(1, AuditLog::where('action', 'session_note.deleted')->count());
        $this->assertStringNotContainsString('private worry', json_encode(AuditLog::all()->toArray()));
    }

    public function test_an_empty_or_too_long_note_is_refused(): void
    {
        $booking = $this->mine('2026-10-30 10:00');

        $this->save($booking, '')->assertSessionHasErrors('body');
        $this->save($booking, str_repeat('a', 5001))->assertSessionHasErrors('body');
        $this->assertSame(0, SessionNote::count());
    }

    public function test_old_notes_are_erased_and_recent_ones_stay(): void
    {
        $old = $this->mine('2018-10-01 10:00');
        $recent = $this->mine('2026-10-30 10:00');
        $this->save($old);
        $this->save($recent);

        $this->artisan('session-notes:purge', ['--dry-run' => true])->expectsOutputToContain('Would erase 1');
        $this->assertSame(2, SessionNote::count());

        $this->artisan('session-notes:purge')->expectsOutputToContain('Erased 1');
        $this->assertSame(1, SessionNote::count());
        $this->assertSame($recent->id, SessionNote::sole()->booking_id);
        $this->assertSame(1, AuditLog::where('action', 'session_note.purged')->count());
    }

    public function test_the_session_page_links_to_the_note_only_when_one_can_be_written(): void
    {
        $started = $this->mine('2026-10-30 10:00');
        $future = $this->mine('2026-11-05 10:00', BookingStatus::Confirmed);

        $this->as()->get(route('counselor.appointments.show', $started->public_id))->assertSee(route('counselor.notes.show', $started->public_id), false);
        $this->as()->get(route('counselor.appointments.show', $future->public_id))->assertDontSee('Open the note');
    }
}
