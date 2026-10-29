<?php

namespace Tests\Feature\Notifications;

use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Inbox\NotificationRegistry;
use Database\Seeders\NotificationDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Platform\AnalyticsFixtures;
use Tests\TestCase;

/** The demo data for the bell: a mix of read and unread across every group, a reviewed item, a counted duplicate, and enough for two pages. */
class NotificationDemoSeederTest extends TestCase
{
    use AnalyticsFixtures;
    use RefreshDatabase;

    private function admin(string $name, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => $this->platform()->id, 'role' => MembershipRole::PlatformAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    public function test_every_active_admin_gets_a_full_mix_and_nobody_else_does(): void
    {
        $one = $this->admin('One');
        $two = $this->admin('Two');
        $removed = $this->admin('Removed', MembershipStatus::Offboarded);

        $this->seed(NotificationDemoSeeder::class);

        $registry = app(NotificationRegistry::class);

        foreach ([$one, $two] as $admin) {
            $rows = UserNotification::query()->ownedBy($admin)->get();
            $this->assertGreaterThan(25, $rows->count(), 'more than one page');
            $this->assertTrue($rows->contains(fn ($r) => $r->read_at === null) && $rows->contains(fn ($r) => $r->read_at !== null), 'read and unread');

            $groups = $rows->map(fn ($r) => $registry->get($r->type)->group)->unique()->sort()->values()->all();
            $this->assertSame(['admins', 'applications', 'complaints', 'lkm', 'system'], $groups);

            $this->assertSame(3, $rows->firstWhere('type', 'counselor.kb_duplicate_flagged')->count, 'a counted duplicate');

            $states = $registry->states($rows);
            $this->assertContains('handled', $states, 'at least one already reviewed');
            $this->assertContains('open', $states);
            $this->assertNotContains('gone', $states, 'every item points at something that exists');
        }

        $this->assertSame(0, UserNotification::query()->ownedBy($removed)->count());
    }

    public function test_it_does_nothing_the_second_time_and_marks_everything_as_demo(): void
    {
        $this->admin('One');

        $this->seed(NotificationDemoSeeder::class);
        $count = UserNotification::count();
        $this->seed(NotificationDemoSeeder::class);

        $this->assertSame($count, UserNotification::count());
        $this->assertSame(0, User::query()->where('name', 'like', 'Demo Notifications%')->where('email', 'not like', '%@example.test')->count());
    }

    public function test_with_no_admin_it_adds_nothing_and_does_not_fail(): void
    {
        $this->seed(NotificationDemoSeeder::class);

        $this->assertSame(0, UserNotification::count());
    }

    public function test_the_pages_open_with_the_demo_data(): void
    {
        $admin = $this->admin('One');
        $this->seed(NotificationDemoSeeder::class);
        $session = ['current_org_id' => $this->platform()->id, 'current_role' => 'platform_admin'];

        $this->actingAs($admin)->withSession($session)->get(route('platform.notifications'))->assertOk()->assertSee('Already reviewed')->assertSee('3 attempts');
        $this->actingAs($admin)->withSession($session)->get(route('platform.notifications', ['page' => 2]))->assertOk();
        $this->actingAs($admin)->withSession($session)->get(route('platform.notifications.panel'))->assertOk()->assertSee('Today');
    }
}
