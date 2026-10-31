<?php

namespace Tests\Feature\Membership;

use App\Enums\AccessModel;
use App\Enums\DomainMatchType;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\MembershipRefused;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\OrgInviteCode;
use App\Models\Organization;
use App\Models\User;
use App\Services\MembershipService;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Reference codes and the "one organization per client" rule (docs/APP_SCREENS.md, "Decisions of the owner"). */
class InviteCodeRulesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private Organization $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        $this->acme = $this->org('Acme Berhad');
        $this->beta = $this->org('Beta Sdn Bhd');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function org(string $name, OrgStatus $status = OrgStatus::Active): Organization
    {
        return Organization::create(['name' => $name, 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => $status]);
    }

    private function service(): MembershipService
    {
        return app(MembershipService::class);
    }

    private function person(bool $verified = true): User
    {
        return User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    }

    private function code(?Organization $org = null, ?int $max = null, ?Carbon $expires = null): OrgInviteCode
    {
        return OrgInviteCode::issue($org ?? $this->acme, null, $max, $expires);
    }

    private function join(User $user, string $code): Membership
    {
        return $this->service()->joinViaInviteCode($user, $code);
    }

    private function refusal(callable $do): MembershipRefused
    {
        try {
            $do();
        } catch (MembershipRefused $e) {
            return $e;
        }

        $this->fail('Expected the join to be refused.');
    }

    private function hold(User $user, Organization $org, MembershipStatus $status, MembershipRole $role = MembershipRole::Employee): Membership
    {
        return Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => $role, 'status' => $status, 'joined_at' => now()]);
    }

    public function test_a_code_is_ten_easy_letters_and_digits_and_is_read_back_whatever_the_person_typed(): void
    {
        $code = $this->code();

        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{10}$/', $code->code);
        $this->assertMatchesRegularExpression('/^\w{5}-\w{5}$/', $code->display());
        $this->assertSame($code->code, OrgInviteCode::normalize(' '.strtolower($code->display()).' '));
        $this->assertNotSame($code->code, $this->code()->code);
    }

    public function test_using_a_code_asks_to_join_as_an_employee_and_gives_no_access_until_approved(): void
    {
        $user = $this->person();
        $code = $this->code();

        $membership = $this->join($user, strtolower($code->display()));

        $this->assertSame(MembershipStatus::PendingApproval, $membership->status);
        $this->assertSame(MembershipRole::Employee, $membership->role);
        $this->assertSame(JoinSource::InviteCode, $membership->join_source);
        $this->assertSame($this->acme->id, $membership->org_id);
        $this->assertSame(1, $code->fresh()->used_count);
        $this->assertSame(0, $user->memberships()->where('status', MembershipStatus::Active->value)->count(), 'nothing is open to a person who is only waiting');

        $log = AuditLog::where('action', 'member.join_requested')->sole();
        $this->assertSame($this->acme->id, $log->target_id);
        $this->assertSame($user->id, $log->actor_user_id);
    }

    public function test_every_bad_code_gets_the_same_answer(): void
    {
        $user = $this->person();
        $expired = $this->code(expires: now()->subMinute());
        $revoked = $this->code();
        $revoked->update(['revoked_at' => now()]);
        $usedUp = $this->code(max: 1);
        $usedUp->update(['used_count' => 1]);
        $pendingOrg = $this->code($this->org('Pending Co', OrgStatus::Pending));
        $suspendedOrg = $this->code($this->org('Suspended Co', OrgStatus::Suspended));
        $platform = Organization::where('org_type', OrgType::Platform->value)->firstOrFail();
        $platformCode = OrgInviteCode::issue($platform, null, null, null);

        $messages = [];

        foreach (['NOSUCHCODE', '', $expired->code, $revoked->code, $usedUp->code, $pendingOrg->code, $suspendedOrg->code, $platformCode->code] as $typed) {
            $e = $this->refusal(fn () => $this->join($user, $typed));
            $this->assertSame('invalid_code', $e->reason, "for '{$typed}'");
            $messages[] = $e->getMessage();
        }

        $this->assertCount(1, array_unique($messages));
        $this->assertSame(0, Membership::where('user_id', $user->id)->count());
        $this->assertSame(1, $usedUp->fresh()->used_count);
        $this->assertSame(0, $expired->fresh()->used_count);
    }

    public function test_the_last_place_of_a_code_goes_to_one_person_only(): void
    {
        $code = $this->code(max: 1);

        $this->join($this->person(), $code->code);
        $e = $this->refusal(fn () => $this->join($this->person(), $code->code));

        $this->assertSame('invalid_code', $e->reason);
        $this->assertSame(1, $code->fresh()->used_count);
    }

    public function test_the_email_must_be_verified_first(): void
    {
        $e = $this->refusal(fn () => $this->join($this->person(verified: false), $this->code()->code));

        $this->assertSame('email_not_verified', $e->reason);
    }

    public function test_a_client_can_only_be_in_one_organization_and_is_told_which(): void
    {
        foreach ([MembershipStatus::Active, MembershipStatus::PendingApproval, MembershipStatus::NoticePeriod, MembershipStatus::Suspended] as $status) {
            $user = $this->person();
            $this->hold($user, $this->beta, $status);
            $code = $this->code();

            $e = $this->refusal(fn () => $this->join($user, $code->code));

            $this->assertSame('in_other_organization', $e->reason, $status->value);
            $this->assertStringContainsString('Beta Sdn Bhd', $e->getMessage());
            $this->assertSame(0, $code->fresh()->used_count, 'a refused join does not use up the code');
            $this->assertFalse(Membership::where('user_id', $user->id)->where('org_id', $this->acme->id)->exists());
        }
    }

    public function test_someone_who_has_left_can_join_another_and_an_old_row_in_the_same_organization_is_reused(): void
    {
        $user = $this->person();
        $this->hold($user, $this->beta, MembershipStatus::Offboarded);
        $old = $this->hold($user, $this->acme, MembershipStatus::Alumni, MembershipRole::Student);
        $old->update(['offboarded_at' => now()->subYear(), 'offboard_reason' => 'left']);

        $membership = $this->join($user, $this->code()->code);

        $this->assertSame($old->id, $membership->id, 'one row per person and organization');
        $this->assertSame(MembershipStatus::PendingApproval, $membership->fresh()->status);
        $this->assertSame(MembershipRole::Employee, $membership->fresh()->role);
        $this->assertNull($membership->fresh()->offboarded_at);
        $this->assertNull($membership->fresh()->offboard_reason);
    }

    public function test_asking_again_while_waiting_or_after_being_approved_changes_nothing(): void
    {
        $user = $this->person();
        $code = $this->code();
        $this->join($user, $code->code);

        $again = $this->refusal(fn () => $this->join($user, $code->code));
        $this->assertSame('waiting_approval', $again->reason);

        Membership::where('user_id', $user->id)->update(['status' => MembershipStatus::Active->value]);
        $approved = $this->refusal(fn () => $this->join($user, $code->code));
        $this->assertSame('already_member', $approved->reason);

        $this->assertSame(1, $code->fresh()->used_count);
        $this->assertSame(1, Membership::where('user_id', $user->id)->count());
    }

    public function test_staff_of_an_organization_are_never_turned_into_employees_by_a_code(): void
    {
        $admin = $this->person();
        $row = $this->hold($admin, $this->acme, MembershipStatus::Active, MembershipRole::OrgAdmin);

        $e = $this->refusal(fn () => $this->join($admin, $this->code()->code));

        $this->assertSame('already_member', $e->reason);
        $this->assertSame(MembershipRole::OrgAdmin, $row->fresh()->role);
    }

    public function test_a_counselor_of_another_organization_may_still_join_as_an_employee(): void
    {
        $user = $this->person();
        $this->hold($user, $this->beta, MembershipStatus::Active, MembershipRole::Counselor);

        $membership = $this->join($user, $this->code()->code);

        $this->assertSame(MembershipStatus::PendingApproval, $membership->status);
        $this->assertSame(2, Membership::where('user_id', $user->id)->count());
    }

    public function test_joining_by_email_domain_follows_the_same_one_organization_rule(): void
    {
        OrgDomain::create([
            'org_id' => $this->acme->id, 'domain' => 'acme.test', 'default_role' => MembershipRole::Employee, 'match_type' => DomainMatchType::Exact,
            'verification_token' => 't', 'dns_verified' => true, 'verified_at' => now(),
        ]);
        $user = User::factory()->create(['email' => 'aina@acme.test', 'email_verified_at' => now()]);
        $this->hold($user, $this->beta, MembershipStatus::Active);

        $e = $this->refusal(fn () => $this->service()->joinViaDomain($user, $this->acme));
        $this->assertSame('in_other_organization', $e->reason);

        Membership::where('user_id', $user->id)->update(['status' => MembershipStatus::Offboarded->value]);
        $this->assertSame(MembershipStatus::Active, $this->service()->joinViaDomain($user, $this->acme)->status);
    }
}
