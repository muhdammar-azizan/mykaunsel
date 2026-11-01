<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The organization admin's Settings page. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgSettingsPageTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->acme->update(['ssm_number' => '201901000123']);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email' => 'hana@acme.example', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hr()
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
    }

    public function test_the_page_shows_the_details_the_domains_and_the_administrators(): void
    {
        OrgDomain::create(['org_id' => $this->acme->id, 'domain' => 'acme.example', 'default_role' => 'employee', 'dns_verified' => true]);
        OrgDomain::create(['org_id' => $this->acme->id, 'domain' => 'acme.test', 'default_role' => 'employee', 'dns_verified' => false]);
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        OrgDomain::create(['org_id' => $beta->id, 'domain' => 'beta.example', 'default_role' => 'employee', 'dns_verified' => true]);

        $this->hr()->get(route('org.settings'))->assertOk()
            ->assertSee('Acme Berhad')->assertSee('201901000123')->assertSee('acme.example')->assertSee('acme.test')->assertSee('Not verified')
            ->assertSee('Hana HR')->assertSee('hana@acme.example')->assertDontSee('beta.example');
    }

    public function test_the_cancellation_deadline_is_saved_audited_and_used_by_bookings(): void
    {
        $this->hr()->put(route('org.settings.update'), ['cancellation_deadline_hours' => 48])->assertSessionHas('status', 'Settings saved.');

        $this->assertSame(48, (int) $this->acme->fresh()->cancellation_deadline_hours);
        $entry = AuditLog::where('action', 'organization.settings_updated')->sole();
        $this->assertStringContainsString('cancellation_deadline_hours', json_encode($entry->toArray()));

        $counselor = $this->counselor('Aina', [$this->acme]);
        $this->assertSame(48, app(BookingService::class)->cancellationHours($this->slot($counselor, $this->acme, '2026-11-05 10:00')->load('contextOrg')));

        $this->hr()->put(route('org.settings.update'), ['cancellation_deadline_hours' => 48])->assertSessionHas('status', 'Nothing changed.');
        $this->assertSame(1, AuditLog::where('action', 'organization.settings_updated')->count());
    }

    public function test_only_the_offered_hours_are_accepted(): void
    {
        $this->hr()->put(route('org.settings.update'), ['cancellation_deadline_hours' => 5])->assertSessionHasErrors('cancellation_deadline_hours');
        $this->hr()->put(route('org.settings.update'), ['cancellation_deadline_hours' => 'soon'])->assertSessionHasErrors('cancellation_deadline_hours');
        $this->assertSame(24, (int) $this->acme->fresh()->cancellation_deadline_hours);
    }

    public function test_the_name_and_registration_number_can_not_be_changed_from_here_and_other_roles_are_refused(): void
    {
        $this->hr()->put(route('org.settings.update'), ['cancellation_deadline_hours' => 24, 'name' => 'Hacked Name', 'ssm_number' => '999', 'allow_counselor_freelance' => 0]);

        $this->acme->refresh();
        $this->assertSame('Acme Berhad', $this->acme->name);
        $this->assertSame('201901000123', $this->acme->ssm_number);

        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $as = $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee']);
        $as->get(route('org.settings'))->assertForbidden();
        $as->put(route('org.settings.update'), ['cancellation_deadline_hours' => 1])->assertForbidden();
    }
}
