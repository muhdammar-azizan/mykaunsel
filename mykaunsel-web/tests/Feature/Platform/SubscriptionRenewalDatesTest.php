<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Models\Organization;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionAssignmentService;
use App\Support\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * When a plan starts and ends (docs/PAYMENTS_PLAN.md, "Subscription end date", decided 2 November 2026): a plan that is still running is not cut short
 * by a renewal or a change, the new year starts the day after its last day; a plan that has ended (or none) starts today. Today is Monday 2 November 2026.
 */
class SubscriptionRenewalDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $acme;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Kuala_Lumpur'));
        $this->admin = User::factory()->create();
        $this->acme = Organization::create(['name' => 'Acme Berhad', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function plan(string $name = 'Starter', int $price = 120000): SubscriptionPlan
    {
        return SubscriptionPlan::create(['org_type' => 'corporate', 'name' => $name, 'min_members' => 1, 'max_members' => 500, 'price_cents' => $price, 'active' => true, 'version' => 1]);
    }

    private function assign(?SubscriptionPlan $plan = null): SubscriptionAssignment
    {
        return app(SubscriptionAssignmentService::class)->assign($this->admin, $this->acme->refresh(), ($plan ?? SubscriptionPlan::first() ?? $this->plan())->id, null);
    }

    private function dates(SubscriptionAssignment $a): array
    {
        return [$a->starts_on->toDateString(), $a->ends_on->toDateString()];
    }

    public function test_the_first_plan_starts_today_and_runs_for_a_year(): void
    {
        $this->assertSame(['2026-11-02', '2027-11-02'], $this->dates($this->assign()));
        $this->assertSame('2027-11-02', $this->acme->fresh()->subscription_ends_at->toDateString());
    }

    public function test_renewing_while_the_plan_runs_starts_the_year_after_its_last_day_and_no_day_is_lost(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-15 10:00', 'Asia/Kuala_Lumpur'));          // about seven weeks before it ends

        $renewal = $this->assign();

        $this->assertSame(['2027-11-03', '2028-11-03'], $this->dates($renewal));
        $this->assertSame('2028-11-03', $this->acme->fresh()->subscription_ends_at->toDateString());
        $this->assertSame('2027-11-03', $renewal->invoice->period_starts_on->toDateString());

        // The old plan's last day and the new plan's first day are next to each other, and the whole stretch is inside the paid period.
        $org = $this->acme->fresh();
        $this->assertTrue(Subscription::allows($org, '2027-11-02'));
        $this->assertTrue(Subscription::allows($org, '2027-11-03'));
        $this->assertTrue(Subscription::allows($org, '2028-11-03'));
        $this->assertFalse(Subscription::allows($org, '2028-11-04'));
    }

    public function test_a_plan_whose_last_day_is_today_is_still_running(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-11-02 23:30', 'Asia/Kuala_Lumpur'));

        $this->assertSame(['2027-11-03', '2028-11-03'], $this->dates($this->assign()));
    }

    public function test_a_plan_that_has_ended_starts_again_today(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-12-20 10:00', 'Asia/Kuala_Lumpur'));

        $this->assertSame(['2027-12-20', '2028-12-20'], $this->dates($this->assign()));
    }

    public function test_changing_to_another_plan_during_the_term_keeps_the_term_and_the_new_plan_follows(): void
    {
        $this->assign($this->plan('Starter'));
        Carbon::setTestNow(Carbon::parse('2027-03-01 10:00', 'Asia/Kuala_Lumpur'));

        $bigger = $this->assign($this->plan('Bigger', 240000));

        $this->assertSame(['2027-11-03', '2028-11-03'], $this->dates($bigger));
        $this->assertSame($bigger->id, $this->acme->fresh()->current_assignment_id);
    }

    public function test_a_second_renewal_before_the_first_starts_starts_at_the_same_time_and_warns(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-01 10:00', 'Asia/Kuala_Lumpur'));
        $first = $this->assign();
        $service = app(SubscriptionAssignmentService::class);

        $quote = $service->quote($this->acme->fresh(), SubscriptionPlan::first()->id, null);

        $this->assertSame('2027-11-03', $quote['starts_on']->toDateString());
        $this->assertCount(1, $quote['warnings']);
        $this->assertStringContainsString('already waiting', $quote['warnings'][0]);
        $this->assertStringContainsString('3 Nov 2027', $quote['warnings'][0]);
        $this->assertSame('2027-11-03', $this->assign()->starts_on->toDateString());
        $this->assertNotSame($first->id, $this->acme->fresh()->current_assignment_id);
    }

    public function test_the_pages_say_starts_for_a_renewal_that_has_not_begun(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-15 10:00', 'Asia/Kuala_Lumpur'));
        $this->assign();

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        \App\Models\Membership::create(['user_id' => $admin->id, 'org_id' => $platform->id, 'role' => \App\Enums\MembershipRole::PlatformAdmin, 'status' => \App\Enums\MembershipStatus::Active, 'joined_at' => now()]);

        $this->actingAs($admin)->withSession(['current_org_id' => $platform->id, 'current_role' => 'platform_admin'])
            ->get(route('platform.organizations.show', $this->acme))->assertOk()->assertSee('Starts')->assertSee('3 Nov 2027');
    }
}
