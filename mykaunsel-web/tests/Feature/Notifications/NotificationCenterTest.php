<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use App\Support\Inbox\NotificationRegistry;
use App\Support\Inbox\NotificationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/**
 * The notification center's service: who receives, de-duplication, coalescing of repeated duplicate-KB attempts, best-effort publishing,
 * and that every read or change is limited to the person's own notifications. (The bell, the panel and the full page come in later steps.)
 */
class NotificationCenterTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const NOW = '2026-10-20 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function center(): NotificationCenter
    {
        return app(NotificationCenter::class);
    }

    private function admin(string $name = 'Admin', MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    private function mine(User $user)
    {
        return UserNotification::query()->ownedBy($user);
    }

    // ---- who receives ----------------------------------------------------------------------------------------------------------------

    public function test_every_active_admin_gets_exactly_one_and_nobody_else_does(): void
    {
        $one = $this->admin('One');
        $two = $this->admin('Two');
        $suspended = $this->admin('Suspended', MembershipStatus::Suspended);
        $removed = $this->admin('Removed', MembershipStatus::Offboarded);
        $orgAdmin = User::factory()->create();
        $organization = $this->org('university', 'active');
        Membership::create(['user_id' => $orgAdmin->id, 'org_id' => $organization->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $student = User::factory()->create();
        // Odd data: the platform_admin role outside the platform organization does not make anyone a Platform Admin.
        $stray = User::factory()->create();
        Membership::create(['user_id' => $stray->id, 'org_id' => $organization->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $created = $this->center()->notifyAdmins('organization.application_submitted', $organization, $organization->name);

        $this->assertSame(2, $created);
        $this->assertSame(0, $this->mine($stray)->count());
        $this->assertSame(1, $this->mine($one)->count());
        $this->assertSame(1, $this->mine($two)->count());
        foreach ([$suspended, $removed, $orgAdmin, $student] as $nobody) {
            $this->assertSame(0, $this->mine($nobody)->count(), "{$nobody->name} must not receive anything");
        }
    }

    public function test_the_person_who_did_it_is_not_told(): void
    {
        $actor = $this->admin('Actor');
        $other = $this->admin('Other');
        $joined = $this->admin('Joined');

        $this->center()->notifyAdmins('admin.invitation_accepted', $joined, $joined->name, actor: $joined);

        $this->assertSame(0, $this->mine($joined)->count());
        $this->assertSame(1, $this->mine($actor)->count());
        $this->assertSame(1, $this->mine($other)->count());
    }

    public function test_a_row_holds_only_the_type_target_label_count_and_times(): void
    {
        $admin = $this->admin();
        $organization = $this->org('university', 'pending');

        $this->center()->notifyAdmins('organization.application_submitted', $organization, "  Universiti\n\tContoh   Sdn Bhd ");

        $row = $this->mine($admin)->sole();
        $this->assertSame('organization.application_submitted', $row->type);
        $this->assertSame('organization', $row->target_type);
        $this->assertSame($organization->id, $row->target_id);
        $this->assertSame('Universiti Contoh Sdn Bhd', $row->label, 'one line, spacing tidied');
        $this->assertSame(1, $row->count);
        $this->assertTrue($row->occurred_at->equalTo(now()));
        $this->assertNull($row->read_at);

        $stored = json_encode($row->getAttributes());
        $this->assertStringNotContainsString('http', $stored, 'no URL is ever stored');
        $this->assertStringNotContainsString('/platform', $stored);
    }

    public function test_a_long_label_is_cut(): void
    {
        $admin = $this->admin();
        $organization = $this->org('university', 'pending');

        $this->center()->notifyAdmins('organization.application_submitted', $organization, str_repeat('A', 400));

        $this->assertSame(150, mb_strlen($this->mine($admin)->sole()->label));
    }

    // ---- no duplicates -----------------------------------------------------------------------------------------------------------------

    public function test_the_same_occurrence_published_again_creates_nothing_new(): void
    {
        $admin = $this->admin();
        $profile = $this->counselor('independent', 'pending');

        $first = $this->center()->notifyAdmins('counselor.application_resubmitted', $profile, 'Name', '2');
        $retry = $this->center()->notifyAdmins('counselor.application_resubmitted', $profile, 'Name', '2');
        $next = $this->center()->notifyAdmins('counselor.application_resubmitted', $profile, 'Name', '3');

        $this->assertSame([1, 0, 1], [$first, $retry, $next]);
        $this->assertSame(2, $this->mine($admin)->count());
    }

    public function test_a_retry_after_a_new_admin_joined_tells_only_the_new_one(): void
    {
        $old = $this->admin('Old');
        $organization = $this->org('university', 'pending');

        $this->center()->notifyAdmins('organization.application_submitted', $organization, 'Org');
        $new = $this->admin('New');
        $this->center()->notifyAdmins('organization.application_submitted', $organization, 'Org');

        $this->assertSame(1, $this->mine($old)->count());
        $this->assertSame(1, $this->mine($new)->count());
    }

    public function test_the_same_occurrence_of_different_types_or_targets_are_different_notifications(): void
    {
        $admin = $this->admin();
        $a = $this->org('university', 'pending');
        $b = $this->org('corporate', 'pending');

        $this->center()->notifyAdmins('organization.application_submitted', $a, 'A');
        $this->center()->notifyAdmins('organization.application_submitted', $b, 'B');
        $this->center()->notifyAdmins('organization.application_resubmitted', $a, 'A');

        $this->assertSame(3, $this->mine($admin)->count());
    }

    // ---- the registry is the only way in -----------------------------------------------------------------------------------------------

    public function test_an_unknown_type_or_the_wrong_kind_of_target_is_refused(): void
    {
        $this->admin();
        $organization = $this->org('university', 'pending');

        foreach ([
            fn () => $this->center()->notifyAdmins('organization.typo', $organization, 'x'),
            fn () => $this->center()->notifyAdmins('organization.application_submitted', null, 'x'),
            fn () => $this->center()->notifyAdmins('system.maintenance_auto_reopened', $organization, 'x'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected the attempt to be refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, UserNotification::count());
    }

    public function test_a_type_without_a_target_works_with_none(): void
    {
        $admin = $this->admin();

        $this->center()->notifyAdmins('system.maintenance_auto_reopened', null, null, '2026-10-20 12:00');

        $row = $this->mine($admin)->sole();
        $this->assertNull($row->target_type);
        $this->assertNull($row->target_id);
        $this->assertNull($row->label);
    }

    public function test_a_later_page_can_register_its_own_type_without_touching_the_core(): void
    {
        $admin = $this->admin();
        $registry = app(NotificationRegistry::class);
        $registry->register(new NotificationType('moderation.post_flagged', 'system', 'A post was flagged', 'complaint', fn (?int $id) => "/moderation/{$id}"));

        $complaint = $this->complaintFixture();
        $this->center()->notifyAdmins('moderation.post_flagged', $complaint, 'Post #4');

        $row = $this->mine($admin)->sole();
        $this->assertSame('moderation.post_flagged', $row->type);
        $this->assertSame("/moderation/{$complaint->id}", $registry->get($row->type)->url($row->target_id));
    }

    // ---- best effort --------------------------------------------------------------------------------------------------------------------

    public function test_publish_never_throws_and_reports_the_error(): void
    {
        Exceptions::fake();
        $this->admin();

        $created = $this->center()->publish('organization.typo', null, 'x');

        $this->assertSame(0, $created);
        Exceptions::assertReported(InvalidArgumentException::class);
    }

    public function test_a_failure_to_save_never_undoes_the_action_that_caused_it(): void
    {
        Exceptions::fake();
        $this->admin();
        $organization = $this->org('university', 'pending');
        Schema::drop('user_notifications');

        // The producer's own work is done inside a transaction, and tells the admins right after it commits.
        $organization = DB::transaction(function () use ($organization) {
            $organization->forceFill(['name' => 'Saved Anyway'])->save();

            return $organization;
        });
        $created = $this->center()->publish('organization.application_submitted', $organization, $organization->name);

        $this->assertSame(0, $created);
        $this->assertSame('Saved Anyway', $organization->fresh()->name, 'the applicant\'s action stands');
        Exceptions::assertReported(\Illuminate\Database\QueryException::class);
    }

    // ---- coalescing: repeated attempts on one KB number ---------------------------------------------------------------------------------------

    public function test_repeated_attempts_inside_the_window_raise_the_count_and_make_it_unread_again(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Other');
        $profile = $this->counselor('independent', 'approved');

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');
        $this->assertSame(1, $this->mine($admin)->sole()->count);

        $this->center()->markRead($admin, $this->mine($admin)->sole()->id);
        Carbon::setTestNow(now()->addHours(2));
        $touched = $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');

        $this->assertSame(2, $touched, 'every active admin\'s notification is raised');
        foreach ([$admin, $other] as $who) {
            $row = $this->mine($who)->sole();
            $this->assertSame(2, $row->count);
            $this->assertTrue($row->occurred_at->equalTo(now()), 'newest time');
            $this->assertNull($row->read_at, 'unread again');
        }

        Carbon::setTestNow(now()->addHours(3));
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');

        $this->assertSame(3, $this->mine($admin)->sole()->count);
    }

    public function test_an_attempt_after_the_window_is_a_new_notification_and_the_old_count_stays(): void
    {
        $admin = $this->admin();
        $profile = $this->counselor('independent', 'approved');

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');
        Carbon::setTestNow(now()->addHour());
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');

        // 25 hours after the FIRST attempt (the window is counted from it, not from the latest attempt).
        Carbon::setTestNow(Carbon::parse(self::NOW)->addHours(25));
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');

        $rows = $this->mine($admin)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame([2, 1], $rows->pluck('count')->all(), 'only attempts inside a window raise its count');

        // …and the next attempt raises the NEW one, not the old.
        Carbon::setTestNow(now()->addHour());
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'Counselor Name');
        $this->assertSame([2, 2], $this->mine($admin)->orderBy('id')->pluck('count')->all());
    }

    public function test_the_window_edge_is_exact(): void
    {
        $admin = $this->admin();
        $profile = $this->counselor('independent', 'approved');

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');
        Carbon::setTestNow(Carbon::parse(self::NOW)->addHours(24));                 // exactly 24 hours later: still the same window
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');
        $this->assertSame([2], $this->mine($admin)->pluck('count')->all());

        Carbon::setTestNow(Carbon::parse(self::NOW)->addHours(24)->addSecond());   // one second past it
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');
        $this->assertSame([2, 1], $this->mine($admin)->orderBy('id')->pluck('count')->all());
    }

    public function test_two_different_kb_numbers_do_not_mix(): void
    {
        $admin = $this->admin();
        $a = $this->counselor('independent', 'approved');
        $b = $this->counselor('independent', 'approved');

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $a, 'A');
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $b, 'B');
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $a, 'A');

        $this->assertSame(['A' => 2, 'B' => 1], $this->mine($admin)->orderBy('id')->pluck('count', 'label')->all());
    }

    public function test_an_admin_who_joins_later_gets_a_fresh_notification_of_one(): void
    {
        $old = $this->admin('Old');
        $profile = $this->counselor('independent', 'approved');

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');
        $new = $this->admin('New');
        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');

        $this->assertSame(2, $this->mine($old)->sole()->count);
        $this->assertSame(1, $this->mine($new)->sole()->count);
    }

    public function test_a_type_that_does_not_coalesce_cannot_be_coalesced(): void
    {
        $this->admin();

        $this->expectException(InvalidArgumentException::class);
        $this->center()->notifyAdminsCoalescing('organization.application_submitted', $this->org('university', 'pending'), 'x');
    }

    public function test_coalescing_locks_the_target_row_so_two_attempts_at_once_cannot_both_create(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite has no row locks; this runs on MySQL/MariaDB.');
        }

        $this->admin();
        $profile = $this->counselor('independent', 'approved');
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $this->center()->notifyAdminsCoalescing('counselor.kb_duplicate_flagged', $profile, 'X');

        $this->assertNotEmpty(array_filter($sql, fn ($s) => str_contains($s, 'counselor_profiles') && str_contains($s, 'for update')), 'the profile row is locked first');
    }

    public function test_coalescing_publish_is_best_effort_too(): void
    {
        Exceptions::fake();
        $this->admin();

        $this->assertSame(0, $this->center()->publishCoalescing('organization.application_submitted', $this->org('university', 'pending'), 'x'));
        Exceptions::assertReported(InvalidArgumentException::class);
    }

    // ---- reading and changing: only your own ---------------------------------------------------------------------------------------------------

    public function test_the_count_the_list_and_the_order(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Other');
        $organizations = collect(range(1, 12))->map(fn ($i) => $this->org('university', 'pending'));

        foreach ($organizations as $i => $organization) {
            Carbon::setTestNow(Carbon::parse(self::NOW)->addMinutes($i));
            $this->center()->notifyAdmins('organization.application_submitted', $organization, "Org {$i}");
        }

        $this->assertSame(12, $this->center()->unreadCount($admin));
        $latest = $this->center()->latest($admin, 8);
        $this->assertCount(8, $latest);
        $this->assertSame(['Org 11', 'Org 10', 'Org 9', 'Org 8', 'Org 7', 'Org 6', 'Org 5', 'Org 4'], $latest->pluck('label')->all(), 'newest first');

        $this->center()->markRead($admin, $latest->first()->id);
        $this->assertSame(11, $this->center()->unreadCount($admin));
        $this->assertSame(12, $this->center()->unreadCount($other), 'the other admin\'s count is theirs');
    }

    public function test_nobody_can_find_or_change_another_admins_notification(): void
    {
        $mine = $this->admin('Mine');
        $theirs = $this->admin('Theirs');
        $this->center()->notifyAdmins('organization.application_submitted', $this->org('university', 'pending'), 'Org');
        $mineRow = $this->mine($mine)->sole();
        $theirRow = $this->mine($theirs)->sole();

        $this->assertNull($this->center()->find($mine, $theirRow->id));
        $this->assertFalse($this->center()->markRead($mine, $theirRow->id));
        $this->assertFalse($this->center()->markUnread($mine, $theirRow->id));
        $this->assertNull($theirRow->fresh()->read_at, 'still unread: untouched');

        $this->center()->markRead($theirs, $theirRow->id);
        $this->assertFalse($this->center()->markUnread($mine, $theirRow->id));
        $this->assertNotNull($theirRow->fresh()->read_at, 'still read: untouched');
        $this->assertNull($mineRow->fresh()->read_at);

        $this->assertNull($this->center()->find($mine, 999999));
        $this->assertFalse($this->center()->markRead($mine, 999999));
    }

    public function test_mark_read_unread_and_all(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Other');

        foreach (range(1, 3) as $i) {
            $this->center()->notifyAdmins('organization.application_submitted', $this->org('university', 'pending'), "Org {$i}");
        }

        $first = $this->center()->latest($admin, 1)->first();
        $this->assertTrue($this->center()->markRead($admin, $first->id));
        $this->assertTrue($this->center()->markRead($admin, $first->id), 'reading again is fine');
        $this->assertSame(2, $this->center()->unreadCount($admin));

        $this->assertTrue($this->center()->markUnread($admin, $first->id));
        $this->assertSame(3, $this->center()->unreadCount($admin));

        $this->assertSame(3, $this->center()->markAllRead($admin), 'it says how many it changed');
        $this->assertSame(0, $this->center()->markAllRead($admin), 'nothing left to change');
        $this->assertSame(0, $this->center()->unreadCount($admin));
        $this->assertSame(3, $this->center()->unreadCount($other), 'the other admin\'s are untouched');
    }

    public function test_deleting_an_admins_notifications_leaves_the_others(): void
    {
        $gone = $this->admin('Gone');
        $stays = $this->admin('Stays');
        $this->center()->notifyAdmins('organization.application_submitted', $this->org('university', 'pending'), 'Org');
        $this->center()->notifyAdmins('system.maintenance_auto_reopened', null, null, 'a');

        $this->assertSame(2, $this->center()->deleteAllFor($gone));

        $this->assertSame(0, $this->mine($gone)->count());
        $this->assertSame(2, $this->mine($stays)->count());
    }

    public function test_the_unread_count_and_the_list_use_the_indexes(): void
    {
        $admin = $this->admin();

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The query plan check uses SQLite\'s EXPLAIN QUERY PLAN.');
        }

        $plan = fn (string $sql, array $bindings) => collect(DB::select('explain query plan '.$sql, $bindings))->pluck('detail')->implode(' | ');

        $count = $plan('select count(*) from user_notifications where recipient_user_id = ? and read_at is null', [$admin->id]);
        $list = $plan('select * from user_notifications where recipient_user_id = ? order by occurred_at desc, id desc limit 8', [$admin->id]);

        $this->assertStringContainsString('ix_notifications_inbox', $count);
        $this->assertStringContainsString('USING INDEX', strtoupper($list));
    }

    // ---- privacy: nothing but the label can get in ------------------------------------------------------------------------------------------

    public function test_nothing_from_a_complaint_a_note_a_reason_or_a_counselor_record_reaches_the_table(): void
    {
        $admin = $this->admin();
        $profile = $this->counselor('independent', 'pending');
        $profile->reviewNotes()->create(['user_id' => $admin->id, 'body' => 'SECRET-NOTE']);
        $organization = $this->org('university', 'rejected');
        $organization->forceFill(['rejection_reason' => 'SECRET-REASON'])->saveQuietly();
        $complaint = $this->complaintFixture('SECRET-COMPLAINT-TEXT');

        $this->center()->notifyAdmins('counselor.application_submitted', $profile, $profile->user->name);
        $this->center()->notifyAdmins('organization.application_submitted', $organization, $organization->name);
        $this->center()->notifyAdmins('complaint.received', $complaint, '#'.$complaint->id);

        $dump = json_encode(DB::table('user_notifications')->get());

        foreach (['SECRET', $profile->kb_number, $profile->pa_number, $profile->user->email, $admin->email, 'example.test'] as $private) {
            $this->assertStringNotContainsString($private, $dump);
        }
        $this->assertStringContainsString('#'.$complaint->id, $dump);
    }

    private function complaintFixture(string $text = 'Ordinary words.')
    {
        return \App\Models\Complaint::create([
            'reporter_user_id' => User::factory()->create()->id,
            'counselor_user_id' => $this->counselor('independent', 'approved')->user_id,
            'description' => $text,
            'status' => \App\Enums\ComplaintStatus::Open,
        ]);
    }
}
