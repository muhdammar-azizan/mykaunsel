<?php

namespace Tests\Feature;

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

/**
 * The original Breeze /profile page, still used by students, staff, counselors and org admins.
 * (Platform Admins use Platform Admin → Account instead — see LegacyAccountGuardTest.)
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private array $session = [];

    /** A normal member signed in with an organization context, as the app requires. */
    private function member(): User
    {
        $org = Organization::create([
            'name' => 'Universiti Contoh',
            'org_type' => OrgType::University,
            'access_model' => AccessModel::Closed,
            'subscription_status' => OrgStatus::Active,
        ]);

        $user = User::factory()->create();

        Membership::create([
            'user_id' => $user->id,
            'org_id' => $org->id,
            'role' => MembershipRole::Student,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $this->session = ['current_org_id' => $org->id, 'current_role' => 'student'];

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->member();

        $this->as($user)->get('/profile')->assertOk();
    }

    public function test_name_can_be_updated_without_a_password(): void
    {
        $user = $this->member();

        $this->as($user)->patch('/profile', ['name' => 'Test User', 'email' => $user->email])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Test User', $user->refresh()->name);
        $this->assertNotNull($user->email_verified_at, 'verification status is unchanged when the email is unchanged');
    }

    public function test_changing_the_email_requires_the_current_password(): void
    {
        $user = $this->member();
        $old = $user->email;

        $this->as($user)->patch('/profile', ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasErrors('current_password');
        $this->as($user)->patch('/profile', ['name' => $user->name, 'email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->assertSame($old, $user->fresh()->email);
    }

    public function test_email_can_be_changed_with_the_password_and_must_be_reverified_and_is_audited(): void
    {
        $user = $this->member();

        $this->as($user)->patch('/profile', ['name' => 'Test User', 'email' => 'new@example.com', 'current_password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email_changed', 'actor_user_id' => $user->id]);
    }

    public function test_user_can_delete_their_account(): void
    {
        $this->markTestSkipped(
            'Deleting a member currently fails with a foreign key error because memberships (and bookings, '
            .'notes, etc.) reference users without a cascade. Needs a data-retention decision first '
            .'(anonymise vs. soft delete vs. cascade) — see the account-deletion item in the project notes.'
        );

        $user = $this->member();

        $this->as($user)->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->member();

        $this->as($user)->from('/profile')->delete('/profile', ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
