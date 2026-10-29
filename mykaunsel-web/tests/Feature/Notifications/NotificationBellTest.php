<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/**
 * The bell, its panel and the redirect route: who may use them, what the count says, what the panel shows (and only when opened), that a
 * click can only go where the type registry says, and that nobody can reach another admin's notifications.
 */
class NotificationBellTest extends TestCase
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
        $this->admin = $this->makeAdmin('Aminah binti Yusof');
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

    /** A notification for $user, written directly so its time and state are exactly what the test needs. */
    private function item(User $user, string $type = 'organization.application_submitted', ?int $targetId = null, string $label = 'Some Name', ?string $at = null, bool $read = false, int $count = 1): UserNotification
    {
        static $n = 0;
        $targetType = app(\App\Support\Inbox\NotificationRegistry::class)->get($type)->targetType;

        return UserNotification::create([
            'recipient_user_id' => $user->id, 'type' => $type, 'target_type' => $targetType, 'target_id' => $targetType ? ($targetId ?? $this->org('university', 'pending')->id) : null,
            'label' => $label, 'occurrence_key' => 'k'.++$n, 'count' => $count, 'occurred_at' => $at ?? now(), 'read_at' => $read ? now() : null,
        ]);
    }

    /** The HTML of the one list item that contains $label. */
    private function itemHtml(string $html, string $label): string
    {
        foreach (explode('<li>', $html) as $piece) {
            if (str_contains($piece, $label)) {
                return $piece;
            }
        }

        $this->fail("No item with {$label}");
    }

    private function unreadFor(User $user): int
    {
        return UserNotification::query()->ownedBy($user)->unread()->count();
    }

    // ---- access --------------------------------------------------------------------------------------------------------------------

    public function test_only_a_platform_admin_gets_bell_data_and_actions(): void
    {
        $row = $this->item($this->admin);
        $student = ['current_org_id' => $this->platform()->id, 'current_role' => 'student'];

        foreach ([
            ['get', route('platform.notifications.count')],
            ['get', route('platform.notifications.panel')],
            ['get', route('platform.notifications')],
            ['post', route('platform.notifications.read-all')],
            ['post', route('platform.notifications.open', $row->id)],
        ] as [$method, $url]) {
            $response = $this->actingAs($this->admin)->withSession($student)->$method($url, [], ['Accept' => 'application/json']);
            $response->assertForbidden();
            $this->assertStringNotContainsString('"count"', $response->getContent(), "no bell data for {$url}");

        }

        $this->assertNull($row->fresh()->read_at, 'nothing was changed');
    }

    public function test_a_guest_asking_by_script_gets_json_401_not_a_login_page(): void
    {
        $row = $this->item($this->admin);

        foreach ([['getJson', route('platform.notifications.count')], ['getJson', route('platform.notifications.panel')], ['postJson', route('platform.notifications.read-all')], ['postJson', route('platform.notifications.open', $row->id)]] as [$method, $url]) {
            $this->$method($url)->assertUnauthorized()->assertJsonMissing(['count']);
        }

        $this->get(route('platform.notifications'))->assertRedirect();
        $this->assertNull($row->fresh()->read_at);
    }

    public function test_the_state_changing_routes_are_not_reachable_by_get(): void
    {
        $row = $this->item($this->admin);

        $this->as()->get(route('platform.notifications.open', $row->id))->assertStatus(405);
        $this->as()->get(route('platform.notifications.read-all'))->assertStatus(405);
        $this->assertNull($row->fresh()->read_at);
    }

    // ---- the count and the badge ----------------------------------------------------------------------------------------------------------

    public function test_the_count_endpoint_gives_only_my_unread_number(): void
    {
        $this->item($this->admin);
        $this->item($this->admin);
        $this->item($this->admin, read: true);
        $this->item($this->other);

        $this->as()->getJson(route('platform.notifications.count'))->assertOk()->assertExactJson(['count' => 2])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_badge_hides_at_zero_shows_the_number_and_caps_at_nine_plus(): void
    {
        $this->as()->get(route('platform.notifications'))->assertOk()
            ->assertSee('aria-label="Notifications"', false)
            ->assertDontSee('unread"', false);
        $this->assertMatchesRegularExpression('/<span data-bell-badge[^>]*class="hidden absolute[^>]*><\/span>/', $this->as()->get(route('platform.notifications'))->getContent(), 'hidden at zero, and empty');

        foreach (range(1, 3) as $i) {
            $this->item($this->admin);
        }
        $html = $this->as()->get(route('platform.notifications'))->getContent();
        $this->assertStringContainsString('aria-label="Notifications, 3 unread"', $html, 'the name includes the count');
        $this->assertMatchesRegularExpression('/<span data-bell-badge[^>]*class="  ?absolute[^>]*>3<\/span>/', preg_replace('/\s+/', ' ', $html) === $html ? $html : $html, 'the number is in the badge itself');
        $this->assertStringNotContainsString('9+', $html);

        foreach (range(1, 6) as $i) {
            $this->item($this->admin);                                                  // 9 unread: still a number
        }
        $this->assertStringContainsString('aria-label="Notifications, 9 unread"', $this->as()->get(route('platform.notifications'))->getContent());

        $this->item($this->admin);                                                      // 10
        $html = $this->as()->get(route('platform.notifications'))->getContent();
        $this->assertStringContainsString('aria-label="Notifications, 9+ unread"', $html);
        $this->assertMatchesRegularExpression('/data-bell-badge[^>]*>9\+<\/span>/', $html);

        foreach (range(1, 20) as $i) {
            $this->item($this->admin);                                                  // 30: still 9+
        }
        $this->assertStringContainsString('Notifications, 9+ unread', $this->as()->get(route('platform.notifications'))->getContent());
    }

    public function test_every_platform_page_carries_the_bell_with_its_wiring(): void
    {
        $page = $this->as()->get(route('platform.dashboard'))->assertOk();

        // The wiring is JSON in the attribute (slashes escaped), so compare with the escaping taken off.
        $plain = str_replace('\\', '', html_entity_decode($page->getContent()));
        foreach (['count', 'panel', 'read-all'] as $name) {
            $this->assertStringContainsString(route('platform.notifications.'.$name), $plain);
        }

        $page->assertSee('x-data="notificationBell(', false)
            ->assertSee('View all notifications')
            ->assertSee('Mark all as read')
            ->assertSee('aria-haspopup="dialog"', false)
            ->assertSee('aria-controls="notification-panel"', false)
            ->assertSee('role="dialog"', false);
    }

    // ---- the panel is loaded only when opened -------------------------------------------------------------------------------------------------------------

    public function test_a_normal_page_load_reads_only_the_unread_count_never_the_list(): void
    {
        $this->item($this->admin, label: 'Visible Only In The Panel');
        $this->item($this->admin, label: 'Another Hidden Label');

        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = strtolower($query->sql);
        });

        $page = $this->as()->get(route('platform.dashboard'))->assertOk();

        $touching = array_values(array_filter($sql, fn ($s) => str_contains($s, 'user_notifications')));
        $this->assertCount(1, $touching, 'one query about notifications');
        $this->assertStringContainsString('count(*)', $touching[0], 'and it is the count');
        $page->assertDontSee('Visible Only In The Panel')->assertDontSee('Another Hidden Label')->assertSee('Notifications, 2 unread');
    }

    public function test_the_panel_shows_the_latest_eight_newest_first_with_the_unread_total_in_a_header(): void
    {
        foreach (range(1, 12) as $i) {
            $this->item($this->admin, label: "Item {$i}", at: Carbon::parse(self::NOW)->subMinutes(30 - $i)->toDateTimeString());
        }
        $this->item($this->other, label: 'Other Admin Item');

        $response = $this->as()->get(route('platform.notifications.panel'))->assertOk()->assertHeader('X-Unread-Count', '12');
        $html = $response->getContent();

        preg_match_all('/Item (\d+)/', $html, $found);
        $this->assertSame(['12', '11', '10', '9', '8', '7', '6', '5'], $found[1], 'the latest 8, newest first');
        $this->assertStringNotContainsString('Other Admin Item', $html);
    }

    public function test_the_panel_groups_today_and_earlier_in_the_admins_own_timezone(): void
    {
        // Now is 20:00 on 20 Oct in Kuala Lumpur. 15:00 UTC on 19 Oct is 23:00 on 19 Oct there (Earlier);
        // 17:00 UTC on 19 Oct is 01:00 on 20 Oct there, which is Today even though it is "yesterday" in UTC.
        $this->item($this->admin, label: 'Just Now', at: '2026-10-20 11:30:00');
        $this->item($this->admin, label: 'Past Midnight Local', at: '2026-10-19 17:00:00');
        $this->item($this->admin, label: 'Late Yesterday Local', at: '2026-10-19 15:00:00');
        $this->item($this->admin, label: 'Last Week', at: '2026-10-12 09:00:00');

        $html = $this->as()->get(route('platform.notifications.panel'))->getContent();

        $this->assertLessThan(strpos($html, 'Earlier'), strpos($html, 'Today'));
        $today = substr($html, strpos($html, 'Today'), strpos($html, 'Earlier') - strpos($html, 'Today'));
        $earlier = substr($html, strpos($html, 'Earlier'));
        $this->assertStringContainsString('Just Now', $today);
        $this->assertStringContainsString('Past Midnight Local', $today);
        $this->assertStringNotContainsString('Late Yesterday Local', $today);
        $this->assertStringContainsString('Late Yesterday Local', $earlier);
        $this->assertStringContainsString('Last Week', $earlier);

        // A different timezone moves the same notifications between the groups.
        $this->admin->forceFill(['timezone' => 'UTC'])->save();
        $html = $this->as($this->admin->fresh())->get(route('platform.notifications.panel'))->getContent();
        $today = substr($html, strpos($html, 'Today'), strpos($html, 'Earlier') - strpos($html, 'Today'));
        $this->assertStringContainsString('Just Now', $today);
        $this->assertStringNotContainsString('Past Midnight Local', $today, 'in UTC that one is yesterday');
    }

    public function test_an_item_shows_title_label_time_with_the_full_time_on_hover(): void
    {
        $this->item($this->admin, label: 'Universiti Contoh', at: '2026-10-20 09:00:00');

        $html = $this->as()->get(route('platform.notifications.panel'))->getContent();

        $this->assertStringContainsString('New organization application', $html);
        $this->assertStringContainsString('Universiti Contoh', $html);
        $this->assertStringContainsString('3 hours ago', $html);
        $this->assertStringContainsString('title="20 Oct 2026, 5:00 PM"', $html, 'the full time, in the admin\'s timezone (UTC+8)');
        $this->assertStringContainsString('datetime="2026-10-20T17:00:00+08:00"', $html);
    }

    public function test_unread_is_a_dot_plus_bold_text_plus_words_never_colour_alone(): void
    {
        $this->item($this->admin, label: 'Unread One');
        $this->item($this->admin, label: 'Read One', read: true);

        $html = $this->as()->get(route('platform.notifications.panel'))->getContent();
        $unread = $this->itemHtml($html, 'Unread One');
        $read = $this->itemHtml($html, 'Read One');

        $this->assertStringContainsString('bg-brand-blue', $unread);
        $this->assertStringContainsString('<span class="sr-only">Unread. </span>', $unread);
        $this->assertStringContainsString('font-bold', $unread);
        $this->assertStringNotContainsString('Unread. ', $read);
        $this->assertStringContainsString('font-medium', $read);
        $this->assertSame(1, substr_count($html, 'Unread. '));
    }

    public function test_a_target_already_reviewed_is_dimmed_and_says_so_and_a_deleted_one_says_it_is_gone(): void
    {
        $waiting = $this->org('university', 'pending');
        $decided = $this->org('university', 'active');
        $this->item($this->admin, targetId: $waiting->id, label: 'Waiting Org');
        $this->item($this->admin, targetId: $decided->id, label: 'Decided Org');
        $this->item($this->admin, targetId: 999999, label: 'Deleted Org');

        $html = $this->as()->get(route('platform.notifications.panel'))->getContent();
        $section = fn (string $label) => $this->itemHtml($html, $label);

        $this->assertStringNotContainsString('Already reviewed', $section('Waiting Org'));
        $this->assertStringNotContainsString('opacity-60', $section('Waiting Org'));
        $this->assertStringContainsString('Already reviewed', $section('Decided Org'));
        $this->assertStringContainsString('opacity-60', $section('Decided Org'));
        $this->assertStringContainsString('No longer available', $section('Deleted Org'));
        $this->assertSame(1, substr_count($html, 'Already reviewed'));
    }

    public function test_the_handled_state_does_not_cost_a_query_per_item(): void
    {
        $queries = function (int $perKind) {
            UserNotification::query()->delete();
            foreach (range(1, $perKind) as $i) {
                $this->item($this->admin, 'organization.application_submitted', $this->org('university', 'active')->id);
                $this->item($this->admin, 'counselor.application_submitted', $this->counselor('independent', 'pending')->id);
            }
            $count = 0;
            DB::listen(function () use (&$count) {
                $count++;
            });
            $this->as()->get(route('platform.notifications.panel'))->assertOk();

            return $count;
        };

        $queries(1);                                                       // warm-up (first-use work)
        $this->assertSame($queries(2), $queries(4), 'the same number of queries for 4 or for 8 items');
    }

    public function test_an_empty_panel_says_all_caught_up(): void
    {
        $this->as()->get(route('platform.notifications.panel'))->assertOk()->assertSee("You're all caught up.", false)->assertHeader('X-Unread-Count', '0');
    }

    public function test_a_stored_label_is_escaped_everywhere_it_is_shown(): void
    {
        $this->item($this->admin, label: '<script>alert("x")</script><img src=x onerror=alert(1)>');

        foreach ([route('platform.notifications.panel'), route('platform.notifications')] as $url) {
            $html = $this->as()->get($url)->getContent();
            $this->assertStringNotContainsString('<script>alert("x")', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        }
    }

    public function test_a_coalesced_item_says_how_many_attempts(): void
    {
        $this->item($this->admin, 'counselor.kb_duplicate_flagged', $this->counselor('independent', 'approved')->id, 'Counselor Name', count: 3);

        $this->as()->get(route('platform.notifications.panel'))->assertSee('Counselor Name · 3 attempts', false);
    }

    // ---- mark all ----------------------------------------------------------------------------------------------------------------------------------

    public function test_mark_all_as_read_changes_only_my_own_and_says_how_many(): void
    {
        $this->item($this->admin);
        $this->item($this->admin);
        $this->item($this->admin, read: true);
        $this->item($this->other);

        $this->as()->postJson(route('platform.notifications.read-all'))->assertOk()->assertExactJson(['marked' => 2, 'count' => 0]);

        $this->assertSame(0, $this->unreadFor($this->admin));
        $this->assertSame(1, $this->unreadFor($this->other), 'the other admin\'s are untouched');
        $this->as()->postJson(route('platform.notifications.read-all'))->assertExactJson(['marked' => 0, 'count' => 0]);
    }

    // ---- the click: mark read and go through the registry ----------------------------------------------------------------------------------------------------

    public function test_a_click_marks_it_read_and_goes_to_the_page_the_registry_defines(): void
    {
        $organization = $this->org('university', 'pending');
        $row = $this->item($this->admin, targetId: $organization->id);

        $this->as()->post(route('platform.notifications.open', $row->id))->assertRedirect(route('platform.organizations.show', $organization));

        $this->assertNotNull($row->fresh()->read_at);
    }

    public function test_each_type_goes_where_its_definition_says(): void
    {
        $profile = $this->counselor('independent', 'pending');
        $cases = [
            ['counselor.application_submitted', $profile->id, route('platform.counselors.show', $profile)],
            ['counselor.kb_duplicate_flagged', $profile->id, route('platform.counselors.show', $profile)],
            ['admin.invitation_accepted', $this->other->id, route('platform.admins')],
        ];

        foreach ($cases as [$type, $target, $expected]) {
            $row = $this->item($this->admin, $type, $target);
            $this->as()->post(route('platform.notifications.open', $row->id))->assertRedirect($expected);
        }

        $system = $this->item($this->admin, 'system.maintenance_auto_reopened');
        $this->as()->post(route('platform.notifications.open', $system->id))->assertRedirect(route('platform.registration'));
    }

    public function test_nothing_stored_can_steer_the_redirect_anywhere_else(): void
    {
        $organization = $this->org('university', 'pending');
        $row = $this->item($this->admin, targetId: $organization->id, label: 'https://evil.example/phish');
        $row->forceFill(['occurrence_key' => '//evil.example', 'target_type' => 'https://evil.example'])->save();

        $response = $this->as()->post(route('platform.notifications.open', $row->id), ['redirect' => 'https://evil.example', 'url' => 'https://evil.example', 'next' => '//evil.example']);

        $response->assertRedirect(route('platform.organizations.show', $organization));
        $this->assertStringNotContainsString('evil', $response->headers->get('Location'));
    }

    public function test_a_deleted_target_lands_on_the_notifications_page_with_a_short_message(): void
    {
        $row = $this->item($this->admin, targetId: 999999);

        $this->as()->post(route('platform.notifications.open', $row->id))->assertRedirect(route('platform.notifications'))->assertSessionHas('notice', 'That item no longer exists.');
        $this->assertNotNull($row->fresh()->read_at, 'it is still marked read');

        $this->followingRedirects()->as()->post(route('platform.notifications.open', $this->item($this->admin, targetId: 999999)->id))->assertSee('That item no longer exists.');
    }

    public function test_an_old_row_of_a_type_that_no_longer_exists_does_not_break_the_click(): void
    {
        $row = $this->item($this->admin);
        $row->forceFill(['type' => 'retired.type'])->save();

        $this->as()->post(route('platform.notifications.open', $row->id))->assertRedirect(route('platform.notifications'));
        $this->as()->get(route('platform.notifications.panel'))->assertOk()->assertSee('Notification');
    }

    public function test_an_already_reviewed_target_is_still_opened(): void
    {
        $decided = $this->org('university', 'active');
        $row = $this->item($this->admin, targetId: $decided->id);

        $this->as()->post(route('platform.notifications.open', $row->id))->assertRedirect(route('platform.organizations.show', $decided));
    }

    // ---- nobody can reach another admin's ------------------------------------------------------------------------------------------------------------------

    public function test_another_admins_notification_cannot_be_opened_or_marked_by_a_crafted_request(): void
    {
        $theirs = $this->item($this->other);

        $this->as()->post(route('platform.notifications.open', $theirs->id))->assertNotFound();
        $this->as()->post(route('platform.notifications.open', 999999))->assertNotFound();
        $this->as()->post('/platform/notifications/abc/open')->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at, 'not marked');

        // read-all with a crafted body cannot reach them either.
        $this->as()->postJson(route('platform.notifications.read-all'), ['recipient_user_id' => $this->other->id, 'user' => $this->other->id, 'ids' => [$theirs->id]])->assertOk();
        $this->assertNull($theirs->fresh()->read_at);
        $this->assertSame(1, $this->unreadFor($this->other));
    }

    public function test_the_panel_and_the_page_never_list_another_admins_items(): void
    {
        $this->item($this->other, label: 'Only For The Other Admin');
        $this->item($this->admin, label: 'Mine');

        foreach ([route('platform.notifications.panel'), route('platform.notifications')] as $url) {
            $this->as()->get($url)->assertSee('Mine')->assertDontSee('Only For The Other Admin');
        }
    }

    public function test_opening_and_marking_are_not_audited(): void
    {
        $row = $this->item($this->admin);
        $before = \App\Models\AuditLog::count();

        $this->as()->get(route('platform.notifications.panel'));
        $this->as()->postJson(route('platform.notifications.read-all'));
        $this->as()->post(route('platform.notifications.open', $row->id));
        $this->as()->get(route('platform.notifications'));

        $this->assertSame($before, \App\Models\AuditLog::count());
    }

    // ---- limits ---------------------------------------------------------------------------------------------------------------------------------------------------

    public function test_the_endpoints_are_rate_limited(): void
    {
        foreach (range(1, 120) as $i) {
            $this->as()->getJson(route('platform.notifications.count'))->assertOk();
        }
        $this->as()->getJson(route('platform.notifications.count'))->assertStatus(429);

        foreach (range(1, 30) as $i) {
            $this->as($this->other)->postJson(route('platform.notifications.read-all'))->assertOk();
        }
        $this->as($this->other)->postJson(route('platform.notifications.read-all'))->assertStatus(429);
    }

    // ---- the full page (basic, completed in the next step) -------------------------------------------------------------------------------------------------

    public function test_the_page_lists_25_at_a_time_and_has_an_empty_state(): void
    {
        $this->as()->get(route('platform.notifications'))->assertOk()->assertSee("You're all caught up. Nothing has happened yet.", false);

        foreach (range(1, 30) as $i) {
            $this->item($this->admin, label: sprintf('Entry %02d', $i), at: Carbon::parse(self::NOW)->subMinutes(60 - $i)->toDateTimeString());
        }

        $first = $this->as()->get(route('platform.notifications'))->assertOk();
        $this->assertSame(25, substr_count($first->getContent(), 'Entry '));
        $this->assertSame(5, substr_count($this->as()->get(route('platform.notifications', ['page' => 2]))->getContent(), 'Entry '));
    }
}
