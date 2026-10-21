<?php

namespace Tests\Feature\Account;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyNewEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProfileTabTest extends TestCase
{
    use RefreshDatabase;

    private array $session = [];

    private function admin(array $attributes = []): User
    {
        $platform = Organization::firstOrCreate(['name' => 'MyKaunsel Platform'], [
            'org_type' => OrgType::Platform,
            'access_model' => AccessModel::Open,
            'subscription_status' => OrgStatus::Active,
        ]);

        $admin = User::factory()->create($attributes + ['name' => 'Ammar Azizan', 'password' => 'Secret#123']);

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

    private function as(User $user)
    {
        return $this->actingAs($user)->withSession($this->session);
    }

    public function test_profile_page_renders_with_details(): void
    {
        $admin = $this->admin(['last_login_at' => now(), 'last_login_ip' => '203.0.113.9']);

        $this->as($admin)->get(route('platform.account'))
            ->assertOk()
            ->assertSee('Profile photo')
            ->assertSee('Platform Admin')
            ->assertSee('203.0.113.9')
            ->assertSee($admin->email);
    }

    public function test_non_platform_admin_cannot_open_account_page(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->withSession(['current_org_id' => $this->session['current_org_id'], 'current_role' => 'student'])
            ->get(route('platform.account'))->assertForbidden();
    }

    public function test_name_and_timezone_can_be_updated(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patch(route('platform.account.update'), ['name' => 'Ammar A.', 'timezone' => 'Asia/Singapore'])
            ->assertRedirect(route('platform.account'))
            ->assertSessionHas('status');

        $admin->refresh();
        $this->assertSame('Ammar A.', $admin->name);
        $this->assertSame('Asia/Singapore', $admin->timezone);
        $this->assertSame('AA', $admin->initials);
    }

    public function test_name_is_required_and_timezone_must_be_valid(): void
    {
        $admin = $this->admin();

        $this->as($admin)->patch(route('platform.account.update'), ['name' => '', 'timezone' => 'Mars/Olympus'])
            ->assertSessionHasErrors(['name', 'timezone']);
    }

    public function test_request_cannot_change_another_users_account(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['name' => 'Other Person']);

        // user_id / email / id in the payload are ignored: only the signed-in user is touched.
        $this->as($admin)->patch(route('platform.account.update'), [
            'name' => 'Changed', 'timezone' => 'UTC', 'id' => $other->id, 'user_id' => $other->id, 'email' => 'hacker@example.com',
        ]);

        $this->assertSame('Other Person', $other->fresh()->name);
        $this->assertNotSame('hacker@example.com', $admin->fresh()->email);
        $this->assertSame('Changed', $admin->fresh()->name);
    }

    public function test_avatar_upload_replaces_old_file_and_is_audited(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->as($admin)->post(route('platform.account.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png', 800, 500)])
            ->assertRedirect(route('platform.account'));

        $first = $admin->fresh()->avatar_path;
        Storage::disk('public')->assertExists($first);
        $this->assertStringEndsWith('.jpg', $first);
        $this->assertNotNull($admin->fresh()->avatar_url);

        $this->as($admin)->post(route('platform.account.avatar.store'), ['avatar' => UploadedFile::fake()->image('b.jpg', 600, 600)]);

        $second = $admin->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->assertSame(2, AuditLog::where('action', 'account.avatar_changed')->count());
    }

    public function test_avatar_rejects_wrong_type_and_oversize(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->as($admin)->post(route('platform.account.avatar.store'), ['avatar' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->as($admin)->post(route('platform.account.avatar.store'), ['avatar' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($admin->fresh()->avatar_path);
    }

    public function test_avatar_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->as($admin)->post(route('platform.account.avatar.store'), ['avatar' => UploadedFile::fake()->image('a.jpg')]);
        $path = $admin->fresh()->avatar_path;

        $this->as($admin)->delete(route('platform.account.avatar.destroy'))->assertRedirect();

        $this->assertNull($admin->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_email_change_requires_current_password(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->as($admin)->post(route('platform.account.email.store'), ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrorsIn('emailChange', 'current_password');

        $this->assertNull($admin->fresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_email_change_keeps_old_email_until_verified(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $old = $admin->email;

        $this->as($admin)->post(route('platform.account.email.store'), ['email' => 'New@Example.com', 'current_password' => 'Secret#123'])
            ->assertRedirect(route('platform.account'));

        $admin->refresh();
        $this->assertSame($old, $admin->email, 'old email must stay active');
        $this->assertSame('new@example.com', $admin->pending_email);
        Notification::assertSentOnDemand(VerifyNewEmailAddress::class);
        Notification::assertSentTo($admin, EmailChangeRequested::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.email_change_requested', 'actor_user_id' => $admin->id]);

        $url = URL::temporarySignedRoute('platform.account.email.verify', now()->addHour(), ['user' => $admin->id, 'hash' => sha1('new@example.com')]);
        $this->as($admin)->get($url)->assertRedirect(route('platform.account'))->assertSessionHas('status');

        $admin->refresh();
        $this->assertSame('new@example.com', $admin->email);
        $this->assertNull($admin->pending_email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.email_changed', 'actor_user_id' => $admin->id]);
    }

    public function test_email_verification_link_needs_valid_signature_and_owner(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pending_email' => 'new@example.com'])->save();
        $other = User::factory()->create();
        $url = URL::temporarySignedRoute('platform.account.email.verify', now()->addHour(), ['user' => $admin->id, 'hash' => sha1('new@example.com')]);

        // Tampered signature
        $this->as($admin)->get($url.'x')->assertForbidden();

        // Link opened by someone else's session (fresh browser session, as in real life)
        $this->flushSession();
        $otherAdmin = $this->admin(['name' => 'Second Admin']);
        $this->as($otherAdmin)->get($url)->assertForbidden();

        $this->assertNotSame('new@example.com', $admin->fresh()->email);
    }

    public function test_email_must_be_unique_and_different(): void
    {
        $admin = $this->admin();
        $taken = User::factory()->create();

        $this->as($admin)->post(route('platform.account.email.store'), ['email' => $taken->email, 'current_password' => 'Secret#123'])
            ->assertSessionHasErrorsIn('emailChange', 'email');
        $this->as($admin)->post(route('platform.account.email.store'), ['email' => $admin->email, 'current_password' => 'Secret#123'])
            ->assertSessionHasErrorsIn('emailChange', 'email');
    }

    public function test_pending_email_can_be_cancelled(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pending_email' => 'new@example.com'])->save();

        $this->as($admin)->delete(route('platform.account.email.destroy'))->assertRedirect();

        $this->assertNull($admin->fresh()->pending_email);
    }
}
