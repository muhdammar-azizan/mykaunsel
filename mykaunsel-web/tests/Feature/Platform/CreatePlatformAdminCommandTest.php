<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;
use Tests\TestCase;

/**
 * The first Platform Admin of a real server: `platform:create-admin`, and the demo seeder that must never run there.
 */
class CreatePlatformAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    private function platform(): Organization
    {
        return Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
    }

    private function existingAdmin(MembershipStatus $status = MembershipStatus::Active): User
    {
        $platform = Organization::where('org_type', OrgType::Platform->value)->first() ?? $this->platform();
        $user = User::factory()->create(['name' => 'Ammar Azizan']);
        Membership::create(['user_id' => $user->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
    }

    /** Runs the command with the usual correct answers. */
    private function run3(string $name = 'Nur Aisyah', string $email = 'Aisyah@MyKaunsel.com', string $password = self::PASSWORD, array $options = [])
    {
        return $this->artisan('platform:create-admin', $options)
            ->expectsQuestion('Name', $name)
            ->expectsQuestion('E-mail', $email)
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Repeat the password', $password);
    }

    // ---- the demo seeder -------------------------------------------------------------------------------------

    public function test_the_demo_seeder_refuses_to_run_outside_local_and_testing_and_creates_nothing(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;

            try {
                app(PlatformSeeder::class)->run();
                $this->fail("PlatformSeeder ran in {$environment}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('platform:create-admin', $e->getMessage());
            }
        }

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertSame(0, Membership::count());
    }

    public function test_the_demo_seeder_still_runs_locally_and_in_tests(): void
    {
        foreach (['testing', 'local'] as $environment) {
            $this->app['env'] = $environment;
            Membership::query()->delete();
            User::query()->delete();
            Organization::query()->delete();

            app(PlatformSeeder::class)->run();

            $this->assertSame(1, User::where('email', 'admin@mykaunsel.com')->count(), $environment);
        }
    }

    public function test_migrate_with_seed_cannot_create_the_known_admin_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            app(\Database\Seeders\DatabaseSeeder::class)->run();
            $this->fail('DatabaseSeeder ran in production');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull(User::where('email', 'admin@mykaunsel.com')->first());
        $this->assertSame(0, User::count());
    }

    // ---- creating the first admin ----------------------------------------------------------------------------

    public function test_it_creates_the_platform_organization_and_the_first_admin_who_can_sign_in(): void
    {
        $this->assertSame(0, Organization::count());

        $this->run3()->expectsOutputToContain('Platform Admin created')->assertSuccessful();

        $platform = Organization::where('org_type', OrgType::Platform->value)->sole();
        $user = User::where('email', 'aisyah@mykaunsel.com')->sole();

        $this->assertSame('Nur Aisyah', $user->name);
        $this->assertNotNull($user->email_verified_at, 'the e-mail counts as verified');
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);

        $membership = Membership::where('user_id', $user->id)->sole();
        $this->assertSame([$platform->id, MembershipRole::PlatformAdmin, MembershipStatus::Active], [$membership->org_id, $membership->role, $membership->status]);

        $this->post(route('login.store'), ['email' => 'aisyah@mykaunsel.com', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_it_uses_the_platform_organization_that_already_exists(): void
    {
        $platform = $this->platform();

        $this->run3()->assertSuccessful();

        $this->assertSame(1, Organization::where('org_type', OrgType::Platform->value)->count());
        $this->assertSame($platform->id, Membership::where('role', MembershipRole::PlatformAdmin->value)->sole()->org_id);
    }

    public function test_it_is_audited_as_a_system_action_without_the_email_or_the_password(): void
    {
        $this->run3()->assertSuccessful();

        $entry = AuditLog::where('action', 'admin.created_from_console')->sole();
        $user = User::where('email', 'aisyah@mykaunsel.com')->sole();

        $this->assertSame('system', $entry->actor_type);
        $this->assertNull($entry->actor_user_id);
        $this->assertSame([$user->id, 'Nur Aisyah', 'admins'], [$entry->target_id, $entry->target_name, $entry->category]);
        $this->assertSame(['summary' => 'First Platform Admin created from the command line', 'forced' => false], $entry->metadata);

        $row = json_encode($entry->getAttributes());
        $this->assertStringNotContainsString('aisyah@', strtolower($row));
        $this->assertStringNotContainsString(self::PASSWORD, $row);
        $this->assertStringNotContainsString($user->password, $row);
    }

    public function test_the_password_is_asked_at_a_hidden_prompt_and_never_printed(): void
    {
        $this->run3()->doesntExpectOutputToContain(self::PASSWORD)->assertSuccessful();
    }

    public function test_the_password_cannot_be_given_as_an_argument_or_option(): void
    {
        foreach ([['password' => self::PASSWORD], ['--password' => self::PASSWORD]] as $params) {
            try {
                $this->artisan('platform:create-admin', $params)->run();
                $this->fail('the command accepted a password on the command line');
            } catch (ConsoleExceptionInterface) {
                // expected
            }
        }

        $this->assertSame(0, User::count());
    }

    // ---- the password rules ----------------------------------------------------------------------------------

    public function test_a_weak_password_is_refused_with_the_same_rules_as_the_account_page_and_asked_again(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'Nur Aisyah')
            ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
            ->expectsQuestion('Password', 'password')
            ->expectsOutputToContain('The password field must')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD)
            ->assertSuccessful();

        $this->assertSame(1, User::count());
    }

    public function test_each_part_of_the_password_policy_is_enforced(): void
    {
        $rules = [
            'Sh0rt!a' => 'at least 8 characters',
            'alllowercase1!' => 'uppercase and one lowercase',
            'ALLUPPERCASE1!' => 'uppercase and one lowercase',
            'NoDigitsHere!!' => 'at least one number',
            'NoSymbols1234Ab' => 'at least one symbol',
        ];

        foreach ($rules as $weak => $why) {
            User::query()->delete();

            $this->artisan('platform:create-admin')
                ->expectsQuestion('Name', 'Nur Aisyah')
                ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
                ->expectsQuestion('Password', $weak)
                ->expectsOutputToContain($why)
                ->expectsQuestion('Password', self::PASSWORD)
                ->expectsQuestion('Repeat the password', self::PASSWORD)
                ->assertSuccessful();

            $this->assertFalse(Hash::check($weak, User::sole()->password), $weak);
            Membership::query()->delete();
        }
    }

    public function test_three_weak_passwords_in_a_row_create_nothing(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'Nur Aisyah')
            ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Password', 'password')
            ->expectsOutputToContain('Nothing was created.')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_two_different_passwords_are_not_accepted(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'Nur Aisyah')
            ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD.'x')
            ->expectsOutputToContain('The two passwords are not the same.')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD)
            ->assertSuccessful();

        $this->assertTrue(Hash::check(self::PASSWORD, User::sole()->password));
    }

    public function test_after_three_bad_tries_nothing_is_created(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', 'Nur Aisyah')
            ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
            ->expectsQuestion('Password', self::PASSWORD)->expectsQuestion('Repeat the password', 'other')
            ->expectsQuestion('Password', self::PASSWORD)->expectsQuestion('Repeat the password', 'other')
            ->expectsQuestion('Password', self::PASSWORD)->expectsQuestion('Repeat the password', 'other')
            ->assertFailed();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_a_bad_name_or_email_is_asked_again(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', '')
            ->expectsOutputToContain('The name field is required.')
            ->expectsQuestion('Name', 'Nur Aisyah')
            ->expectsQuestion('E-mail', 'not-an-email')
            ->expectsOutputToContain('The e-mail field must be a valid email address.')
            ->expectsQuestion('E-mail', 'aisyah@mykaunsel.com')
            ->expectsQuestion('Password', self::PASSWORD)
            ->expectsQuestion('Repeat the password', self::PASSWORD)
            ->assertSuccessful();

    }

    public function test_empty_answers_three_times_create_nothing(): void
    {
        $this->artisan('platform:create-admin')
            ->expectsQuestion('Name', '')->expectsQuestion('Name', '')->expectsQuestion('Name', '')
            ->expectsOutputToContain('Nothing was created.')
            ->assertFailed();

        $this->assertSame([0, 0, 0], [User::count(), Organization::count(), AuditLog::count()]);
    }

    // ---- when an admin already exists ------------------------------------------------------------------------

    public function test_it_refuses_when_an_active_admin_exists_unless_forced_and_asks_nothing(): void
    {
        $this->existingAdmin();
        $before = [User::count(), Membership::count(), AuditLog::count()];

        $this->artisan('platform:create-admin')->expectsOutputToContain('A Platform Admin already exists')->assertFailed();

        $this->assertSame($before, [User::count(), Membership::count(), AuditLog::count()]);
    }

    public function test_force_adds_another_admin_and_the_audit_entry_says_so(): void
    {
        $this->existingAdmin();

        $this->run3(options: ['--force' => true])->assertSuccessful();

        $this->assertSame(2, Membership::where('role', MembershipRole::PlatformAdmin->value)->where('status', MembershipStatus::Active->value)->count());
        $entry = AuditLog::where('action', 'admin.created_from_console')->sole();
        $this->assertSame(['summary' => 'Platform Admin created from the command line (--force)', 'forced' => true], $entry->metadata);
        $this->assertSame(1, Organization::where('org_type', OrgType::Platform->value)->count());
    }

    public function test_a_removed_admin_does_not_count_so_no_force_is_needed(): void
    {
        $this->existingAdmin(MembershipStatus::Offboarded);

        $this->run3()->assertSuccessful();

        $this->assertSame(1, Membership::where('role', MembershipRole::PlatformAdmin->value)->where('status', MembershipStatus::Active->value)->count());
    }

    public function test_an_email_that_already_has_an_account_is_refused_and_the_account_is_not_touched(): void
    {
        $this->platform();
        $person = User::factory()->create(['email' => 'aisyah@mykaunsel.com', 'name' => 'Someone Else', 'password' => 'Original!Passw0rd1']);
        $passwordBefore = $person->fresh()->password;

        $this->run3(email: 'AISYAH@mykaunsel.com')->expectsOutputToContain('already has an account')->assertFailed();

        $person->refresh();
        $this->assertSame([$passwordBefore, 'Someone Else'], [$person->password, $person->name]);
        $this->assertSame(0, Membership::count());
        $this->assertSame(0, AuditLog::count());
        $this->assertSame(1, User::count());
    }
}
