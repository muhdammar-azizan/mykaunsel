<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Payments\SimulatedGateway;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The organization admin's Subscription page: plan, invoices and receipts, paying, asking to renew. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class OrgSubscriptionPageTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hr;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->hr = User::factory()->create(['name' => 'Hana HR', 'email_verified_at' => now()]);
        Membership::create(['user_id' => $this->hr->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::OrgAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->platformAdmin = User::factory()->create(['email_verified_at' => now()]);
        Membership::create(['user_id' => $this->platformAdmin->id, 'org_id' => $this->platform->id, 'role' => MembershipRole::PlatformAdmin, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
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

    private function plan(?Organization $org = null, int $price = 120000): Invoice
    {
        $org ??= $this->acme;
        $plan = SubscriptionPlan::create(['org_type' => $org->org_type->value, 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => $price, 'active' => true, 'version' => 1]);
        app(SubscriptionAssignmentService::class)->assign($this->platformAdmin, $org, $plan->id, null);

        return Invoice::where('org_id', $org->id)->latest('id')->firstOrFail();
    }

    public function test_the_page_shows_the_plan_the_state_and_only_this_organizations_invoices(): void
    {
        $invoice = $this->plan();
        $other = $this->plan($this->org('Beta', AccessModel::Closed, OrgType::Corporate));

        $this->hr()->get(route('org.subscription'))->assertOk()
            ->assertSee('Starter')->assertSee('RM 1,200.00')->assertSee('Active')->assertSee($invoice->number)->assertDontSee($other->number);
    }

    public function test_a_voided_invoice_is_not_shown_and_has_no_page(): void
    {
        $invoice = $this->plan();
        app(\App\Services\InvoiceService::class)->void($invoice, $this->platformAdmin, 'Mistake');

        $this->hr()->get(route('org.subscription'))->assertDontSee($invoice->number);
        $this->hr()->get(route('org.subscription.invoice', $invoice->public_id))->assertNotFound();
    }

    public function test_paying_opens_a_payment_page_and_the_signed_result_makes_the_receipt(): void
    {
        $invoice = $this->plan();

        $response = $this->hr()->post(route('org.subscription.pay', $invoice->public_id));
        $payment = InvoicePayment::sole();
        $response->assertRedirect(route('pay.show', $payment));
        $this->assertSame($this->hr->id, $payment->started_by);
        $this->assertSame(1, AuditLog::where('action', 'invoice.payment_started')->count());

        $this->post(route('pay.result', $payment), ['outcome' => 'paid', 'signature' => app(SimulatedGateway::class)->sign($payment, 'paid')]);

        $this->hr()->get(route('org.subscription'))->assertSee('Receipt')->assertSee('Paid');
        $this->hr()->get(route('org.subscription.invoice', $invoice->public_id))->assertOk()->assertSee('Invoice '.$invoice->number)->assertSee('Online (test page)')->assertSee($payment->fresh()->provider_reference)->assertSee('Receipt RCT-2026-0001');
    }

    public function test_a_paid_invoice_can_not_be_paid_again_and_another_organizations_invoice_is_a_404(): void
    {
        $invoice = $this->plan();
        app(\App\Services\InvoiceService::class)->markPaid($invoice, $this->platformAdmin, 'BANK-1', now());
        $other = $this->plan($this->org('Beta', AccessModel::Closed, OrgType::Corporate));

        $this->hr()->post(route('org.subscription.pay', $invoice->public_id))->assertSessionHasErrors('invoice');
        $this->assertSame(0, InvoicePayment::count());

        $this->hr()->post(route('org.subscription.pay', $other->public_id))->assertNotFound();
        $this->hr()->get(route('org.subscription.invoice', $other->public_id))->assertNotFound();
    }

    public function test_an_organization_with_no_plan_sees_the_plans_it_is_offered_and_asking_for_one_is_only_a_request(): void
    {
        $starter = SubscriptionPlan::create(['org_type' => OrgType::Corporate->value, 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true, 'version' => 1]);
        SubscriptionPlan::create(['org_type' => OrgType::Corporate->value, 'name' => 'Old plan', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 99900, 'active' => false, 'version' => 1]);
        SubscriptionPlan::create(['org_type' => OrgType::University->value, 'name' => 'Uni plan', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 50000, 'active' => true, 'version' => 1]);

        $this->hr()->get(route('org.subscription'))->assertOk()
            ->assertSee('Pick the plan that fits your organization')->assertSee('Starter')->assertSee('RM 1,200', false)->assertSee('without tax (SST)')
            ->assertDontSee('Old plan')->assertDontSee('Uni plan');

        // Asking is a request: nothing is assigned or charged, and the plan looked at is kept in the audit log.
        $this->hr()->post(route('org.subscription.renewal'), ['plan_id' => $starter->id])->assertSessionHas('status');

        $this->assertNull($this->acme->fresh()->current_assignment_id);
        $this->assertSame(0, Invoice::count());
        $this->assertSame($starter->id, AuditLog::where('action', 'subscription.renewal_requested')->first()->metadata['requested_plan_id'] ?? null);
        $this->hr()->get(route('org.subscription'))->assertDontSee('Pick the plan that fits your organization');
    }

    public function test_a_plan_the_organization_is_not_offered_is_not_written_into_the_request(): void
    {
        $uni = SubscriptionPlan::create(['org_type' => OrgType::University->value, 'name' => 'Uni plan', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 50000, 'active' => true, 'version' => 1]);

        $this->hr()->post(route('org.subscription.renewal'), ['plan_id' => $uni->id])->assertSessionHas('status');

        $this->assertArrayNotHasKey('requested_plan_id', AuditLog::where('action', 'subscription.renewal_requested')->first()->metadata ?? []);
    }

    public function test_asking_to_renew_flags_the_organization_tells_the_platform_admins_once_and_a_new_plan_clears_it(): void
    {
        $this->plan();

        $this->hr()->post(route('org.subscription.renewal'))->assertSessionHas('status');

        $this->acme->refresh();
        $this->assertNotNull($this->acme->renewal_requested_at);
        $this->assertSame($this->hr->id, (int) $this->acme->renewal_requested_by);
        $this->assertSame(1, AuditLog::where('action', 'subscription.renewal_requested')->count());
        $this->assertSame(1, UserNotification::query()->ownedBy($this->platformAdmin)->where('type', 'subscription.renewal_requested')->count());

        $this->hr()->post(route('org.subscription.renewal'))->assertSessionHasErrors('renewal');
        $this->assertSame(1, UserNotification::query()->ownedBy($this->platformAdmin)->where('type', 'subscription.renewal_requested')->count());
        $this->hr()->get(route('org.subscription'))->assertSee('You asked MyKaunsel to renew');

        $this->actingAs($this->platformAdmin)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin'])
            ->get(route('platform.organizations.show', $this->acme))->assertOk()->assertSee('Asked on 2 Nov 2026');

        Carbon::setTestNow(now()->addDay());
        $plan = SubscriptionPlan::first();
        app(SubscriptionAssignmentService::class)->assign($this->platformAdmin, $this->acme, $plan->id, null);
        $this->assertNull($this->acme->fresh()->renewal_requested_at);
    }

    public function test_an_ended_plan_is_shown_as_ended_and_other_roles_are_refused(): void
    {
        $this->plan();
        $this->acme->update(['subscription_ends_at' => '2026-10-01']);

        $this->hr()->get(route('org.subscription'))->assertSee('Ended')->assertSee('Your plan has ended');

        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.subscription'))->assertForbidden();
    }
}
