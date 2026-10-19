<?php

namespace Tests\Feature\Account;

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

class NotificationsTabTest extends TestCase
{
    use RefreshDatabase;

    private array $session = [];

    private function admin(string $name = 'Ammar Azizan'): User
    {
        $platform = Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], [
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create(['name' => $name]);

        Membership::create([
            'user_id' => $admin->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->session = ['current_org_id' => $platform->id, 'current_role' => 'platform_admin'];

        return $admin;
    }

    public function test_page_lists_all_four_toggles_on_by_default(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->withSession($this->session)
            ->get(route('platform.account.notifications'))
            ->assertOk()
            ->assertSee('New organization application')
            ->assertSee('New counselor application')
            ->assertSee('Escalated complaint')
            ->assertSee('Weekly platform summary');

        $this->assertStringNotContainsString('on: false', $response->getContent());
    }

    public function test_toggle_is_saved_and_persists_after_reload(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'weekly_summary', 'enabled' => false])
            ->assertOk()
            ->assertJson(['saved' => true, 'key' => 'weekly_summary', 'enabled' => false]);

        $admin->refresh();
        $this->assertFalse($admin->wantsNotification('weekly_summary'));
        $this->assertTrue($admin->wantsNotification('escalated_complaint'));

        $this->actingAs($admin)->withSession($this->session)
            ->get(route('platform.account.notifications'))
            ->assertSee('on: false', false);

        // and back on
        $this->actingAs($admin)->withSession($this->session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'weekly_summary', 'enabled' => true])
            ->assertOk();
        $this->assertTrue($admin->fresh()->wantsNotification('weekly_summary'));
    }

    public function test_unknown_keys_and_bad_values_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession($this->session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'make_me_root', 'enabled' => true])
            ->assertStatus(422);

        $this->actingAs($admin)->withSession($this->session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'weekly_summary', 'enabled' => 'maybe'])
            ->assertStatus(422);

        $this->assertNull($admin->fresh()->notification_preferences);
    }

    public function test_changing_one_admins_preferences_never_touches_another(): void
    {
        $admin = $this->admin();
        $other = $this->admin('Second Admin');

        $this->actingAs($admin)->withSession($this->session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'escalated_complaint', 'enabled' => false, 'user_id' => $other->id]);

        $this->assertFalse($admin->fresh()->wantsNotification('escalated_complaint'));
        $this->assertTrue($other->fresh()->wantsNotification('escalated_complaint'));
    }

    public function test_non_platform_admin_cannot_use_notifications_endpoints(): void
    {
        $user = $this->admin();
        $session = ['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student'];

        $this->actingAs($user)->withSession($session)->get(route('platform.account.notifications'))->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->patchJson(route('platform.account.notifications.update'), ['key' => 'weekly_summary', 'enabled' => false])
            ->assertForbidden();
    }
}
