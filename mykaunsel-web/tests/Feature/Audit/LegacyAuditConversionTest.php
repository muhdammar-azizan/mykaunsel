<?php

namespace Tests\Feature\Audit;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\AuditCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The one-off migration that turned the Account-page audit rows into the new format. */
class LegacyAuditConversionTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_22_000001_extend_audit_logs_table.php');
    }

    private function legacy(array $row): int
    {
        return DB::table('audit_logs')->insertGetId($row + ['actor_type' => 'user', 'category' => 'other', 'created_at' => now()]);
    }

    public function test_old_rows_are_converted_to_dotted_keys_categories_and_short_target_types(): void
    {
        $org = Organization::create(['name' => 'Uni Lama', 'org_type' => OrgType::University, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
        $admin = User::factory()->create(['name' => 'Admin Lama']);
        Membership::create(['user_id' => $admin->id, 'org_id' => $org->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $student = User::factory()->create(['name' => 'Pelajar Lama']);
        Membership::create(['user_id' => $student->id, 'org_id' => $org->id, 'role' => MembershipRole::Student, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $approved = $this->legacy(['actor_user_id' => $admin->id, 'action' => 'organization_approved', 'target_type' => 'App\\Models\\Organization', 'target_id' => $org->id]);
        $login = $this->legacy(['actor_user_id' => $student->id, 'action' => 'login', 'target_type' => 'App\\Models\\User', 'target_id' => $student->id]);
        $adminLogin = $this->legacy(['actor_user_id' => $admin->id, 'action' => 'login_failed', 'target_type' => 'App\\Models\\User', 'target_id' => $admin->id]);
        $unknown = $this->legacy(['actor_user_id' => null, 'action' => 'something_from_the_past']);

        $this->migration()->convertLegacyRows();
        $row = fn (int $id) => DB::table('audit_logs')->find($id);

        $a = $row($approved);
        $this->assertSame(['organization.approved', 'organizations', 'organization', $org->id, 'Uni Lama'], [$a->action, $a->category, $a->target_type, $a->organization_id, $a->target_name]);
        $this->assertSame(['Admin Lama', 'org_admin'], [$a->actor_name, $a->actor_role]);

        $l = $row($login);
        $this->assertSame(['auth.login', 'auth', 'user'], [$l->action, $l->category, $l->target_type]);
        $this->assertNull($l->actor_name, 'a student is never named');
        $this->assertNull($l->target_name);

        $this->assertSame('Admin Lama', $row($adminLogin)->target_name, 'a staff member keeps their name');

        $u = $row($unknown);
        $this->assertSame(['something_from_the_past', 'other'], [$u->action, $u->category], 'unmapped keys are left as they are');
    }

    public function test_running_the_conversion_twice_changes_nothing_more(): void
    {
        $id = $this->legacy(['action' => 'password_changed', 'target_type' => 'App\\Models\\User', 'target_id' => 1]);

        $this->migration()->convertLegacyRows();
        $first = (array) DB::table('audit_logs')->find($id);
        $this->migration()->convertLegacyRows();

        $this->assertSame($first, (array) DB::table('audit_logs')->find($id));
        $this->assertSame('account.password_changed', $first['action']);
    }

    public function test_every_old_key_maps_to_a_key_that_exists_in_the_catalogue(): void
    {
        $source = file_get_contents(database_path('migrations/2026_10_22_000001_extend_audit_logs_table.php'));
        preg_match_all("/=> \['([a-z_.]+)', '([a-z]+)'\]/", $source, $m, PREG_SET_ORDER);

        $this->assertNotEmpty($m);
        foreach ($m as [, $new, $category]) {
            $this->assertArrayHasKey($new, config('audit.actions'), "{$new} is missing from config/audit.php");
            $this->assertSame(config('audit.actions')[$new]['category'], $category, "{$new}: migration and catalogue disagree on the category");
        }
    }

    public function test_the_display_fallback_handles_keys_that_are_not_in_the_catalogue(): void
    {
        $known = AuditCatalogue::describe('organization.approved');
        $this->assertSame(['organizations', 'approved', 'Organization approved'], [$known['category'], $known['badge'], $known['label']]);

        $legacy = AuditCatalogue::describe('something_from_the_past');
        $this->assertSame(['other', 'updated', 'Something From The Past'], [$legacy['category'], $legacy['badge'], $legacy['label']]);

        $this->assertSame('Auth Login Gone', AuditCatalogue::describe('auth.login_gone')['label']);
    }
}
