<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationCenter;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shared shell of the counselor and organization admin portals: header, sidebar by role, footer, the bell and its page. */
class PortalLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        $this->org = Organization::create(['name' => 'Acme Berhad', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
    }

    private function as(MembershipRole $role, string $name = 'Hana Person'): array
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => now(), 'timezone' => 'Asia/Kuala_Lumpur']);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->org->id, 'role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return [$user, ['current_org_id' => $this->org->id, 'current_role' => $role->value]];
    }

    public function test_an_organization_admin_sees_the_header_the_sidebar_of_their_role_and_the_footer(): void
    {
        [$user, $session] = $this->as(MembershipRole::OrgAdmin);

        $page = $this->actingAs($user)->withSession($session)->get(route('org.members'))->assertOk();

        $page->assertSee('Find a page')->assertSee('Acme Berhad')->assertSee('Account menu', false)
            ->assertSee('Members')->assertSee('Counselors')->assertSee('Subscription')->assertSee('Terms of Use')->assertSee('All rights reserved');
        $page->assertSee('aria-current="page"', false);
        $page->assertDontSee('Session notes');
    }

    public function test_pages_not_built_yet_are_shown_as_soon_and_are_not_links(): void
    {
        [$user, $session] = $this->as(MembershipRole::OrgAdmin);
        // Every page of the portals is built now, so a page that does not exist yet is made up to show what the sidebar does with one.
        config(['portal-nav.org_admin' => array_merge(config('portal-nav.org_admin'), [['label' => 'Pretend page', 'route' => 'org.pretend', 'icon' => 'M0 0h24v24H0z']])]);

        $this->actingAs($user)->withSession($session)->get(route('org.dashboard'))->assertOk()
            ->assertSee('Pretend page')->assertSee('Soon')->assertDontSee('org/pretend', false);
    }

    public function test_a_counselor_sees_the_counselor_sidebar(): void
    {
        [$user, $session] = $this->as(MembershipRole::Counselor, 'Dr Aisyah');

        $this->actingAs($user)->withSession($session)->get(route('counselor.dashboard'))->assertOk()
            ->assertSee('Bookings')->assertSee('Schedule')->assertSee('Session notes')->assertSee('Acme Berhad · Counselor')
            ->assertDontSee('Members');
    }

    public function test_the_bell_shows_the_unread_count_and_the_page_lists_only_my_own(): void
    {
        [$user, $session] = $this->as(MembershipRole::OrgAdmin);
        [$other] = $this->as(MembershipRole::OrgAdmin, 'Someone Else');
        $center = app(NotificationCenter::class);
        $center->notify($user, 'feed.warned', null, null);
        $center->notify($other, 'feed.warned', null, null);

        $as = fn () => $this->actingAs($user)->withSession($session);

        $as()->getJson(route('portal.notifications.count'))->assertOk()->assertJson(['count' => 1]);
        $as()->get(route('portal.notifications.panel'))->assertOk()->assertSee('You received a warning about the Feed');
        $as()->get(route('portal.notifications'))->assertOk()->assertSee('You received a warning about the Feed')->assertSee('Mark all as read');

        $as()->postJson(route('portal.notifications.read-all'))->assertOk()->assertJson(['marked' => 1, 'count' => 0]);
        $this->assertSame(1, $center->unreadCount($other));
    }

    public function test_someone_elses_notification_is_a_404_and_other_roles_are_refused(): void
    {
        [$user, $session] = $this->as(MembershipRole::OrgAdmin);
        [$other] = $this->as(MembershipRole::OrgAdmin, 'Someone Else');
        app(NotificationCenter::class)->notify($other, 'feed.warned', null, null);
        $theirs = \App\Models\UserNotification::query()->ownedBy($other)->first();

        $this->actingAs($user)->withSession($session)->post(route('portal.notifications.open', $theirs->id))->assertNotFound();

        [$employee, $employeeSession] = $this->as(MembershipRole::Employee, 'An Employee');
        $this->actingAs($employee)->withSession($employeeSession)->get(route('portal.notifications'))->assertForbidden();
    }

    public function test_a_jump_to_a_part_of_the_page_never_pushes_the_header_out_of_view(): void
    {
        // A redirect to "page#part" scrolls whatever CAN scroll around that part, the page itself included: the header went out of sight and the
        // footer rose. Two things stop it. The page shell is FIXED to the window (a fixed box adds nothing to the page's scrollable area, so
        // there is nothing for the page to scroll; "overflow" on the body does not help, it is handed to the window, which "clip" turns back into
        // "hidden"), and the panel around the sidebar and the content uses overflow:clip (never scrollable; "hidden" stays first as the fallback).
        [$user, $session] = $this->as(MembershipRole::Counselor, 'Dr Aisyah');
        $page = $this->actingAs($user)->withSession($session)->get(route('counselor.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<body class="fixed inset-0 flex flex-col overflow-hidden/', $page);
        $this->assertStringNotContainsString('<body class="flex h-screen', $page);

        $platform = file_get_contents(resource_path('views/components/platform-layout.blade.php'));
        $this->assertSame(1, substr_count($platform, 'fixed inset-0 flex flex-col overflow-hidden'), 'the page shell');
        $this->assertSame(1, substr_count($platform, 'overflow-hidden overflow-clip'), 'the panel around the sidebar and the content');
    }

    public function test_the_page_transition_is_off_by_default_so_the_menu_does_no_extra_work(): void
    {
        $this->assertFalse(config('portal.page_transition'));

        foreach ([[MembershipRole::Counselor, 'counselor.dashboard'], [MembershipRole::OrgAdmin, 'org.dashboard']] as [$role, $route]) {
            [$user, $session] = $this->as($role, 'Someone '.$role->value);

            $this->actingAs($user)->withSession($session)->get(route($route))->assertOk()
                ->assertDontSee('@view-transition', false)->assertDontSee('view-transition-name', false);
        }
    }

    public function test_switched_on_every_portal_page_keeps_the_sidebar_header_and_footer_still_when_moving_to_another_page(): void
    {
        config(['portal.page_transition' => true]);

        foreach ([[MembershipRole::Counselor, 'counselor.dashboard'], [MembershipRole::OrgAdmin, 'org.dashboard']] as [$role, $route]) {
            [$user, $session] = $this->as($role, 'Someone '.$role->value);

            $page = $this->actingAs($user)->withSession($session)->get(route($route))->assertOk();

            // The opt-in appears once, and each fixed part has its own single name (two with the same name would cancel the transition).
            $page->assertSee('@view-transition { navigation: auto; }', false);
            foreach (['portal-sidebar', 'portal-header', 'portal-footer'] as $name) {
                $this->assertSame(1, substr_count($page->getContent(), 'style="view-transition-name: '.$name.'"'), "{$role->value}: {$name}");
            }
            $page->assertSee('prefers-reduced-motion: reduce', false);
        }
    }

    public function test_the_sign_in_and_public_pages_do_not_take_part_in_the_page_transition(): void
    {
        auth()->logout();

        $this->get(route('login'))->assertOk()->assertDontSee('@view-transition', false);
        $this->get(route('terms'))->assertOk()->assertDontSee('@view-transition', false);
    }
}
