<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\BookingIdempotencyKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** bookings:purge-idempotency: expired keys go, live ones stay, --dry-run changes nothing, one system audit entry with counts only. */
class PurgeBookingIdempotencyKeysTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function key(User $user, string $expires): BookingIdempotencyKey
    {
        return BookingIdempotencyKey::create([
            'user_id' => $user->id, 'key' => (string) Str::uuid(), 'fingerprint' => str_repeat('a', 64), 'status' => 'completed',
            'response_status' => 201, 'response_body' => '{"booking":{"id":"x"}}', 'expires_at' => $expires,
        ]);
    }

    public function test_expired_keys_are_deleted_and_live_ones_stay(): void
    {
        Carbon::setTestNow('2026-11-02 09:00:00');
        $user = User::factory()->create();
        $this->key($user, '2026-11-01 09:00:00');
        $this->key($user, '2026-11-02 08:59:59');
        $live = $this->key($user, '2026-11-02 09:00:01');

        $this->artisan('bookings:purge-idempotency')->expectsOutputToContain('Deleted 2')->assertSuccessful();

        $this->assertSame([$live->id], BookingIdempotencyKey::pluck('id')->all());
    }

    public function test_dry_run_counts_and_changes_nothing(): void
    {
        Carbon::setTestNow('2026-11-02 09:00:00');
        $this->key(User::factory()->create(), '2026-11-01 09:00:00');

        $this->artisan('bookings:purge-idempotency --dry-run')->expectsOutputToContain('Would delete 1')->assertSuccessful();

        $this->assertSame(1, BookingIdempotencyKey::count());
        $this->assertSame(0, AuditLog::where('action', 'booking.idempotency_purged')->count());
    }

    public function test_it_writes_one_system_audit_entry_with_counts_only(): void
    {
        Carbon::setTestNow('2026-11-02 09:00:00');
        $user = User::factory()->create();
        $this->key($user, '2026-11-01 09:00:00');
        $this->key($user, '2026-11-01 10:00:00');

        $this->artisan('bookings:purge-idempotency')->assertSuccessful();

        $log = AuditLog::where('action', 'booking.idempotency_purged')->sole();
        $this->assertSame(['deleted' => 2, 'ttl_hours' => 24], $log->metadata);
        $this->assertNull($log->actor_user_id ?? null);
        $this->assertStringNotContainsString('booking', strtolower(json_encode($log->metadata)));
        $this->assertSame('system', config('audit.actions')['booking.idempotency_purged']['category']);
    }

    public function test_nothing_to_delete_writes_no_audit_entry(): void
    {
        $this->artisan('bookings:purge-idempotency')->expectsOutputToContain('Deleted 0')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('action', 'booking.idempotency_purged')->count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'bookings:purge-idempotency'));

        $this->assertCount(1, $events);
    }
}
