<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/**
 * Retention: read notifications go after 90 days, unread ones after 180 (configurable), by a scheduled command with a dry run, and only
 * the clean-up is audited (counts only).
 */
class NotificationRetentionTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const NOW = '2026-10-20 12:00:00';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->admin = User::factory()->create(['name' => 'Aminah']);
        Membership::create(['user_id' => $this->admin->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** A notification that happened this long ago (in seconds). */
    private function aged(int $seconds, bool $read, string $label = 'Some Name'): UserNotification
    {
        static $n = 0;

        return UserNotification::create([
            'recipient_user_id' => $this->admin->id, 'type' => 'system.maintenance_auto_reopened', 'label' => $label, 'occurrence_key' => 'r'.++$n,
            'occurred_at' => now()->subSeconds($seconds), 'read_at' => $read ? now()->subSeconds(max(1, $seconds - 5)) : null,
        ]);
    }

    private const DAY = 86400;

    public function test_the_dry_run_counts_and_changes_nothing(): void
    {
        $this->aged(91 * self::DAY, read: true);
        $this->aged(181 * self::DAY, read: false);
        $this->aged(10 * self::DAY, read: true);

        $this->artisan('notifications:purge --dry-run')->expectsOutput('Would delete 1 read notifications (older than 90 days) and 1 unread ones (older than 180 days).')->assertSuccessful();

        $this->assertSame(3, UserNotification::count());
        $this->assertSame(0, AuditLog::where('action', 'notification.purged')->count());
    }

    public function test_read_ones_go_after_90_days_and_unread_ones_after_180_to_the_second(): void
    {
        $readOld = $this->aged(90 * self::DAY + 1, read: true);
        $readEdge = $this->aged(90 * self::DAY, read: true);
        $readNew = $this->aged(89 * self::DAY, read: true);
        $unreadOld = $this->aged(180 * self::DAY + 1, read: false);
        $unreadEdge = $this->aged(180 * self::DAY, read: false);
        $unreadMiddle = $this->aged(100 * self::DAY, read: false);       // older than 90 days, but unread ones are kept for 180

        $this->artisan('notifications:purge')->expectsOutput('Deleted 1 read and 1 unread notifications.')->assertSuccessful();

        $this->assertNull($readOld->fresh());
        $this->assertNull($unreadOld->fresh());
        foreach ([$readEdge, $readNew, $unreadEdge, $unreadMiddle] as $kept) {
            $this->assertNotNull($kept->fresh());
        }
    }

    public function test_marking_an_old_one_unread_moves_it_to_the_longer_rule(): void
    {
        $old = $this->aged(100 * self::DAY, read: true);

        app(NotificationCenter::class)->markUnread($this->admin, $old->id);
        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertNotNull($old->fresh(), 'unread again: kept for 180 days');
    }

    public function test_the_periods_can_be_configured(): void
    {
        config(['notifications.retention.read_days' => 7, 'notifications.retention.unread_days' => 14]);
        $read = $this->aged(8 * self::DAY, read: true);
        $unread = $this->aged(15 * self::DAY, read: false);
        $kept = $this->aged(10 * self::DAY, read: false);

        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertNull($read->fresh());
        $this->assertNull($unread->fresh());
        $this->assertNotNull($kept->fresh());
    }

    public function test_a_big_backlog_is_deleted_in_batches(): void
    {
        $rows = [];
        foreach (range(1, 2100) as $i) {
            $rows[] = ['recipient_user_id' => $this->admin->id, 'type' => 'system.maintenance_auto_reopened', 'label' => null, 'occurrence_key' => "bulk{$i}", 'count' => 1,
                'occurred_at' => now()->subDays(200)->toDateTimeString(), 'read_at' => null, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('user_notifications')->insert($chunk);
        }
        $fresh = $this->aged(self::DAY, read: false);

        $this->assertSame(['read' => 0, 'unread' => 2100], app(NotificationCenter::class)->purge());

        $this->assertSame([$fresh->id], UserNotification::pluck('id')->all());
    }

    public function test_the_purge_is_audited_once_by_the_system_with_counts_only(): void
    {
        $this->aged(91 * self::DAY, read: true, label: 'Secret Org Name');
        $this->aged(92 * self::DAY, read: true, label: 'Another Secret Name');
        $this->aged(181 * self::DAY, read: false, label: 'Third Secret Name');

        $this->artisan('notifications:purge')->assertSuccessful();

        $log = AuditLog::where('action', 'notification.purged')->sole();
        $this->assertSame('system', $log->actor_type);
        $this->assertSame(['read' => 2, 'unread' => 1, 'read_days' => 90, 'unread_days' => 180], $log->metadata);
        $this->assertNull($log->target_id);
        $this->assertStringNotContainsString('Secret', json_encode($log->toArray()));
    }

    public function test_nothing_deleted_means_nothing_audited(): void
    {
        $this->aged(10 * self::DAY, read: true);

        $this->artisan('notifications:purge')->expectsOutput('Deleted 0 read and 0 unread notifications.')->assertSuccessful();

        $this->assertSame(0, AuditLog::where('action', 'notification.purged')->count());
    }

    public function test_only_the_purge_is_audited_never_creating_opening_or_reading(): void
    {
        $before = AuditLog::count();

        app(NotificationCenter::class)->publish('system.maintenance_auto_reopened', null, null, 'a');
        $row = UserNotification::firstOrFail();
        app(NotificationCenter::class)->markRead($this->admin, $row->id);
        app(NotificationCenter::class)->markUnread($this->admin, $row->id);
        app(NotificationCenter::class)->markAllRead($this->admin);
        app(NotificationCenter::class)->deleteAllFor($this->admin);

        $this->assertSame($before, AuditLog::count());
    }

    public function test_the_audit_log_knows_the_new_event(): void
    {
        $this->assertSame('system', config('audit.actions')['notification.purged']['category']);
        $this->assertSame('Old notifications deleted', config('audit.actions')['notification.purged']['label']);
    }

    public function test_it_is_scheduled_every_day(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'notifications:purge'));

        $this->assertNotNull($event, 'notifications:purge is scheduled');
        $this->assertSame('20 3 * * *', $event->expression, 'daily at 03:20');
        $this->assertTrue($event->withoutOverlapping);
    }
}
