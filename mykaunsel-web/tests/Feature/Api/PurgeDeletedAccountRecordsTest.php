<?php

namespace Tests\Feature\Api;

use App\Enums\BookingRequestStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** accounts:purge-deleted: 90 days after an account was deleted its bookings go, unless a session note or a complaint needs them. */
class PurgeDeletedAccountRecordsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private \App\Models\CounselorProfile $aina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->aina = $this->counselor('Aina Syazwani', [$this->closed]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function stub(int $daysAgo): User
    {
        return User::factory()->create(['name' => 'Deleted user', 'deleted_at' => now()->subDays($daysAgo)]);
    }

    private function bookingFor(User $user, string $when): Booking
    {
        return $this->book($this->slot($this->aina, $this->closed, $when), $user);
    }

    public function test_old_enough_bookings_and_requests_go_and_what_is_needed_stays(): void
    {
        $old = $this->stub(91);
        $recent = $this->stub(89);
        $live = User::factory()->create();

        $plain = $this->bookingFor($old, '2026-10-01 10:00');
        $withNote = $this->bookingFor($old, '2026-10-02 10:00');
        $complained = $this->bookingFor($old, '2026-10-03 10:00');
        $young = $this->bookingFor($recent, '2026-10-04 10:00');
        $someoneElses = $this->bookingFor($live, '2026-10-05 10:00');

        DB::table('session_notes')->insert(['booking_id' => $withNote->id, 'attendance' => 'attended', 'follow_up_status' => 'none', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('complaints')->insert(['reporter_user_id' => $live->id, 'counselor_user_id' => $this->aina->user_id, 'booking_id' => $complained->id, 'description' => 'x', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $request = BookingRequest::create([
            'user_id' => $old->id, 'counselor_user_id' => $plain->counselor_user_id, 'context_org_id' => $this->closed->id, 'requested_date' => '2026-10-10',
            'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online', 'status' => BookingRequestStatus::Approved,
            'expires_at' => now()->addDay(), 'booking_id' => $plain->id,
        ]);

        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertNull(Booking::find($plain->id));
        $this->assertNull(BookingRequest::find($request->id));
        foreach ([$withNote, $complained, $young, $someoneElses] as $kept) {
            $this->assertNotNull(Booking::find($kept->id));
        }

        $log = AuditLog::where('action', 'account.records_purged')->sole();
        $this->assertSame(1, $log->metadata['bookings']);
        $this->assertSame(1, $log->metadata['requests']);
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $old = $this->stub(120);
        $booking = $this->bookingFor($old, '2026-10-01 10:00');

        $this->artisan('accounts:purge-deleted --dry-run')->expectsOutputToContain('Would delete 1 bookings')->assertSuccessful();

        $this->assertNotNull(Booking::find($booking->id));
        $this->assertSame(0, AuditLog::where('action', 'account.records_purged')->count());
    }
}
