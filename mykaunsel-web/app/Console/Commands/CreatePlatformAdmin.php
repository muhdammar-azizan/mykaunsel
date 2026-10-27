<?php

namespace App\Console\Commands;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The way to make the FIRST Platform Admin on a real server (the demo seeder only runs locally).
 *
 * - The password is typed at a hidden prompt, never given as an argument (arguments stay in the shell history).
 * - It follows the same password rules as the Account page.
 * - The e-mail counts as verified: whoever has access to the server vouches for it.
 * - It refuses when an active Platform Admin already exists (more admins are invited from Settings > Platform Admins),
 *   unless --force is given. It also refuses an e-mail that already has an account: it never changes anyone's password.
 * - It is written to the audit log as a "system" action, without the e-mail or the password.
 *
 *   php artisan platform:create-admin
 *   php artisan platform:create-admin --force
 */
class CreatePlatformAdmin extends Command
{
    private const ATTEMPTS = 3;

    protected $signature = 'platform:create-admin
        {--force : Add the admin although an active Platform Admin already exists}';

    protected $description = 'Create a Platform Admin from the terminal (the first admin of a new server)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (! $force && $this->activeAdmins() > 0) {
            $this->error('A Platform Admin already exists. Invite more admins from Settings > Platform Admins, or run this again with --force.');

            return self::FAILURE;
        }

        $name = $this->askValid('Name', ['required', 'string', 'max:255']);
        $email = $name === null ? null : $this->askValid('E-mail', ['required', 'email', 'max:255']);
        $password = $email === null ? null : $this->askPassword();

        if ($password === null) {
            $this->error('Nothing was created.');

            return self::FAILURE;
        }

        $email = strtolower(trim($email));

        try {
            $result = DB::transaction(fn () => $this->create(trim($name), $email, $password, $force));
        } catch (UniqueConstraintViolationException) {
            $result = 'taken';
        }

        if ($result === 'exists') {
            $this->error('A Platform Admin already exists. Run this again with --force to add another one.');

            return self::FAILURE;
        }

        if ($result === 'taken') {
            $this->error('That e-mail already has an account. This command never changes an existing account: invite it from Settings > Platform Admins instead.');

            return self::FAILURE;
        }

        $this->info('Platform Admin created. They can sign in now.');

        return self::SUCCESS;
    }

    private function activeAdmins(): int
    {
        return Membership::query()
            ->where('role', MembershipRole::PlatformAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->count();
    }

    /** @return 'created'|'exists'|'taken' */
    private function create(string $name, string $email, string $password, bool $force): string
    {
        // Same lock as removing an admin, so two terminals cannot both make "the first" admin.
        $active = Membership::query()
            ->where('role', MembershipRole::PlatformAdmin->value)
            ->where('status', MembershipStatus::Active->value)
            ->lockForUpdate()
            ->pluck('id')
            ->count();

        if ($active > 0 && ! $force) {
            return 'exists';
        }

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            return 'taken';
        }

        $platform = Organization::query()->where('org_type', OrgType::Platform->value)->first()
            ?? Organization::create([
                'name' => 'MyKaunsel Platform',
                'org_type' => OrgType::Platform,
                'access_model' => AccessModel::Open,
                'subscription_status' => OrgStatus::Active,
            ]);

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Membership::create([
            'user_id' => $user->id,
            'org_id' => $platform->id,
            'role' => MembershipRole::PlatformAdmin,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        Audit::log('admin.created_from_console', $user, [
            'summary' => $force && $active > 0 ? 'Platform Admin created from the command line (--force)' : 'First Platform Admin created from the command line',
            'forced' => $force && $active > 0,
        ], 'system', ['organization_id' => $platform->id]);

        return 'created';
    }

    /** Asks until the answer passes the rules (3 tries); null if it never does or nobody can answer. */
    private function askValid(string $label, array $rules): ?string
    {
        for ($try = 0; $try < self::ATTEMPTS; $try++) {
            $answer = $this->ask($label);
            $error = Validator::make(['value' => $answer], ['value' => $rules], [], ['value' => strtolower($label)])->errors()->first();

            if ($error === '') {
                return (string) $answer;
            }

            $this->error($error);
        }

        return null;
    }

    private function askPassword(): ?string
    {
        for ($try = 0; $try < self::ATTEMPTS; $try++) {
            $password = $this->secret('Password');
            $error = Validator::make(['password' => $password], ['password' => ['required', 'string', Password::defaults()]])->errors()->first();

            if ($error !== '') {
                $this->error($error);

                continue;
            }

            if ($this->secret('Repeat the password') !== $password) {
                $this->error('The two passwords are not the same.');

                continue;
            }

            return $password;
        }

        return null;
    }
}
