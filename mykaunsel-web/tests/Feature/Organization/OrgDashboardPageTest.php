<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The organization admin's Dashboard. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgDashboardPageTest extends TestCase
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
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function as()
    {
        return $this->actingAs($this->hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin']);
    }

    private function person(MembershipStatus $status, MembershipRole $role = MembershipRole::Employee, ?Organization $org = null, ?string $joined = null): Membership
    {
        return Membership::create(['user_id' => User::factory()->create()->id, 'org_id' => ($org ?? $this->acme)->id, 'role' => $role, 'status' => $status, 'joined_at' => $joined ?? now()]);
    }

    private function sessions(int $n, string $day, ?Organization $org = null, BookingStatus $status = BookingStatus::Completed): void
    {
        $counselor = $this->counselor('Aina '.uniqid(), [$org ?? $this->acme]);

        for ($i = 0; $i < $n; $i++) {
            $this->book($this->slot($counselor, $org ?? $this->acme, $day.' '.(8 + $i).':00'), null, $status);
        }
    }

    public function test_the_counts_are_this_organizations_own_people(): void
    {
        $other = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $this->person(MembershipStatus::Active);
        $this->person(MembershipStatus::Active, MembershipRole::Staff);
        $this->person(MembershipStatus::PendingApproval);
        $this->person(MembershipStatus::Alumni);
        $this->person(MembershipStatus::Active, MembershipRole::Counselor);
        $this->person(MembershipStatus::Active, org: $other);

        $page = $this->as()->get(route('org.dashboard'))->assertOk();

        $page->assertSeeInOrder(['Active members', '2', 'Waiting for your approval', '1', 'Counselors', '1']);
    }

    public function test_sessions_this_month_and_last_month_hide_small_numbers_and_never_count_cancelled_ones(): void
    {
        $this->sessions(6, '2026-11-01');
        $this->sessions(2, '2026-10-20');
        $this->sessions(3, '2026-11-01', status: BookingStatus::Cancelled);
        $this->sessions(9, '2026-11-01', $this->org('Beta', AccessModel::Closed, OrgType::Corporate));

        $page = $this->as()->get(route('org.dashboard'))->assertOk();

        $page->assertSee('Sessions in November')->assertSeeText('6 sessions in November')->assertSeeText('fewer than 5 sessions in October')->assertDontSeeText('9 sessions');
    }

    public function test_a_comparison_is_shown_only_when_both_months_are_large_enough(): void
    {
        $this->sessions(8, '2026-11-01');
        $this->sessions(6, '2026-10-20');

        $this->as()->get(route('org.dashboard'))->assertSee('2 more than in October');
    }

    public function test_notices_for_a_waiting_person_a_missing_plan_and_an_ended_plan(): void
    {
        $this->person(MembershipStatus::PendingApproval, joined: now()->subDays(5)->toDateTimeString());

        $this->as()->get(route('org.dashboard'))->assertSee('been waiting for your approval for 5 days or more')->assertSee('no subscription plan yet');

        $plan = SubscriptionPlan::create(['org_type' => 'corporate', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true, 'version' => 1]);
        $admin = User::factory()->create();
        app(SubscriptionAssignmentService::class)->assign($admin, $this->acme, $plan->id, null);
        $this->acme->refresh()->update(['subscription_ends_at' => '2026-10-01']);

        $this->as()->get(route('org.dashboard'))->assertSee('Your subscription ended on 1 Oct 2026')->assertSee('waiting for payment')->assertDontSee('no subscription plan yet');
    }

    public function test_no_name_of_a_client_or_a_counselor_is_on_the_dashboard_and_other_roles_are_refused(): void
    {
        $this->sessions(6, '2026-11-01');

        $this->as()->get(route('org.dashboard'))->assertOk()->assertDontSee('Aina')->assertDontSee('Siti');

        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.dashboard'))->assertForbidden();
    }
}
