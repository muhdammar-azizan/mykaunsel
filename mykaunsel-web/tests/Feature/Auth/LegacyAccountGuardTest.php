<?php

namespace Tests\Feature\Auth;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The old Breeze endpoints must not be a back door around the Account page rules
 * (password for email change, audit trail, signing out other devices).
 */
class LegacyAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private array $session = [];

    private function platformAdmin(): User
    {
        $platform = Organization::create([
            'name' => 'MyKaunsel Platform',
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create();

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

    public function test_platform_admin_cannot_change_email_through_the_old_profile_endpoint(): void
    {
        $admin = $this->platformAdmin();
        $old = $admin->email;

        $this->actingAs($admin)->withSession($this->session)
            ->patch('/profile', ['name' => 'Hacked', 'email' => 'attacker@example.com'])
            ->assertForbidden();

        $admin->refresh();
        $this->assertSame($old, $admin->email);
        $this->assertNotSame('Hacked', $admin->name);
    }

    public function test_platform_admin_cannot_change_password_through_the_old_endpoint(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->withSession($this->session)
            ->put('/password', ['current_password' => 'password', 'password' => 'Brand#New9pass', 'password_confirmation' => 'Brand#New9pass'])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.password_changed']);
    }

    public function test_platform_admin_cannot_delete_the_account_through_the_old_endpoint(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->withSession($this->session)
            ->delete('/profile', ['password' => 'password'])
            ->assertForbidden();

        $this->assertNotNull($admin->fresh());
    }

    public function test_old_profile_page_sends_a_platform_admin_to_the_account_page(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->withSession($this->session)
            ->get('/profile')
            ->assertRedirect(route('platform.account'));
    }

    public function test_the_block_applies_even_when_browsing_in_another_role(): void
    {
        $admin = $this->platformAdmin();
        $org = Organization::create([
            'name' => 'Universiti Contoh',
            'org_type' => OrgType::University,
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Active,
        ]);
        Membership::create(['user_id' => $admin->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        // Switched to a student context: still the same person, still locked out of the weak endpoints.
        $this->actingAs($admin)->withSession(['current_org_id' => $org->id, 'current_role' => 'student'])
            ->patch('/profile', ['name' => 'x', 'email' => 'attacker@example.com'])
            ->assertForbidden();
    }

    public function test_ordinary_members_are_unaffected(): void
    {
        $this->platformAdmin();
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', ['current_password' => 'password', 'password' => 'Brand#New9pass', 'password_confirmation' => 'Brand#New9pass'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Brand#New9pass', $user->fresh()->password));
    }
}
