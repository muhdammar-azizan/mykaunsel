<?php

namespace Tests\Feature\Audit;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\AuditLogImmutable;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationDecisionService;
use App\Support\AuditContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = $this->org('MyKaunsel Platform', OrgType::Platform, OrgStatus::Active);
    }

    private function org(string $name = 'Universiti Contoh', OrgType $type = OrgType::University, OrgStatus $status = OrgStatus::Pending): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => $type, 'access_model' => AccessModel::Closed, 'subscription_status' => $status]);
    }

    private function person(string $name, MembershipRole $role, ?Organization $org = null): User
    {
        $user = User::factory()->create(['name' => $name]);
        Membership::create(['user_id' => $user->id, 'org_id' => ($org ?? $this->platform)->id, 'role' => $role, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        return $user;
    }

    private function entry(): AuditLog
    {
        return AuditLog::latest('id')->firstOrFail();
    }

    // ---- Append-only -----------------------------------------------------------------------

    public function test_an_entry_can_never_be_updated_or_deleted(): void
    {
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);
        $log = Audit::log('account.avatar_changed', $admin, [], $admin);

        $attempts = [
            'save after changing' => fn () => $log->fill(['action' => 'organization.approved'])->save(),
            'saveQuietly' => fn () => tap($log)->forceFill(['category' => 'x'])->saveQuietly(),
            'update()' => fn () => $log->update(['category' => 'x']),
            'delete()' => fn () => $log->delete(),
            'destroy' => fn () => AuditLog::destroy($log->id),
            'builder update' => fn () => AuditLog::where('id', $log->id)->update(['category' => 'x']),
            'builder delete' => fn () => AuditLog::where('id', $log->id)->delete(),
            'whole table delete' => fn () => AuditLog::query()->delete(),
            'forceDelete' => fn () => AuditLog::where('id', $log->id)->forceDelete(),
            'truncate' => fn () => AuditLog::truncate(),
            'upsert' => fn () => AuditLog::upsert([['id' => $log->id, 'action' => 'x']], ['id']),
            'increment' => fn () => AuditLog::where('id', $log->id)->increment('target_id'),
            'updateOrCreate' => fn () => AuditLog::updateOrCreate(['id' => $log->id], ['category' => 'x']),
        ];

        foreach ($attempts as $name => $attempt) {
            try {
                $attempt();
                $this->fail("{$name} should have thrown");
            } catch (AuditLogImmutable) {
                // expected
            }
        }

        $fresh = $log->fresh();
        $this->assertSame('account.avatar_changed', $fresh->action);
        $this->assertSame('account', $fresh->category);
        $this->assertSame(1, AuditLog::count());
    }

    public function test_deleting_the_user_or_the_organization_does_not_touch_audit_rows(): void
    {
        $org = $this->org();
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);
        Audit::log('organization.note_added', $org, ['note_id' => 1], $admin);
        $before = AuditLog::first()->toArray();

        Membership::where('user_id', $admin->id)->delete();
        $admin->delete();
        $org->delete();

        $after = AuditLog::first();
        $this->assertSame($before, $after->toArray(), 'no column was rewritten');
        $this->assertSame($admin->id, $after->actor_user_id);
        $this->assertSame('Admin', $after->actor_name, 'the saved name keeps the entry readable');
        $this->assertSame('Universiti Contoh', $after->target_name);
        $this->assertNull($after->actor);
    }

    public function test_unknown_actions_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown audit action [organization_approved]');

        Audit::log('organization_approved');
    }

    public function test_every_catalogue_entry_is_complete(): void
    {
        $badges = ['created', 'approved', 'rejected', 'suspended', 'reactivated', 'updated', 'deleted', 'exported', 'security', 'system'];

        foreach (config('audit.actions') as $key => $def) {
            $this->assertMatchesRegularExpression('/^[a-z_]+\.[a-z_]+$/', $key, "{$key} must be area.event");
            $this->assertArrayHasKey($def['category'], config('audit.categories'), "{$key} has an unknown category");
            $this->assertContains($def['badge'], $badges, "{$key} has an unknown badge");
            $this->assertNotSame('', $def['label']);
        }
    }

    // ---- Atomic with the action ------------------------------------------------------------

    public function test_the_entry_is_rolled_back_together_with_its_action(): void
    {
        $org = $this->org();
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);

        try {
            DB::transaction(function () use ($org, $admin) {
                $org->update(['name' => 'Nama Baharu']);
                Audit::log('organization.note_added', $org, ['note_id' => 1], $admin);

                throw new RuntimeException('something later in the action failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, AuditLog::count());
        $this->assertSame('Universiti Contoh', $org->fresh()->name);
    }

    public function test_the_action_is_rolled_back_when_its_audit_entry_cannot_be_written(): void
    {
        Notification::fake();
        $org = $this->org();
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);

        Audit::shouldReceive('log')->andThrow(new RuntimeException('audit table unavailable'));

        try {
            app(OrganizationDecisionService::class)->approve($org, $admin);
            $this->fail('approve() should have failed');
        } catch (RuntimeException) {
        }

        $org->refresh();
        $this->assertSame(OrgStatus::Pending, $org->subscription_status, 'no log, no approval');
        $this->assertNull($org->reviewed_by);
        Notification::assertNothingSent();
    }

    public function test_decision_services_write_the_entry_with_the_change(): void
    {
        Notification::fake();
        $org = $this->org();
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);

        app(OrganizationDecisionService::class)->approve($org, $admin);

        $log = $this->entry();
        $this->assertSame('organization.approved', $log->action);
        $this->assertSame(['status' => ['old' => 'pending', 'new' => 'active']], $log->metadata['changes']);
        $this->assertSame($org->id, $log->organization_id);
    }

    // ---- Content rules ---------------------------------------------------------------------

    public function test_sensitive_keys_and_message_text_are_stripped_from_metadata(): void
    {
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);

        Audit::log('account.password_changed', $admin, [
            'reason' => 'kept',
            'password' => 'hunter2',
            'new_password_confirmation' => 'hunter2',
            'api_token' => 'abc',
            'Client-Secret' => 'abc',
            'recovery_codes' => ['a', 'b'],
            'two_factor_secret' => 'JBSWY3DP',
            'message' => 'I feel anxious about exams',
            'chat' => ['text' => 'hello'],
            'complaint_text' => 'The counselor said...',
            'description' => 'free text from a complaint',
            'nested' => ['ok' => 1, 'secret' => 'x', 'deeper' => ['token' => 'y', 'fine' => 'z']],
            'footprint' => 'not caught by "otp"',
            'context' => 'not caught by "text"',
        ], $admin);

        $this->assertSame([
            'reason' => 'kept',
            'nested' => ['ok' => 1, 'deeper' => ['fine' => 'z']],
            'footprint' => 'not caught by "otp"',
            'context' => 'not caught by "text"',
        ], $this->entry()->metadata);

        $this->assertStringNotContainsString('hunter2', json_encode(AuditLog::all()->toArray()));
    }

    public function test_long_values_are_truncated_and_enums_and_dates_are_stored_as_plain_values(): void
    {
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);

        Audit::log('organization.suspended', $this->org(), [
            'reason' => str_repeat('x', 5000),
            'status' => OrgStatus::Suspended,
            'when' => now(),
            'object' => new \stdClass,
        ], $admin);

        $meta = $this->entry()->metadata;
        $this->assertLessThanOrEqual(2001, mb_strlen($meta['reason']));
        $this->assertSame('suspended', $meta['status']);
        $this->assertIsString($meta['when']);
        $this->assertArrayNotHasKey('object', $meta);
    }

    // ---- Actor, role snapshot ---------------------------------------------------------------

    public function test_platform_admin_name_and_role_are_copied_at_that_moment(): void
    {
        $admin = $this->person('Siti Platform', MembershipRole::PlatformAdmin);

        Audit::log('organization.note_added', $this->org(), [], $admin);
        $admin->update(['name' => 'Siti Renamed']);

        $log = $this->entry();
        $this->assertSame('user', $log->actor_type);
        $this->assertSame('Siti Platform', $log->actor_name);
        $this->assertSame('platform_admin', $log->actor_role);
    }

    public function test_org_admin_and_counselor_snapshots_follow_the_rules(): void
    {
        $org = $this->org('Org A', OrgType::Corporate, OrgStatus::Active);
        $orgAdmin = $this->person('Pengurus', MembershipRole::OrgAdmin, $org);
        $counselor = $this->person('Kaunselor', MembershipRole::Counselor, $org);

        Audit::log('member.removed', $org, ['member_id' => 9], $orgAdmin);
        $this->assertSame(['Pengurus', 'org_admin', $org->id], [$this->entry()->actor_name, $this->entry()->actor_role, $this->entry()->organization_id]);

        // A counselor doing something administrative is staff...
        Audit::log('org_counselor.removed', $org, [], $counselor);
        $this->assertSame(['Kaunselor', 'counselor'], [$this->entry()->actor_name, $this->entry()->actor_role]);

        // ...but a counselor merely signing in is not.
        Audit::log('auth.login', $counselor, [], $counselor);
        $this->assertNull($this->entry()->actor_name);
        $this->assertNull($this->entry()->actor_role);
        $this->assertNull($this->entry()->target_name);
        $this->assertSame($counselor->id, $this->entry()->actor_user_id);
    }

    public function test_non_staff_are_stored_as_a_bare_user_id(): void
    {
        $student = $this->person('Pelajar Biasa', MembershipRole::Student, $this->org('Uni', OrgType::University, OrgStatus::Active));

        Audit::log('account.password_changed', $student, [], $student);
        $log = $this->entry();

        $this->assertSame($student->id, $log->actor_user_id);
        $this->assertNull($log->actor_name);
        $this->assertNull($log->actor_role);
        $this->assertNull($log->target_name, 'a non-staff target is not named either');
        $this->assertStringNotContainsString('Pelajar Biasa', json_encode($log->toArray()));
        $this->assertStringNotContainsString($student->email, json_encode($log->toArray()));
    }

    public function test_the_active_session_role_is_used_for_the_snapshot(): void
    {
        $user = $this->person('Dwi Peranan', MembershipRole::PlatformAdmin);
        $org = $this->org('Uni', OrgType::University, OrgStatus::Active);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        \Illuminate\Support\Facades\Route::middleware(['web', 'auth'])->post('/_audit-probe', fn () => response()->json(['id' => Audit::log('account.avatar_changed', $user)->id]));

        // Acting as a student: no staff snapshot, even though this person is also a platform admin.
        $this->actingAs($user)->withSession(['current_org_id' => $org->id, 'current_role' => 'student'])->post('/_audit-probe')->assertOk();
        $this->assertNull($this->entry()->actor_name);
        $this->assertNull($this->entry()->actor_role);

        // Acting as the platform admin: snapshot taken.
        $this->actingAs($user)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'])->post('/_audit-probe')->assertOk();
        $this->assertSame(['Dwi Peranan', 'platform_admin'], [$this->entry()->actor_name, $this->entry()->actor_role]);
        $this->assertNotNull($this->entry()->user_agent);
    }

    public function test_system_actor_for_scheduled_work_and_for_explicit_calls(): void
    {
        $org = $this->org();

        $explicit = Audit::log('system.chat_purged', null, ['sessions' => 3], 'system');
        $this->assertSame('system', $explicit->actor_type);
        $this->assertNull($explicit->actor_user_id);
        $this->assertNull($explicit->actor_name);

        $viaContext = Audit::asSystem(fn () => Audit::log('organization.dns_verified', $org, ['domain' => 'a.edu.my']));
        $this->assertSame('system', $viaContext->actor_type);
        $this->assertTrue($viaContext->isSystem());

        // Even with someone signed in, asSystem() wins (a scheduled job is not that person).
        $this->actingAs($this->person('Admin', MembershipRole::PlatformAdmin));
        $this->assertSame('system', Audit::asSystem(fn () => Audit::log('organization.dns_verified', $org))->actor_type);
        $this->assertSame('user', Audit::log('organization.note_added', $org)->actor_type);
    }

    public function test_outside_a_web_request_with_nobody_signed_in_the_actor_is_the_system(): void
    {
        $this->assertSame('system', Audit::log('system.booking_requests_expired', null, ['count' => 2])->actor_type);
    }

    // ---- Target, organization, request id, ip ----------------------------------------------

    public function test_target_type_name_and_organization_are_recorded_and_survive_rename_and_delete(): void
    {
        $org = $this->org('Nama Asal');
        Audit::log('organization.note_added', $org, [], 'system');

        $org->update(['name' => 'Nama Baru']);
        $org->delete();

        $log = $this->entry();
        $this->assertSame('organization', $log->target_type);
        $this->assertSame($org->id, $log->target_id);
        $this->assertSame('Nama Asal', $log->target_name);
        $this->assertSame($org->id, $log->organization_id);
    }

    public function test_options_can_describe_a_target_that_is_already_gone_and_set_the_organization(): void
    {
        $org = $this->org();

        $log = Audit::log('member.removed', null, ['reason' => 'left'], 'system', [
            'target_type' => 'user', 'target_id' => 99, 'target_name' => null, 'organization_id' => $org->id,
        ]);

        $this->assertSame(['user', 99, $org->id], [$log->target_type, $log->target_id, $log->organization_id]);
    }

    public function test_entries_in_one_request_share_a_request_id_and_different_requests_do_not(): void
    {
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);
        $session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];
        $org = $this->org();
        Notification::fake();

        $first = $this->actingAs($admin)->withSession($session)->post(route('platform.organizations.reject', $org), ['reason' => 'Dokumen tidak lengkap.']);
        $firstId = $first->headers->get('X-Request-Id');

        $second = $this->actingAs($admin)->withSession($session)->post(route('platform.organizations.notes.store', $org), ['body' => 'Nota']);
        $secondId = $second->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $firstId);
        $this->assertNotSame($firstId, $secondId);
        $this->assertSame($firstId, AuditLog::where('action', 'organization.rejected')->value('request_id'));
        $this->assertSame($secondId, AuditLog::where('action', 'organization.note_added')->value('request_id'));
        $this->assertSame('127.0.0.1', AuditLog::first()->ip);
    }

    public function test_a_client_cannot_choose_its_own_request_id(): void
    {
        $admin = $this->person('Admin', MembershipRole::PlatformAdmin);
        $session = ['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'];

        $response = $this->actingAs($admin)->withSession($session)->withHeader('X-Request-Id', 'forged')->get(route('platform.dashboard'));

        $this->assertNotSame('forged', $response->headers->get('X-Request-Id'));
    }

    public function test_the_console_uses_one_request_id_for_the_whole_run(): void
    {
        $context = app(AuditContext::class);

        $a = Audit::log('system.chat_purged', null, [], 'system');
        $b = Audit::log('system.chat_purged', null, [], 'system');

        $this->assertSame($a->request_id, $b->request_id);
        $this->assertSame($context->requestId(), $a->request_id);
        $this->assertNull($a->ip);
    }

    public function test_times_are_stored_in_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->assertSame('UTC', config('app.timezone'));

        $log = Audit::log('system.chat_purged', null, [], 'system', ['at' => now()->setTimezone('Asia/Kuala_Lumpur')->setTime(8, 0)]);

        $this->assertSame(0, (int) $log->fresh()->created_at->utcOffset());
        $this->assertSame('00:00:00', $log->fresh()->created_at->format('H:i:s'), '08:00 in Kuala Lumpur is 00:00 UTC');
    }

    // ---- tryLog ----------------------------------------------------------------------------

    public function test_try_log_reports_but_swallows_a_failure(): void
    {
        $this->withoutExceptionHandling();
        \Illuminate\Support\Facades\Exceptions::fake();

        $result = Audit::tryLog('not.a_real_action');

        $this->assertNull($result);
        \Illuminate\Support\Facades\Exceptions::assertReported(\InvalidArgumentException::class);
    }
}
