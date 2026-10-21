<?php

namespace Tests\Feature\Audit;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthActivityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, ?MembershipRole $role = null, string $name = 'Orang Biasa'): User
    {
        $user = User::factory()->create(['email' => $email, 'name' => $name]);

        if ($role) {
            $org = Organization::create(['name' => 'Org '.$email, 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
            Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }

        return $user;
    }

    public function test_a_student_sign_in_stores_only_the_user_id_and_ip(): void
    {
        $student = $this->user('pelajar@example.com', MembershipRole::Student, 'Pelajar Satu');

        $this->post('/login', ['email' => $student->email, 'password' => 'password']);

        $log = AuditLog::where('action', 'auth.login')->firstOrFail();
        $this->assertSame('auth', $log->category);
        $this->assertSame($student->id, $log->actor_user_id);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertNull($log->actor_name);
        $this->assertNull($log->actor_role);
        $this->assertNull($log->target_name);
        $this->assertNull($log->user_agent);
        $this->assertNull($log->metadata);

        $json = json_encode($log->toArray());
        $this->assertStringNotContainsString('Pelajar Satu', $json);
        $this->assertStringNotContainsString('pelajar@example.com', $json);
    }

    public function test_a_platform_admin_sign_in_keeps_name_and_role(): void
    {
        $admin = $this->user('admin@example.com', MembershipRole::PlatformAdmin, 'Admin Platform');

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $log = AuditLog::where('action', 'auth.login')->firstOrFail();
        $this->assertSame(['Admin Platform', 'platform_admin'], [$log->actor_name, $log->actor_role]);
    }

    public function test_a_failed_sign_in_for_a_known_user_records_the_user_id_but_never_the_password(): void
    {
        $student = $this->user('pelajar@example.com', MembershipRole::Student);

        $this->post('/login', ['email' => $student->email, 'password' => 'Salah#Kata123']);

        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertSame($student->id, $log->actor_user_id);
        $this->assertNull($log->metadata);
        $this->assertStringNotContainsString('Salah#Kata123', json_encode(AuditLog::all()->toArray()));
        $this->assertStringNotContainsString('pelajar@example.com', json_encode($log->toArray()));
    }

    public function test_a_failed_sign_in_for_an_unknown_email_stores_only_a_keyed_hash(): void
    {
        // People sometimes type their password into the email field.
        $typed = 'MyP@ssw0rd-typed-here';

        $this->post('/login', ['email' => $typed, 'password' => 'x']);
        $this->post('/login', ['email' => 'tiada@example.com', 'password' => 'Salah#Kata123']);

        $logs = AuditLog::where('action', 'auth.login_failed')->get();
        $this->assertCount(2, $logs);

        foreach ($logs as $log) {
            $this->assertSame('guest', $log->actor_type);
            $this->assertNull($log->actor_user_id);
            $this->assertNull($log->actor_name);
            $this->assertSame(['email_hash'], array_keys($log->metadata));
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $log->metadata['email_hash']);
        }

        $dump = json_encode(AuditLog::all()->toArray());
        foreach ([$typed, 'tiada@example.com', 'Salah#Kata123'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }

        $this->assertSame(
            hash_hmac('sha256', 'tiada@example.com', config('app.key')),
            $logs->last()->metadata['email_hash'],
            'same email, same hash, so repeated guessing against one address can still be spotted',
        );
    }

    public function test_failed_sign_in_entries_are_rate_limited_per_ip(): void
    {
        config(['audit.login_failed_per_ip_per_minute' => 3]);
        RateLimiter::clear('audit-login-failed:127.0.0.1');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        foreach (range(1, 8) as $i) {
            $this->post('/login', ['email' => "guess{$i}@example.com", 'password' => 'x']);
        }

        $this->assertSame(3, AuditLog::where('action', 'auth.login_failed')->count(), 'only the first 3 are stored');
        $this->assertSame(5, (int) Cache::get('audit:login_failed_suppressed'), 'the rest are counted, not dropped silently');
    }

    public function test_a_sign_in_still_works_when_the_audit_log_cannot_be_written(): void
    {
        $student = $this->user('pelajar@example.com', MembershipRole::Student);
        Exceptions::fake();

        // Break the table the log writes to.
        Schema_dropAuditLogs();

        $this->post('/login', ['email' => $student->email, 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($student);
        Exceptions::assertReported(fn (\Illuminate\Database\QueryException $e) => str_contains($e->getMessage(), 'audit_logs'));
    }

    public function test_admin_actions_are_not_best_effort(): void
    {
        // Contrast with the sign-in test above: log() (used by admin actions) lets the failure out.
        Schema_dropAuditLogs();

        $this->expectException(\Throwable::class);
        Audit::log('system.chat_purged', null, [], 'system');
    }
}

/** Drop the table inside the test transaction (SQLite supports transactional DDL). */
function Schema_dropAuditLogs(): void
{
    DB::statement('drop table audit_logs');
}
