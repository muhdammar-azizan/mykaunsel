<?php

namespace Tests\Feature\Api;

use App\Enums\AccessModel;
use App\Enums\BookingRequestStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\FeedPost;
use App\Models\FeedReport;
use App\Models\Membership;
use App\Models\OrgInviteCode;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FeedModerationService;
use App\Services\MembershipEnding;
use App\Services\MembershipService;
use App\Services\NotificationCenter;
use App\Services\OrgMemberService;
use App\Support\Inbox\ClientNotificationTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The app's notifications (docs/NOTIFICATIONS.md): what happened to a client that they did not do themselves, listed, counted and marked read through
 * /api/v1/notifications, and created by the places that already e-mail them. The clock stands at Monday 2 November 2026, 09:00 Malaysian time.
 */
class NotificationApiTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private User $me;

    private NotificationCenter $center;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->center = app(NotificationCenter::class);
        $this->me = User::factory()->create(['name' => 'Siti Aminah', 'email_verified_at' => now()]);
        Sanctum::actingAs($this->me);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function give(string $type, ?\Illuminate\Database\Eloquent\Model $target = null, ?string $label = 'Acme Berhad', string $occurrence = '', ?User $to = null): void
    {
        $this->center->notify($to ?? $this->me, $type, $target, $label, $occurrence);
    }

    private function mine(): \Illuminate\Database\Eloquent\Collection
    {
        return UserNotification::query()->where('recipient_user_id', $this->me->id)->orderBy('id')->get();
    }

    // ---- the API -----------------------------------------------------------------------------------------------------

    public function test_it_needs_a_signed_in_confirmed_person(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['email_verified_at' => null]));
        $this->getJson('/api/v1/notifications')->assertStatus(403);
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->getJson('/api/v1/notifications')->assertOk()->assertExactJson(['data' => [], 'unread' => 0, 'page' => 1, 'last_page' => 1])
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertExactJson(['unread' => 0]);
    }

    public function test_an_item_shows_its_type_label_time_and_a_public_id_and_never_a_counter(): void
    {
        $org = $this->open;
        $this->give('membership.approved', $org, 'Acme Berhad', 'a');

        $item = $this->getJson('/api/v1/notifications')->assertOk()->json('data.0');

        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $item['id']);
        $this->assertSame('membership.approved', $item['type']);
        $this->assertSame('Your organization approved you', $item['title']);
        $this->assertSame('Acme Berhad', $item['label']);
        $this->assertSame(1, $item['count']);
        $this->assertFalse($item['read']);
        $this->assertSame(now()->toIso8601String(), $item['occurred_at']);
        $this->assertNull($item['target'], 'an organization needs no page of its own in the app');
        $this->assertStringNotContainsString((string) $org->id.'"', json_encode($item));
    }

    public function test_a_booking_or_request_comes_with_its_public_id_and_vanishes_when_it_does_not_exist(): void
    {
        $aina = $this->counselor('Aina', [$this->open]);
        $booking = $this->book($this->slot($aina, $this->open, '2026-11-05 10:00'), $this->me);
        $request = BookingRequest::create([
            'user_id' => $this->me->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $this->open->id, 'requested_date' => '2026-11-06',
            'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online', 'status' => BookingRequestStatus::Approved, 'expires_at' => now()->addDay(),
        ]);
        $this->give('booking.cancelled', $booking, 'Aina');
        $this->give('request.approved', $request, 'Aina');

        $data = collect($this->getJson('/api/v1/notifications')->json('data'))->keyBy('type');
        $this->assertSame(['kind' => 'booking', 'id' => $booking->public_id], $data['booking.cancelled']['target']);
        $this->assertSame(['kind' => 'booking_request', 'id' => $request->public_id], $data['request.approved']['target']);

        $request->delete();
        $gone = collect($this->getJson('/api/v1/notifications')->json('data'))->keyBy('type');
        $this->assertNull($gone['request.approved']['target']);
    }

    public function test_newest_first_in_pages_of_twenty_with_the_unread_number_for_the_whole_list(): void
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->travel(1)->minutes();
            $this->give('membership.approved', $this->open, 'Org '.$i, (string) $i);
        }

        $first = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertCount(20, $first->json('data'));
        $this->assertSame('Org 23', $first->json('data.0.label'));
        $this->assertSame(23, $first->json('unread'));
        $this->assertSame(2, $first->json('last_page'));

        $second = $this->getJson('/api/v1/notifications?page=2')->json('data');
        $this->assertCount(3, $second);
        $this->assertSame('Org 3', $second[0]['label']);
    }

    public function test_reading_one_is_safe_to_repeat_and_changes_only_the_count(): void
    {
        $this->give('membership.approved', $this->open, 'A', '1');
        $this->give('membership.declined', $this->open, 'B', '2');
        $id = $this->getJson('/api/v1/notifications')->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertExactJson(['unread' => 1]);
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertExactJson(['unread' => 1]);

        $this->assertTrue(collect($this->getJson('/api/v1/notifications')->json('data'))->firstWhere('id', $id)['read']);
        $this->getJson('/api/v1/notifications/unread-count')->assertExactJson(['unread' => 1]);
    }

    public function test_read_all_marks_this_persons_only(): void
    {
        $other = User::factory()->create();
        $this->give('membership.approved', $this->open, 'A', '1');
        $this->give('membership.declined', $this->open, 'B', '2');
        $this->give('membership.approved', $this->open, 'Theirs', '3', $other);

        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertExactJson(['marked' => 2, 'unread' => 0]);

        $this->assertSame(0, $this->center->unreadCount($this->me));
        $this->assertSame(1, $this->center->unreadCount($other));
    }

    public function test_someone_elses_and_an_unknown_notification_are_the_same_404(): void
    {
        $other = User::factory()->create();
        $this->give('membership.approved', $this->open, 'Theirs', '1', $other);
        $theirs = UserNotification::where('recipient_user_id', $other->id)->value('public_id');

        $this->postJson("/api/v1/notifications/{$theirs}/read")->assertNotFound()->assertJsonPath('code', 'notification_not_found');
        $this->postJson('/api/v1/notifications/'.str_repeat('0', 26).'/read')->assertNotFound();
        $this->assertSame(1, $this->center->unreadCount($other), 'untouched');
        $this->getJson('/api/v1/notifications')->assertJsonPath('data', []);
    }

    public function test_only_the_apps_own_group_is_listed_and_counted_never_a_platform_admins(): void
    {
        $this->give('membership.approved', $this->open, 'Mine', '1');
        // A Platform Admin notification that happens to be addressed to the same person is none of this API's business.
        UserNotification::create(['recipient_user_id' => $this->me->id, 'type' => 'organization.application_submitted', 'target_type' => 'organization', 'target_id' => $this->open->id, 'label' => 'Admin only', 'occurrence_key' => 'x:1', 'count' => 1, 'occurred_at' => now()]);

        $this->getJson('/api/v1/notifications')->assertJsonCount(1, 'data')->assertJsonPath('unread', 1);
        $this->postJson('/api/v1/notifications/read-all')->assertJsonPath('marked', 1);
        $this->assertSame(1, $this->center->unreadCount($this->me), 'the admin one stays unread');
    }

    public function test_the_same_event_told_twice_makes_one_and_a_failure_never_throws(): void
    {
        $this->give('membership.approved', $this->open, 'A', 'same');
        $this->give('membership.approved', $this->open, 'A', 'same');
        $this->assertCount(1, $this->mine());

        $this->assertFalse($this->center->notify($this->me, 'no.such.type', null, 'x'), 'an unknown type is reported, not thrown');
        $this->assertFalse($this->center->notify(null, 'membership.approved', $this->open, 'x'));
        $this->assertFalse($this->center->notify($this->me, 'membership.approved', null, 'x'), 'a type that needs a target without one');
    }

    public function test_every_type_of_the_app_belongs_to_the_group_and_has_a_title(): void
    {
        $registry = app(\App\Support\Inbox\NotificationRegistry::class);
        $keys = $registry->keysInGroup(ClientNotificationTypes::GROUP);

        $this->assertEqualsCanonicalizing([
            'request.approved', 'request.declined', 'request.expired', 'booking.cancelled', 'invitation.received',
            'membership.approved', 'membership.declined', 'membership.ended', 'feed.removed', 'feed.warned',
        ], $keys);

        foreach ($keys as $key) {
            $this->assertNotSame('', $registry->get($key)->title);
        }
    }

    // ---- the places that create them -------------------------------------------------------------------------------

    private function hr(): User
    {
        $hr = User::factory()->create(['email_verified_at' => now()]);
        Membership::create(['user_id' => $hr->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $hr;
    }

    public function test_the_organization_answering_a_request_and_inviting_are_news(): void
    {
        Notification::fake();
        $hr = $this->hr();
        $row = app(MembershipService::class)->joinViaInviteCode($this->me, OrgInviteCode::issue($this->closed, $hr, null, null)->code);
        $service = app(OrgMemberService::class);

        $service->approve($this->closed, $row->id, $hr);
        $this->assertSame(['membership.approved'], $this->mine()->pluck('type')->all());
        $this->assertSame('Universiti Contoh', $this->mine()[0]->label);

        $other = User::factory()->create(['email_verified_at' => now()]);
        $second = app(MembershipService::class)->joinViaInviteCode($other, OrgInviteCode::issue($this->closed, $hr, null, null)->code);
        $service->decline($this->closed, $second->id, $hr);
        $this->assertSame(['membership.declined'], UserNotification::where('recipient_user_id', $other->id)->pluck('type')->all());
    }

    public function test_an_invitation_is_news_only_for_someone_who_already_has_the_address_confirmed(): void
    {
        Notification::fake();
        $hr = $this->hr();
        $mine = User::factory()->create(['email' => 'siti@gmail.test', 'email_verified_at' => now()]);
        $unconfirmed = User::factory()->create(['email' => 'budi@gmail.test', 'email_verified_at' => null]);
        $service = app(OrgMemberService::class);

        $service->invite($this->closed, $hr, 'Siti@Gmail.test');
        $service->invite($this->closed, $hr, 'budi@gmail.test');
        $service->invite($this->closed, $hr, 'nobody@gmail.test');

        $this->assertSame(['invitation.received'], UserNotification::where('recipient_user_id', $mine->id)->pluck('type')->all());
        $this->assertSame(0, UserNotification::where('recipient_user_id', $unconfirmed->id)->count(), 'an address nobody proved is theirs gets nothing');
        $this->assertSame(1, UserNotification::count());
    }

    public function test_being_removed_is_news_but_leaving_by_oneself_is_not_and_sessions_cancelled_either_way_are(): void
    {
        Notification::fake();
        $hr = $this->hr();
        $aina = $this->counselor('Aina', [$this->closed]);
        $row = Membership::create(['user_id' => $this->me->id, 'org_id' => $this->closed->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $later = $this->book($this->slot($aina, $this->closed, '2026-11-20 14:00'), $this->me);

        app(MembershipEnding::class)->remove($this->closed, $row->id, $hr);

        $this->assertEqualsCanonicalizing(['membership.ended', 'booking.cancelled'], $this->mine()->pluck('type')->all());
        $this->assertSame($later->id, $this->mine()->firstWhere('type', 'booking.cancelled')->target_id);

        // by oneself: only the cancelled session
        UserNotification::query()->delete();
        $again = Membership::where('user_id', $this->me->id)->first();
        $again->update(['status' => MembershipStatus::Active]);
        $second = $this->book($this->slot($aina, $this->closed, '2026-11-21 14:00'), $this->me);

        app(MembershipEnding::class)->leave($this->me);

        $this->assertSame(['booking.cancelled'], $this->mine()->pluck('type')->all());
        $this->assertSame($second->id, $this->mine()[0]->target_id);
    }

    public function test_a_counselor_being_suspended_cancels_sessions_and_closes_requests_with_news_for_the_client(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@mykaunsel.com')->firstOrFail();
        $aina = $this->counselor('Aina Syazwani', [$this->closed]);
        $booking = $this->book($this->slot($aina, $this->closed, '2026-11-20 14:00'), $this->me);
        $request = BookingRequest::create([
            'user_id' => $this->me->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $this->closed->id, 'requested_date' => '2026-11-12',
            'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->addDays(3),
        ]);

        app(\App\Services\CounselorStatusService::class)->suspend($aina, $admin, 'A reason that must not be copied anywhere', true);

        $rows = $this->mine();
        $this->assertEqualsCanonicalizing(['booking.cancelled', 'request.expired'], $rows->pluck('type')->all());
        $this->assertSame('Aina Syazwani', $rows->firstWhere('type', 'booking.cancelled')->label);
        $this->assertSame($booking->id, $rows->firstWhere('type', 'booking.cancelled')->target_id);
        $this->assertSame($request->id, $rows->firstWhere('type', 'request.expired')->target_id);
        $this->assertStringNotContainsString('must not be copied', json_encode($rows->toArray()));
    }

    public function test_requests_nobody_answered_in_time_are_news(): void
    {
        Notification::fake();
        $aina = $this->counselor('Aina', [$this->closed]);
        $request = BookingRequest::create([
            'user_id' => $this->me->id, 'counselor_user_id' => $aina->user_id, 'context_org_id' => $this->closed->id, 'requested_date' => '2026-11-12',
            'requested_time' => '10:00:00', 'duration_minutes' => 50, 'session_mode' => 'online', 'status' => BookingRequestStatus::Pending, 'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('requests:expire')->assertSuccessful();
        $this->artisan('requests:expire')->assertSuccessful();

        $this->assertSame(['request.expired'], $this->mine()->pluck('type')->all());
        $this->assertSame($request->id, $this->mine()[0]->target_id);
    }

    public function test_feed_moderation_tells_the_author_without_the_writing_or_the_admins_words(): void
    {
        Notification::fake();
        $admin = User::where('email', 'admin@mykaunsel.com')->firstOrFail();
        $post = FeedPost::create(['user_id' => $this->me->id, 'content' => 'A rude thing it said']);
        $reporter = User::factory()->create();
        FeedReport::create(['user_id' => $reporter->id, 'feed_post_id' => $post->id, 'reason' => 'spam', 'status' => 'pending']);

        app(FeedModerationService::class)->warn('post', $post->id, $admin, 'Please be kind to others here.');
        $this->assertSame(['feed.warned'], $this->mine()->pluck('type')->all());

        FeedReport::create(['user_id' => User::factory()->create()->id, 'feed_post_id' => $post->id, 'reason' => 'harassment', 'status' => 'pending']);
        app(FeedModerationService::class)->remove('post', $post->id, $admin, 'You were warned.');

        $this->assertEqualsCanonicalizing(['feed.warned', 'feed.removed'], $this->mine()->pluck('type')->all());
        $json = json_encode($this->mine()->toArray());

        foreach (['rude', 'kind to others', 'You were warned', 'Secret'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    public function test_deleted_accounts_lose_their_notifications_with_the_rest(): void
    {
        Notification::fake();
        $this->give('membership.approved', $this->open, 'A', '1');
        $this->me->forceFill(['password' => 'secret-pass-123'])->save();

        $this->deleteJson('/api/v1/account', ['current_password' => 'secret-pass-123', 'confirm' => 'DELETE'])->assertOk();

        $this->assertSame(0, UserNotification::where('recipient_user_id', $this->me->id)->count());
    }

    public function test_the_retention_command_still_clears_old_ones(): void
    {
        $this->give('membership.approved', $this->open, 'Old', '1');
        UserNotification::query()->update(['read_at' => now()->subDays(100), 'occurred_at' => now()->subDays(100)]);

        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertSame(0, UserNotification::count());
    }
}
