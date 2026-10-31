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

        $page->assertSee('Find a page')->assertSee('Acme Berhad · Organization admin')->assertSee('Account menu', false)
            ->assertSee('Members')->assertSee('Counselors')->assertSee('Subscription')->assertSee('Terms of Use')->assertSee('All rights reserved');
        $page->assertSee('aria-current="page"', false);
        $page->assertDontSee('Session notes');
    }

    public function test_pages_not_built_yet_are_shown_as_soon_and_are_not_links(): void
    {
        [$user, $session] = $this->as(MembershipRole::OrgAdmin);

        $this->actingAs($user)->withSession($session)->get(route('org.dashboard'))->assertOk()
            ->assertSee('Reports')->assertSee('Soon')->assertDontSee(route('org.dashboard').'/reports', false);
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
}
