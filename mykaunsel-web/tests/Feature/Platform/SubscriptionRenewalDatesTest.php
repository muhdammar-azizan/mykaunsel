<?php

namespace Tests\Feature\Platform;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Enums\OrgType;
use App\Exceptions\PricingChangeBlocked;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SubscriptionAssignmentService;
use App\Support\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * When a plan starts and ends (docs/PAYMENTS_PLAN.md, "Subscription end date", decided 2 November 2026): a year is a year (it ends the day before its
 * anniversary), a plan that is still running is not cut short by a renewal or a change (the new year starts the day after its last day, so the periods
 * neither overlap nor drift), and a plan that has ended (or none) starts today. Today is Monday 2 November 2026.
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

    private function assign(?SubscriptionPlan $plan = null, ?string $code = null): SubscriptionAssignment
    {
        return app(SubscriptionAssignmentService::class)->assign($this->admin, $this->acme->refresh(), ($plan ?? SubscriptionPlan::first() ?? $this->plan())->id, $code);
    }

    private function dates(SubscriptionAssignment $a): array
    {
        return [$a->starts_on->toDateString(), $a->ends_on->toDateString()];
    }

    private function days(SubscriptionAssignment $a): int
    {
        return (int) $a->starts_on->diffInDays($a->ends_on) + 1;
    }

    public function test_the_first_plan_starts_today_and_runs_for_exactly_a_year(): void
    {
        $first = $this->assign();

        $this->assertSame(['2026-11-02', '2027-11-01'], $this->dates($first));
        $this->assertSame(365, $this->days($first));
        $this->assertSame('2027-11-01', $this->acme->fresh()->subscription_ends_at->toDateString());
    }

    public function test_renewing_while_the_plan_runs_starts_the_day_after_its_last_day_with_no_gap_and_no_overlap(): void
    {
        $first = $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-15 10:00', 'Asia/Kuala_Lumpur'));          // about six weeks before it ends

        $renewal = $this->assign();

        $this->assertSame(['2027-11-02', '2028-11-01'], $this->dates($renewal));
        $this->assertSame(366, $this->days($renewal));                                       // 2028 has a 29 February: a year is still a year
        $this->assertSame($first->ends_on->copy()->addDay()->toDateString(), $renewal->starts_on->toDateString());
        $this->assertSame('2028-11-01', $this->acme->fresh()->subscription_ends_at->toDateString());
        $this->assertSame('2027-11-02', $renewal->invoice->period_starts_on->toDateString());

        $org = $this->acme->fresh();
        $this->assertTrue(Subscription::allows($org, '2027-11-01'));
        $this->assertTrue(Subscription::allows($org, '2027-11-02'));
        $this->assertTrue(Subscription::allows($org, '2028-11-01'));
        $this->assertFalse(Subscription::allows($org, '2028-11-02'));
    }

    public function test_year_after_year_the_last_day_does_not_drift(): void
    {
        $this->assign();
        $ends = [];

        foreach (['2027-09-01', '2028-09-01', '2029-09-01'] as $day) {
            Carbon::setTestNow(Carbon::parse($day.' 10:00', 'Asia/Kuala_Lumpur'));
            $ends[] = $this->assign()->ends_on->toDateString();
        }

        $this->assertSame(['2028-11-01', '2029-11-01', '2030-11-01'], $ends);
    }

    public function test_a_plan_whose_last_day_is_today_is_still_running(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-11-01 23:30', 'Asia/Kuala_Lumpur'));

        $this->assertSame(['2027-11-02', '2028-11-01'], $this->dates($this->assign()));
    }

    public function test_a_plan_that_has_ended_starts_again_today(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-12-20 10:00', 'Asia/Kuala_Lumpur'));

        $this->assertSame(['2027-12-20', '2028-12-19'], $this->dates($this->assign()));
    }

    public function test_changing_to_another_plan_during_the_term_keeps_the_term_and_the_new_plan_follows(): void
    {
        $this->assign($this->plan('Starter'));
        Carbon::setTestNow(Carbon::parse('2027-03-01 10:00', 'Asia/Kuala_Lumpur'));

        $bigger = $this->assign($this->plan('Bigger', 240000));

        $this->assertSame(['2027-11-02', '2028-11-01'], $this->dates($bigger));
        $this->assertSame($bigger->id, $this->acme->fresh()->current_assignment_id);
    }

    // ---- a waiting renewal is replaced, not doubled ------------------------------------------------------------------

    public function test_a_second_renewal_replaces_the_one_that_is_waiting_voids_its_invoice_and_gives_back_its_promo_use(): void
    {
        $this->assign();
        PromoCode::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'valid_from' => now()->subDay(), 'valid_until' => now()->addYear(), 'applies_to' => 'all', 'usage_limit' => 5, 'version' => 1]);
        Carbon::setTestNow(Carbon::parse('2027-09-01 10:00', 'Asia/Kuala_Lumpur'));
        $first = $this->assign(code: 'SAVE10');
        $this->assertSame(1, PromoCode::where('code', 'SAVE10')->value('used_count'));
        $service = app(SubscriptionAssignmentService::class);

        $quote = $service->quote($this->acme->fresh(), SubscriptionPlan::first()->id, null);
        $this->assertSame('2027-11-02', $quote['starts_on']->toDateString());
        $this->assertStringContainsString('replaces it', $quote['warnings'][0]);
        $this->assertStringContainsString('2 Nov 2027', $quote['warnings'][0]);

        $second = $this->assign($this->plan('Bigger', 240000));

        $this->assertSame('2027-11-02', $second->starts_on->toDateString());
        $this->assertSame($second->id, $this->acme->fresh()->current_assignment_id);
        $this->assertSame(Invoice::VOID, $first->invoice->fresh()->status);
        $this->assertSame(0, PromoCode::where('code', 'SAVE10')->value('used_count'));
        $this->assertSame(1, AuditLog::where('action', 'subscription.renewal_replaced')->count());
        $this->assertSame(Invoice::UNPAID, $second->invoice->status);
    }

    public function test_a_renewal_that_has_already_been_paid_can_not_be_replaced(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-01 10:00', 'Asia/Kuala_Lumpur'));
        $first = $this->assign();
        app(InvoiceService::class)->markPaid($first->invoice, $this->admin, 'BANK-1', Carbon::parse('2027-09-01'));

        try {
            $this->assign();
            $this->fail('A paid renewal was replaced.');
        } catch (PricingChangeBlocked $e) {
            $this->assertStringContainsString('already been paid', $e->getMessage() ?: implode(' ', $e->messages ?? []));
        }

        $this->assertSame($first->id, $this->acme->fresh()->current_assignment_id);
        $this->assertSame(0, AuditLog::where('action', 'subscription.renewal_replaced')->count());
    }

    // ---- what the pages say --------------------------------------------------------------------------------------

    public function test_the_pages_say_starts_for_a_renewal_that_has_not_begun_and_when_the_current_plan_runs_until(): void
    {
        $this->assign();
        Carbon::setTestNow(Carbon::parse('2027-09-15 10:00', 'Asia/Kuala_Lumpur'));
        $renewal = $this->assign();
        $this->assertFalse($renewal->hasStarted());

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $platform = Organization::create(['name' => 'MyKaunsel Platform', 'org_type' => OrgType::Platform, 'access_model' => AccessModel::Open, 'subscription_status' => OrgStatus::Active]);
        Membership::create(['user_id' => $admin->id, 'org_id' => $platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->actingAs($admin)->withSession(['current_org_id' => $platform->id, 'current_role' => 'platform_admin'])
            ->get(route('platform.organizations.show', $this->acme))->assertOk()->assertSee('Starts')->assertSee('2 Nov 2027');

        $hr = User::factory()->create(['email_verified_at' => now()]);
        Membership::create(['user_id' => $hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->flushSession();
        $this->actingAs($hr)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'org_admin'])
            ->get(route('org.subscription'))->assertOk()->assertSee('Your current plan keeps running until 1 Nov 2027');
    }

    public function test_the_days_left_to_the_last_day_are_counted_in_one_place(): void
    {
        $this->assign();                                          // last day 1 Nov 2027
        $org = $this->acme->fresh();

        $this->assertSame(364, $org->daysLeft());

        Carbon::setTestNow(Carbon::parse('2027-11-01 23:59', 'Asia/Kuala_Lumpur'));
        $this->assertSame(0, $org->daysLeft());
        $this->assertSame('ending_soon', $org->subscriptionState());

        Carbon::setTestNow(Carbon::parse('2027-11-02 00:01', 'Asia/Kuala_Lumpur'));
        $this->assertSame(-1, $org->daysLeft());
        $this->assertSame('ended', $org->subscriptionState());

        $this->assertNull(Organization::create(['name' => 'None', 'org_type' => OrgType::Corporate, 'access_model' => AccessModel::Closed, 'subscription_status' => OrgStatus::Active])->daysLeft());
    }
}
