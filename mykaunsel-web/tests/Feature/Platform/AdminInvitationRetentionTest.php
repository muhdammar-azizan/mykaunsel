<?php

namespace Tests\Feature\Platform;

use App\Enums\AdminInvitationStatus;
use App\Models\AdminInvitation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Counselor\CounselorTestCase;

/**
 * Old Platform Admin invitations lose their name and email 30 days after they were accepted, revoked or ran out (admin-invitations:purge).
 */
class AdminInvitationRetentionTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSamples();
    }

    private function row(string $email, array $over = []): AdminInvitation
    {
        $status = $over['status'] ?? 'pending';
        unset($over['status']);

        return AdminInvitation::create($over + [
            'name' => 'Person '.$email, 'email' => $email, 'token_hash' => hash('sha256', $email), 'active_email' => $status === 'pending' ? $email : null,
            'status' => $status, 'invited_by_user_id' => $this->admin->id, 'expires_at' => now()->addDay(), 'last_sent_at' => now()->subDay(), 'sent_count' => 1,
        ]);
    }

    private function erased(AdminInvitation $i): bool
    {
        return $i->fresh()->personal_data_erased_at !== null && $i->fresh()->name === null && $i->fresh()->email === null;
    }

    public function test_an_accepted_invitation_is_erased_after_30_days_and_not_before(): void
    {
        $user = User::factory()->create();
        $young = $this->row('young@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(29)->subHours(23), 'accepted_user_id' => $user->id]);
        $old = $this->row('old@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(30)->subMinute(), 'accepted_user_id' => $user->id]);

        $this->artisan('admin-invitations:purge')->expectsOutputToContain('Erased the name and email of 1 invitations')->assertSuccessful();

        $this->assertFalse($this->erased($young));
        $this->assertTrue($this->erased($old));
        $this->assertSame('young@example.com', $young->fresh()->email);
    }

    public function test_the_empty_record_keeps_everything_that_is_not_personal(): void
    {
        $user = User::factory()->create();
        $old = $this->row('old@example.com', [
            'status' => 'accepted', 'accepted_at' => now()->subDays(40), 'accepted_user_id' => $user->id, 'sent_count' => 3, 'expires_at' => now()->subDays(41),
        ]);
        $before = $old->fresh()->only(['id', 'status', 'token_hash', 'invited_by_user_id', 'accepted_user_id', 'accepted_at', 'expires_at', 'last_sent_at', 'sent_count']);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $after = $old->fresh();
        $this->assertEquals($before, $after->only(array_keys($before)));
        $this->assertSame([null, null, null], [$after->name, $after->email, $after->active_email]);
        $this->assertNotNull($after->personal_data_erased_at);
        $this->assertSame(AdminInvitationStatus::Accepted, $after->status);
        $this->assertSame($user->id, $after->accepted_user_id, 'who accepted stays, as a user id');
        $this->assertSame($this->admin->id, $after->invited_by_user_id, 'who invited stays');
    }

    public function test_a_revoked_invitation_is_counted_from_the_day_it_was_revoked(): void
    {
        $young = $this->row('young@example.com', ['status' => 'revoked', 'revoked_at' => now()->subDays(10), 'expires_at' => now()->subDays(60)]);
        $old = $this->row('old@example.com', ['status' => 'revoked', 'revoked_at' => now()->subDays(31), 'expires_at' => now()->addDays(5)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertFalse($this->erased($young), 'revoked 10 days ago, even though its own time ran out long ago');
        $this->assertTrue($this->erased($old));
    }

    public function test_an_expired_invitation_is_counted_from_the_day_it_ran_out(): void
    {
        $recent = $this->row('recent@example.com', ['status' => 'pending', 'expires_at' => now()->subDays(10)]);
        $old = $this->row('old@example.com', ['status' => 'pending', 'expires_at' => now()->subDays(31)]);

        $this->assertSame(AdminInvitationStatus::Expired, $old->displayStatus());
        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertFalse($this->erased($recent));
        $this->assertTrue($this->erased($old));
        $this->assertSame('pending', $old->fresh()->status->value, 'the stored status is not rewritten');
        $this->assertNull($old->fresh()->active_email);
    }

    public function test_an_invitation_replaced_by_a_newer_one_is_counted_from_the_day_it_was_replaced(): void
    {
        $replacedLongAgo = $this->row('a@example.com', ['status' => 'expired', 'superseded_at' => now()->subDays(45), 'expires_at' => now()->subDays(50)]);
        $replacedRecently = $this->row('b@example.com', ['status' => 'expired', 'superseded_at' => now()->subDays(2), 'expires_at' => now()->subDays(90)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertTrue($this->erased($replacedLongAgo));
        $this->assertFalse($this->erased($replacedRecently));
    }

    public function test_a_pending_invitation_that_is_still_valid_is_never_touched(): void
    {
        $live = $this->row('live@example.com', ['expires_at' => now()->addHours(5), 'last_sent_at' => now()->subDays(100), 'created_at' => now()->subDays(100)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertFalse($this->erased($live));
        $this->assertTrue($live->fresh()->isLive());
        $this->assertSame('live@example.com', $live->fresh()->email);
    }

    public function test_a_dry_run_counts_and_changes_nothing(): void
    {
        $old = $this->row('old@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(60)]);
        $this->row('old2@example.com', ['status' => 'revoked', 'revoked_at' => now()->subDays(60)]);
        $auditBefore = AuditLog::count();

        $this->artisan('admin-invitations:purge', ['--dry-run' => true])->expectsOutputToContain('Would erase the name and email of 2 invitations')->assertSuccessful();

        $this->assertSame('old@example.com', $old->fresh()->email);
        $this->assertNull($old->fresh()->personal_data_erased_at);
        $this->assertSame($auditBefore, AuditLog::count());
    }

    public function test_it_writes_one_system_entry_with_counts_only_and_none_when_there_is_nothing(): void
    {
        $this->artisan('admin-invitations:purge')->assertSuccessful();
        $this->assertSame(0, AuditLog::where('action', 'admin.invitations_purged')->count());

        $this->row('secret.person@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(60), 'name' => 'Secret Person']);
        $this->row('another@example.com', ['status' => 'revoked', 'revoked_at' => now()->subDays(60)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $entry = AuditLog::where('action', 'admin.invitations_purged')->sole();
        $this->assertSame(['system', null, ['invitations' => 2, 'retention_days' => 30]], [$entry->actor_type, $entry->actor_user_id, $entry->metadata]);
        $row = strtolower(json_encode($entry->getAttributes()));
        $this->assertStringNotContainsString('secret', $row);
        $this->assertStringNotContainsString('@example.com', $row);
    }

    public function test_running_it_again_finds_nothing_more(): void
    {
        $this->row('old@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(60)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();
        $this->artisan('admin-invitations:purge')->expectsOutputToContain('Erased the name and email of 0 invitations')->assertSuccessful();

        $this->assertSame(1, AuditLog::where('action', 'admin.invitations_purged')->count());
    }

    public function test_the_period_comes_from_the_configuration(): void
    {
        config(['platform-admins.invitation.retention_days' => 7]);
        $eight = $this->row('eight@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(8)]);
        $six = $this->row('six@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(6)]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertTrue($this->erased($eight));
        $this->assertFalse($this->erased($six));
        $this->assertSame(7, AuditLog::where('action', 'admin.invitations_purged')->sole()->metadata['retention_days']);
    }

    public function test_it_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'admin-invitations:purge'));

        $this->assertNotNull($event, 'the command is on the scheduler');
        $this->assertSame('45 3 * * *', $event->expression);
        $this->assertSame(30, config('platform-admins.invitation.retention_days'), '30 days is the default');
    }

    public function test_the_list_shows_an_erased_invitation_as_an_empty_record_without_actions(): void
    {
        $old = $this->row('old@example.com', ['status' => 'pending', 'expires_at' => now()->subDays(40), 'name' => 'Old Person']);
        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $page = $this->as()->get(route('platform.admins', ['tab' => 'invitations']))->assertOk();

        $page->assertSee('Details erased under the retention rule')->assertSee("Invitation #{$old->id}")->assertDontSee('old@example.com')->assertDontSee('Old Person');
        $page->assertDontSee(route('platform.admins.invitations.resend', $old), false)->assertDontSee(route('platform.admins.invitations.revoke', $old), false);
        $page->assertSee('Expired');
    }

    public function test_an_erased_invitation_cannot_be_sent_again_or_revoked(): void
    {
        $old = $this->row('old@example.com', ['status' => 'pending', 'expires_at' => now()->subDays(40), 'last_sent_at' => now()->subDays(40)]);
        $this->artisan('admin-invitations:purge')->assertSuccessful();
        Notification::fake();

        $this->as()->post(route('platform.admins.invitations.resend', $old))->assertSessionHas('error');
        $this->as()->post(route('platform.admins.invitations.revoke', $old))->assertSessionHas('error');

        Notification::assertNothingSent();
        $this->assertSame('pending', $old->fresh()->status->value);
        $this->assertSame(0, AuditLog::whereIn('action', ['admin.invite_resent', 'admin.invite_revoked'])->count());
    }

    public function test_the_same_person_can_be_invited_again_after_their_old_invitation_was_erased(): void
    {
        $this->row('again@example.com', ['status' => 'revoked', 'revoked_at' => now()->subDays(60)]);
        $this->artisan('admin-invitations:purge')->assertSuccessful();
        Notification::fake();

        $this->as()->post(route('platform.admins.store'), ['name' => 'Again Person', 'email' => 'again@example.com'])->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(1, AdminInvitation::live()->count());
        $this->assertSame(2, AdminInvitation::count());
    }

    public function test_only_the_matching_columns_are_erased(): void
    {
        $user = User::factory()->create(['name' => 'Real Account', 'email' => 'real@example.com']);
        $old = $this->row('real@example.com', ['status' => 'accepted', 'accepted_at' => now()->subDays(60), 'accepted_user_id' => $user->id]);

        $this->artisan('admin-invitations:purge')->assertSuccessful();

        $this->assertTrue($this->erased($old));
        $this->assertSame(['Real Account', 'real@example.com'], [$user->fresh()->name, $user->fresh()->email], 'the account itself is not touched');
    }
}
