<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Inbox\NotificationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/**
 * The full notifications page: All / Unread, the filter by kind, 25 per page, the three kinds of empty state, marking one or all as read,
 * and that everything stays inside the signed-in admin's own notifications.
 */
class NotificationPageTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private const NOW = '2026-10-20 12:00:00';

    private User $admin;

    private User $other;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::NOW);
        $this->admin = $this->makeAdmin('Aminah');
        $this->other = $this->makeAdmin('Other Admin');
        $this->session = ['current_org_id' => $this->platform()->id, 'current_role' => 'platform_admin'];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeAdmin(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function as(?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->withSession($this->session);
    }

    private function item(User $user, string $type = 'organization.application_submitted', string $label = 'Some Name', int $minutesAgo = 0, bool $read = false, ?int $targetId = null): UserNotification
    {
        static $n = 0;
        $targetType = app(NotificationRegistry::class)->get($type)->targetType;

        return UserNotification::create([
            'recipient_user_id' => $user->id, 'type' => $type, 'target_type' => $targetType,
            'target_id' => $targetType ? ($targetId ?? $this->org('university', 'pending')->id) : null,
            'label' => $label, 'occurrence_key' => 'p'.++$n, 'occurred_at' => now()->subMinutes($minutesAgo), 'read_at' => $read ? now() : null,
        ]);
    }

    /** @return list<string> the labels listed on the page, in order */
    private function labels(string $html): array
    {
        preg_match_all('/class="block truncate text-\[13px\] text-brand-ink\/70">([^<]*)</', $html, $found);

        return array_map('html_entity_decode', $found[1]);
    }

    private function unreadFor(User $user): int
    {
        return UserNotification::query()->ownedBy($user)->unread()->count();
    }

    // ---- all / unread / filter -----------------------------------------------------------------------------------------------------------------------

    public function test_all_and_unread_tabs_with_their_counts(): void
    {
        $this->item($this->admin, label: 'Unread A', minutesAgo: 1);
        $this->item($this->admin, label: 'Read B', minutesAgo: 2, read: true);
        $this->item($this->admin, label: 'Unread C', minutesAgo: 3);
        $this->item($this->other, label: 'Not Mine');

        $all = $this->as()->get(route('platform.notifications'))->assertOk();
        $this->assertSame(['Unread A', 'Read B', 'Unread C'], $this->labels($all->getContent()), 'newest first');
        $all->assertSee('aria-label="Notification views"', false)->assertDontSee('Not Mine');
        $this->assertMatchesRegularExpression('/All\s*<span[^>]*>3<\/span>/', $all->getContent());
        $this->assertMatchesRegularExpression('/Unread\s*<span[^>]*>2<\/span>/', $all->getContent());

        $unread = $this->as()->get(route('platform.notifications', ['tab' => 'unread']))->assertOk();
        $this->assertSame(['Unread A', 'Unread C'], $this->labels($unread->getContent()));
    }

    public function test_the_filter_by_kind_keeps_only_that_group_and_the_counts_follow_it(): void
    {
        $this->item($this->admin, 'organization.application_submitted', 'An Application');
        $this->item($this->admin, 'counselor.kb_duplicate_flagged', 'A Duplicate', targetId: $this->counselor('independent', 'approved')->id);
        $this->item($this->admin, 'system.maintenance_auto_reopened', 'A System Thing', read: true);
        $this->item($this->admin, 'admin.invitation_accepted', 'A New Admin', targetId: $this->other->id);

        $applications = $this->as()->get(route('platform.notifications', ['group' => 'applications']));
        $this->assertEqualsCanonicalizing(['An Application', 'A Duplicate'], $this->labels($applications->getContent()));
        $this->assertMatchesRegularExpression('/All\s*<span[^>]*>2<\/span>/', $applications->getContent());

        $system = $this->as()->get(route('platform.notifications', ['group' => 'system', 'tab' => 'unread']));
        $this->assertSame([], $this->labels($system->getContent()));

        $this->assertSame(['A New Admin'], $this->labels($this->as()->get(route('platform.notifications', ['group' => 'admins']))->getContent()));
        $applications->assertSee('aria-label="Filter by kind"', false);
    }

    public function test_an_unknown_tab_or_kind_in_the_address_is_ignored(): void
    {
        $this->item($this->admin, label: 'Only One');

        $page = $this->as()->get(route('platform.notifications', ['tab' => 'nonsense', 'group' => 'nonsense', 'page' => 'x']))->assertOk();

        $this->assertSame(['Only One'], $this->labels($page->getContent()));
        $this->as()->get(route('platform.notifications', ['group' => ['a'], 'tab' => ['b']]))->assertOk();
    }

    // ---- 25 per page ------------------------------------------------------------------------------------------------------------------------------------

    public function test_25_per_page_in_order_with_the_filters_kept_in_the_page_links(): void
    {
        foreach (range(1, 30) as $i) {
            $this->item($this->admin, label: sprintf('Entry %02d', $i), minutesAgo: 60 - $i);
        }

        $first = $this->as()->get(route('platform.notifications', ['tab' => 'unread']))->assertOk();
        $labels = $this->labels($first->getContent());
        $this->assertCount(25, $labels);
        $this->assertSame('Entry 30', $labels[0]);
        $this->assertSame('Entry 06', $labels[24]);
        $this->assertStringContainsString('tab=unread', html_entity_decode($first->getContent()));
        $this->assertMatchesRegularExpression('/page=2/', $first->getContent());

        $this->assertSame(['Entry 05', 'Entry 04', 'Entry 03', 'Entry 02', 'Entry 01'], $this->labels($this->as()->get(route('platform.notifications', ['tab' => 'unread', 'page' => 2]))->getContent()));
    }

    public function test_a_page_past_the_end_goes_to_the_last_real_page(): void
    {
        foreach (range(1, 30) as $i) {
            $this->item($this->admin, label: "Entry {$i}", minutesAgo: $i);
        }

        $this->as()->get(route('platform.notifications', ['page' => 9]))->assertRedirect(route('platform.notifications', ['page' => 2]));
    }

    // ---- three different empty states ------------------------------------------------------------------------------------------------------------------

    public function test_no_notifications_nothing_unread_and_no_match_are_different_messages(): void
    {
        $this->as()->get(route('platform.notifications'))->assertSee('No notifications yet')->assertSee('They appear here when something happens')->assertDontSee('No notifications match');

        $this->item($this->admin, label: 'Already Read', read: true);

        $this->as()->get(route('platform.notifications', ['tab' => 'unread']))->assertSee("You're all caught up", false)->assertSee('Nothing is unread')->assertDontSee('No notifications yet')->assertDontSee('No notifications match');

        $this->as()->get(route('platform.notifications', ['group' => 'complaints']))->assertSee('No notifications match this filter')->assertSee('Clear the filter')->assertDontSee('No notifications yet');
        $this->as()->get(route('platform.notifications', ['group' => 'complaints', 'tab' => 'unread']))->assertSee('No notifications match this filter');
    }

    public function test_another_admins_items_do_not_make_my_page_non_empty(): void
    {
        $this->item($this->other, label: 'Theirs');

        $this->as()->get(route('platform.notifications'))->assertSee('No notifications yet')->assertDontSee('Theirs');
    }

    // ---- reviewed, retention note ----------------------------------------------------------------------------------------------------------------------

    public function test_the_page_shows_the_reviewed_state_the_count_detail_and_the_retention_rule(): void
    {
        $this->item($this->admin, 'organization.application_submitted', 'Decided Org', targetId: $this->org('university', 'active')->id);
        $profile = $this->counselor('independent', 'approved');
        $row = $this->item($this->admin, 'counselor.kb_duplicate_flagged', 'Repeated Name', targetId: $profile->id);
        $row->forceFill(['count' => 4])->save();

        $page = $this->as()->get(route('platform.notifications'))->assertOk();

        $page->assertSee('Already reviewed')->assertSee('Repeated Name · 4 attempts', false)
            ->assertSee('Read notifications are deleted after 90 days and unread ones after 180 days.');

        config(['notifications.retention.read_days' => 30, 'notifications.retention.unread_days' => 60]);
        $this->as()->get(route('platform.notifications'))->assertSee('Read notifications are deleted after 30 days and unread ones after 60 days.');
    }

    // ---- mark one ---------------------------------------------------------------------------------------------------------------------------------------------

    public function test_mark_as_read_and_unread_from_the_page_and_come_back_to_the_same_view(): void
    {
        $row = $this->item($this->admin, label: 'Toggle Me');

        $this->as()->post(route('platform.notifications.read', $row->id), ['tab' => 'unread', 'group' => 'applications', 'page' => 3])
            ->assertRedirect(route('platform.notifications', ['tab' => 'unread', 'group' => 'applications', 'page' => 3]));
        $this->assertNotNull($row->fresh()->read_at);

        $this->as()->post(route('platform.notifications.unread', $row->id))->assertRedirect(route('platform.notifications'));
        $this->assertNull($row->fresh()->read_at);

        $page = $this->as()->get(route('platform.notifications'))->getContent();
        $this->assertStringContainsString('Mark as read', $page);
        $this->assertStringContainsString(route('platform.notifications.read', $row->id), $page);
    }

    public function test_only_checked_values_are_carried_back_so_the_return_trip_cannot_be_steered(): void
    {
        $row = $this->item($this->admin);

        $response = $this->as()->post(route('platform.notifications.read', $row->id), ['tab' => 'https://evil.example', 'group' => '//evil.example', 'page' => 'javascript:alert(1)', 'redirect' => 'https://evil.example']);

        $response->assertRedirect(route('platform.notifications'));
        $this->assertStringNotContainsString('evil', $response->headers->get('Location'));
    }

    public function test_nobody_can_mark_another_admins_notification_read_or_unread(): void
    {
        $theirs = $this->item($this->other, read: true);
        $mineUnread = $this->item($this->other);

        $this->as()->post(route('platform.notifications.unread', $theirs->id))->assertNotFound();
        $this->as()->post(route('platform.notifications.read', $mineUnread->id))->assertNotFound();
        $this->as()->post(route('platform.notifications.read', 999999))->assertNotFound();
        $this->as()->post('/platform/notifications/abc/read')->assertNotFound();

        $this->assertNotNull($theirs->fresh()->read_at);
        $this->assertNull($mineUnread->fresh()->read_at);
        $this->as()->get(route('platform.notifications.read', $mineUnread->id))->assertStatus(405);
    }

    // ---- mark all, with a count ---------------------------------------------------------------------------------------------------------------------------

    public function test_mark_all_as_read_says_how_many_it_changed(): void
    {
        foreach (range(1, 3) as $i) {
            $this->item($this->admin);
        }
        $this->item($this->admin, read: true);
        $this->item($this->other);

        $page = $this->as()->get(route('platform.notifications'));
        $this->assertStringContainsString('Mark all as read (3)', preg_replace('/\s+/', ' ', $page->getContent()));

        $this->as()->post(route('platform.notifications.read-all'))->assertRedirect(route('platform.notifications'))->assertSessionHas('status', 'Marked 3 notifications as read.');
        $this->assertSame(0, $this->unreadFor($this->admin));
        $this->assertSame(1, $this->unreadFor($this->other), 'the other admin\'s are untouched');

        $this->followingRedirects()->as()->post(route('platform.notifications.read-all'))->assertSee('Nothing to mark: everything is already read.');
    }

    public function test_one_is_one_notification_in_the_message(): void
    {
        $this->item($this->admin);

        $this->as()->post(route('platform.notifications.read-all'))->assertSessionHas('status', 'Marked 1 notification as read.');
    }

    public function test_mark_all_can_be_limited_to_the_kind_being_looked_at(): void
    {
        $this->item($this->admin, 'organization.application_submitted', 'Application');
        $this->item($this->admin, 'system.maintenance_auto_reopened', 'System');
        $this->item($this->admin, 'system.maintenance_auto_reopened', 'System 2');

        $page = $this->as()->get(route('platform.notifications', ['group' => 'system']))->getContent();
        $this->assertStringContainsString('Mark all in System as read (2)', preg_replace('/\s+/', ' ', $page));

        $this->as()->post(route('platform.notifications.read-all'), ['group' => 'system'])
            ->assertRedirect(route('platform.notifications', ['group' => 'system']))->assertSessionHas('status', 'Marked 2 notifications in System as read.');

        $this->assertSame(1, $this->unreadFor($this->admin), 'the application is still unread');
        $this->assertSame('organization.application_submitted', UserNotification::query()->ownedBy($this->admin)->unread()->sole()->type);
    }

    public function test_the_bell_still_gets_json_from_the_same_route(): void
    {
        $this->item($this->admin);

        $this->as()->postJson(route('platform.notifications.read-all'))->assertOk()->assertExactJson(['marked' => 1, 'count' => 0]);
    }

    public function test_the_mark_all_button_is_off_when_nothing_is_unread(): void
    {
        $this->item($this->admin, read: true);

        $this->assertMatchesRegularExpression('/<button type="submit" disabled[^>]*>\s*Mark all as read\s*<\/button>/', $this->as()->get(route('platform.notifications'))->getContent());
    }

    // ---- the page is safe ----------------------------------------------------------------------------------------------------------------------------------------

    public function test_the_page_is_platform_admins_only_and_a_label_is_escaped(): void
    {
        $this->item($this->admin, label: '<img src=x onerror=alert(1)>');

        $html = $this->as()->get(route('platform.notifications'))->getContent();
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);

        $this->actingAs($this->admin)->withSession(['current_org_id' => $this->platform()->id, 'current_role' => 'student'])->get(route('platform.notifications'))->assertForbidden();
    }

    public function test_no_sidebar_entry_points_to_the_page(): void
    {
        $this->assertNotContains('platform.notifications', collect(config('platform-nav'))->flatMap(fn ($section) => collect($section['items'])->pluck('route'))->all());
    }
}
