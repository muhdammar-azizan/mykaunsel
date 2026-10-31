<?php

namespace Tests\Feature\Api;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\CalendarEntry;
use App\Models\ChatSession;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Membership;
use App\Models\Message;
use App\Models\User;
use App\Notifications\AccountDeleted;
use App\Notifications\BookingCancelledCounselor;
use App\Notifications\MemberLeft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * DELETE /api/v1/account: a client erases what identifies them and what they wrote, ends their sessions and membership, and what other people
 * depend on stays (docs/ACCOUNT_DELETION.md). The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class AccountDeletionTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->me = User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@gmail.test', 'email_verified_at' => now(), 'password' => 'secret-pass-123']);
        Sanctum::actingAs($this->me);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function erase(array $body = ['current_password' => 'secret-pass-123', 'confirm' => 'DELETE'])
    {
        return $this->deleteJson('/api/v1/account', $body);
    }

    public function test_it_needs_the_password_and_the_typed_word_and_changes_nothing_without_them(): void
    {
        $this->erase(['current_password' => 'wrong', 'confirm' => 'DELETE'])->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->erase(['current_password' => 'secret-pass-123', 'confirm' => 'delete'])->assertStatus(422)->assertJsonValidationErrors('confirm');
        $this->erase(['current_password' => 'secret-pass-123'])->assertStatus(422);

        $this->assertNull($this->me->fresh()->deleted_at);
        $this->assertSame('Siti Aminah', $this->me->fresh()->name);
    }

    public function test_a_guest_can_not(): void
    {
        $this->app['auth']->forgetGuards();

        $this->deleteJson('/api/v1/account', ['current_password' => 'x', 'confirm' => 'DELETE'])->assertUnauthorized();
    }

    public function test_what_identifies_the_person_is_erased_and_the_row_stays_as_an_anonymous_stub(): void
    {
        Notification::fake();
        $this->me->createToken('phone');
        $this->me->createToken('tablet');
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $this->me->id, 'payload' => '', 'last_activity' => time()]);
        $this->me->forceFill(['ai_consent_at' => now(), 'last_login_ip' => '203.0.113.9', 'two_factor_secret' => 'SECRET'])->save();

        $this->erase()->assertOk()->assertJsonPath('deleted', true);

        $stub = $this->me->fresh();
        $this->assertSame('Deleted user', $stub->name);
        $this->assertStringEndsWith('@deleted.invalid', $stub->email);
        $this->assertNotNull($stub->deleted_at);
        $this->assertNull($stub->email_verified_at);
        $this->assertNull($stub->ai_consent_at);
        $this->assertNull($stub->last_login_ip);
        $this->assertNull($stub->two_factor_secret);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('secret-pass-123', $stub->password));
        $this->assertSame(0, $stub->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->me->id)->count());
    }

    public function test_the_address_is_told_and_a_second_delete_finds_nothing(): void
    {
        Notification::fake();

        $this->erase()->assertOk();

        Notification::assertSentOnDemand(AccountDeleted::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'siti@gmail.test');
        $this->assertTrue(User::where('email', 'siti@gmail.test')->doesntExist(), 'the address is gone from our records');
        $this->assertSame(1, AuditLog::where('action', 'account.deleted')->count());
    }

    public function test_chats_and_feed_writing_go_but_the_rows_other_people_point_at_stay_without_words(): void
    {
        Notification::fake();
        $chat = ChatSession::create(['user_id' => $this->me->id, 'started_at' => now(), 'last_message_at' => now()]);
        Message::create(['chat_session_id' => $chat->id, 'sender' => 'user', 'message_text' => 'a private worry', 'sent_at' => now()]);
        $post = FeedPost::create(['user_id' => $this->me->id, 'content' => 'my own post']);
        $other = User::factory()->create();
        $reply = FeedComment::create(['feed_post_id' => $post->id, 'user_id' => $other->id, 'content' => 'a reply from someone else']);
        $mine = FeedComment::create(['feed_post_id' => $post->id, 'user_id' => $this->me->id, 'content' => 'my comment']);
        $theirPost = FeedPost::create(['user_id' => $other->id, 'content' => 'someone else post']);
        DB::table('post_reactions')->insert(['feed_post_id' => $theirPost->id, 'user_id' => $this->me->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->erase()->assertOk();

        $this->assertSame(0, ChatSession::where('user_id', $this->me->id)->count());
        $this->assertSame(0, Message::where('chat_session_id', $chat->id)->count());
        $this->assertSame('', $post->fresh()->content);
        $this->assertNotNull($post->fresh()->deleted_at);
        $this->assertSame('', $mine->fresh()->content);
        $this->assertSame('a reply from someone else', $reply->fresh()->content, 'other people\'s words are theirs');
        $this->assertSame('someone else post', $theirPost->fresh()->content);
        $this->assertSame(0, DB::table('post_reactions')->where('user_id', $this->me->id)->count());
    }

    public function test_every_later_session_is_cancelled_even_the_next_few_days_the_time_is_given_back_and_counselors_are_told_without_the_name(): void
    {
        Notification::fake();
        $aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $dr = $this->counselor('Dr Public', [$this->open]);
        $soon = $this->book($this->slot($aina, $this->closed, '2026-11-03 10:00'), $this->me);
        $later = $this->book($this->slot($dr, $this->open, '2026-11-20 14:00'), $this->me);
        $over = $this->book($this->slot($aina, $this->closed, '2026-11-02 08:00'), $this->me); // already started: part of the record
        $done = $this->book($this->slot($aina, $this->closed, '2026-10-20 10:00'), $this->me, BookingStatus::Completed);

        $this->erase()->assertOk()->assertJsonPath('cancelled', 2);

        foreach ([$soon, $later] as $b) {
            $this->assertSame(BookingStatus::Cancelled, $b->fresh()->status);
            $this->assertTrue(CalendarEntry::find($b->calendar_entry_id)->is_available);
        }

        $this->assertSame(BookingStatus::Confirmed, $over->fresh()->status);
        $this->assertSame(BookingStatus::Completed, $done->fresh()->status);

        Notification::assertSentTo($aina->user, BookingCancelledCounselor::class, fn ($n) => ! str_contains(json_encode((new \ReflectionProperty($n, 'session'))->getValue($n)), 'Siti'));
        Notification::assertSentTo($dr->user, BookingCancelledCounselor::class);
        $this->assertSame(4, Booking::where('user_id', $this->me->id)->count(), 'the booking records stay');
    }

    public function test_time_requests_nobody_answered_are_withdrawn(): void
    {
        Notification::fake();
        $aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $request = BookingRequest::create([
            'user_id' => $this->me->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $this->closed->id, 'requested_date' => '2026-11-12',
            'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addDays(3),
        ]);

        $this->erase()->assertOk();

        $this->assertSame(BookingRequestStatus::Withdrawn, $request->fresh()->status);
    }

    public function test_the_organization_loses_the_member_and_its_admins_hear_the_name_only_but_a_waiting_request_leaves_quietly(): void
    {
        Notification::fake();
        $hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $hr->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $row = Membership::create(['user_id' => $this->me->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->erase()->assertOk();

        $this->assertSame(MembershipStatus::Offboarded, $row->fresh()->status);
        Notification::assertSentTo($hr, MemberLeft::class, fn ($n) => str_contains(implode(' ', $n->toMail($hr)->introLines), 'Siti Aminah'));

        // a different person who was only waiting: nobody is told
        Notification::fake();
        $waiter = User::factory()->create(['email_verified_at' => now(), 'password' => 'secret-pass-123']);
        Membership::create(['user_id' => $waiter->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::PendingApproval, 'joined_at' => now()]);
        Sanctum::actingAs($waiter);

        $this->erase()->assertOk();

        Notification::assertNotSentTo($hr, MemberLeft::class);
    }

    public function test_counselors_and_administrators_are_refused_and_nothing_changes(): void
    {
        $profile = $this->counselor('Aina Syazwani', [$this->closed]);
        $profile->user->update(['password' => 'secret-pass-123']);
        Sanctum::actingAs($profile->user);

        $this->erase()->assertStatus(403)->assertJsonPath('code', 'staff_account');
        $this->assertNull($profile->user->fresh()->deleted_at);

        $admin = User::factory()->create(['email_verified_at' => now(), 'password' => 'secret-pass-123']);
        Membership::create(['user_id' => $admin->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        Sanctum::actingAs($admin);

        $this->erase()->assertStatus(403)->assertJsonPath('code', 'staff_account');
        $this->assertSame(0, AuditLog::where('action', 'account.deleted')->count());
    }

    public function test_the_audit_entry_holds_ids_only(): void
    {
        Notification::fake();
        $aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $later = $this->book($this->slot($aina, $this->closed, '2026-11-20 14:00'), $this->me);

        $this->erase()->assertOk();

        $log = AuditLog::where('action', 'account.deleted')->sole();
        $this->assertSame($this->me->id, $log->target_id);
        $this->assertSame([$later->id], $log->metadata['cancelled_booking_ids']);
        $this->assertStringNotContainsString('Siti', json_encode($log->metadata));
        $this->assertStringNotContainsString('@', json_encode($log->metadata));
    }

    public function test_a_failing_mail_does_not_undo_the_deletion(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        $this->erase()->assertOk();

        $this->assertNotNull($this->me->fresh()->deleted_at);
    }
}
