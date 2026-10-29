<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdminSession(): array
    {
        $platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create(['name' => 'Platform Administrator']);

        Membership::create([
            'user_id' => $admin->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        return [$admin, ['current_org_id' => $platform->id, 'current_role' => 'platform_admin']];
    }

    public function test_platform_admin_sees_dashboard_with_pending_counts(): void
    {
        [$admin, $session] = $this->platformAdminSession();

        // Waiting 6 days: from 5 days on it is something that needs attention and is listed by name.
        Organization::create([
            'name' => 'Universiti Contoh',
            'org_type' => OrgType::University,
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Pending,
        ])->forceFill(['submitted_at' => now()->subDays(6)])->saveQuietly();

        $this->actingAs($admin)->withSession($session)
            ->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('Good', false)
            ->assertSee('Pending organizations')
            ->assertSee('Universiti Contoh');
    }

    public function test_generic_dashboard_redirects_platform_admin_to_platform_dashboard(): void
    {
        [$admin, $session] = $this->platformAdminSession();

        $this->actingAs($admin)->withSession($session)
            ->get(route('dashboard'))
            ->assertRedirect(route('platform.dashboard'));
    }

    public function test_non_platform_admin_is_forbidden(): void
    {
        [$admin, $session] = $this->platformAdminSession();

        // The admin really belongs to this organization, but is acting as a student in it: not allowed into the Platform area.
        $this->actingAs($admin)
            ->withSession(['current_org_id' => $session['current_org_id'], 'current_role' => 'student'])
            ->get(route('platform.dashboard'))
            ->assertForbidden();
    }

    public function test_other_platform_pages_render_in_the_platform_layout(): void
    {
        [$admin, $session] = $this->platformAdminSession();

        foreach (['platform.organizations', 'platform.counselors', 'platform.complaints'] as $route) {
            $this->actingAs($admin)->withSession($session)
                ->get(route($route))
                ->assertOk()
                ->assertSee('Log out');
        }
    }
}
