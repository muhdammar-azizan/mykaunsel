<?php

namespace Tests\Feature\Counselor;

use App\Models\CounselorProfile;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use App\Support\Inbox\CounselorNotificationTypes;
use App\Support\Inbox\NotificationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/**
 * The counselor portal's bell and notifications page: the types a counselor receives, where each one leads, and that it is only ever their own.
 * (The events that CREATE them are tested next to the e-mails they accompany: BookingApiTest, BookingCancelApiTest, BookingRescheduleApiTest,
 * TimeRequestApiTest, ExpireTimeRequestsTest, CounselorPaExpiryTest, CounselorStatusTest.) The clock stands at Monday 2 November 2026, 09:00.
 */
class CounselorNotificationsTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

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

    private function tell(CounselorProfile $who, string $type, $target, string $label = 'Thu, 5 Nov, 10:00 AM'): void
    {
        $this->assertTrue(app(NotificationCenter::class)->notify($who->user, $type, $target, $label));
    }

    public function test_every_counselor_type_is_defined_in_its_own_group_and_leads_to_a_page_of_the_portal(): void
    {
        $registry = app(NotificationRegistry::class);

        $expected = [
            'session.booked' => route('counselor.appointments'),
            'session.cancelled_by_client' => route('counselor.appointments'),
            'session.rescheduled' => route('counselor.appointments'),
            'request.received' => route('counselor.requests'),
            'request.lapsed' => route('counselor.requests', ['tab' => 'answered']),
            'request.withdrawn' => route('counselor.requests', ['tab' => 'answered']),
            'pa.expiring' => route('counselor.profile'),
            'profile.suspended' => route('counselor.dashboard'),
            'profile.reactivated' => route('counselor.dashboard'),
        ];

        $this->assertEqualsCanonicalizing(array_keys($expected), $registry->keysInGroup(CounselorNotificationTypes::GROUP));

        foreach ($expected as $key => $url) {
            $type = $registry->get($key);
            $this->assertSame(CounselorNotificationTypes::GROUP, $type->group, $key);
            $this->assertNotSame('', $type->title);
            $this->assertSame($url, $type->url(123), "{$key} opens a list page, whatever the stored id says");
        }

        // The mobile app lists only its own group, so none of these ever reaches it.
        $this->assertSame([], array_intersect($registry->keysInGroup('activity'), array_keys($expected)));
    }

    public function test_the_bell_and_the_page_show_my_notifications_with_a_text_title_and_the_time_only(): void
    {
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'), User::factory()->create(['name' => 'Siti Client']));
        $this->tell($this->aina, 'session.booked', $booking);

        $this->as()->getJson(route('portal.notifications.count'))->assertOk();

        $page = $this->as()->get(route('portal.notifications'))->assertOk();
        $page->assertSee('A client booked a session')->assertSee('Thu, 5 Nov, 10:00 AM')->assertDontSee('Siti Client');

        $this->as()->get(route('portal.notifications.panel'))->assertOk()->assertSee('A client booked a session');
    }

    public function test_opening_one_marks_it_read_and_goes_to_the_right_page(): void
    {
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'), User::factory()->create());
        $this->tell($this->aina, 'session.booked', $booking);
        $row = UserNotification::where('recipient_user_id', $this->aina->user_id)->sole();
        $this->assertNull($row->read_at);

        $this->as()->post(route('portal.notifications.open', $row->id))->assertRedirect(route('counselor.appointments'));

        $this->assertNotNull($row->fresh()->read_at);
    }

    public function test_someone_elses_notification_is_never_listed_or_openable(): void
    {
        $bakar = $this->counselor('Bakar', [$this->platform]);
        $booking = $this->book($this->slot($bakar, $this->platform, '2026-11-06 10:00'), User::factory()->create());
        $this->tell($bakar, 'session.booked', $booking, 'Fri, 6 Nov, 10:00 AM');
        $theirs = UserNotification::where('recipient_user_id', $bakar->user_id)->sole();

        $this->as()->get(route('portal.notifications'))->assertOk()->assertDontSee('Fri, 6 Nov, 10:00 AM')->assertDontSee('A client booked a session');
        $this->as()->post(route('portal.notifications.open', $theirs->id))->assertNotFound();
        $this->as()->post(route('portal.notifications.read', $theirs->id))->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_the_same_event_told_twice_makes_one_notification(): void
    {
        $booking = $this->book($this->slot($this->aina, $this->platform, '2026-11-05 10:00'), User::factory()->create());

        $this->tell($this->aina, 'session.booked', $booking);
        $this->assertFalse(app(NotificationCenter::class)->notify($this->aina->user, 'session.booked', $booking, 'Thu, 5 Nov, 10:00 AM'));

        $this->assertSame(1, UserNotification::where('recipient_user_id', $this->aina->user_id)->count());
    }
}
