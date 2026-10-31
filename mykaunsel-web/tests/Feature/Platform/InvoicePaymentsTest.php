<?php

namespace Tests\Feature\Platform;

use App\Exceptions\InvoiceNotOpen;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Services\InvoiceService;
use App\Services\Payments\InvoicePayments;
use App\Services\Payments\SimulatedGateway;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\Feature\Counselor\CounselorTestCase;

/** Paying an invoice on the simulated provider's page (docs/PAYMENTS_PLAN.md): a signed result, once, for the right amount. */
class InvoicePaymentsTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSamples();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Asia/Kuala_Lumpur'));
    }

    private function invoice(): Invoice
    {
        $org = Organization::where('org_type', 'university')->first();
        $org->update(['subscription_status' => 'active']);
        $plan = SubscriptionPlan::create(['org_type' => 'university', 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true, 'version' => 1]);
        app(SubscriptionAssignmentService::class)->assign($this->admin, $org, $plan->id, null);

        return Invoice::sole();
    }

    private function sign(InvoicePayment $payment, string $outcome): string
    {
        return app(SimulatedGateway::class)->sign($payment, $outcome);
    }

    public function test_a_signed_success_marks_the_invoice_paid_once(): void
    {
        $invoice = $this->invoice();
        $payments = app(InvoicePayments::class);
        $payment = $payments->start($invoice, $this->admin);

        $payments->settle($payment, 'paid', $this->sign($payment, 'paid'));
        $payments->settle($payment, 'paid', $this->sign($payment, 'paid'));

        $invoice->refresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('simulated', $invoice->payment_method);
        $this->assertSame(InvoicePayment::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'invoice.paid_online')->count());
    }

    public function test_a_wrong_signature_or_amount_changes_nothing(): void
    {
        $invoice = $this->invoice();
        $payments = app(InvoicePayments::class);
        $payment = $payments->start($invoice, $this->admin);

        try {
            $payments->settle($payment, 'paid', 'forged');
            $this->fail('A forged result was accepted.');
        } catch (InvalidArgumentException) {
        }

        $payment->update(['amount_cents' => 1]);
        $this->expectException(InvalidArgumentException::class);

        try {
            $payments->settle($payment->fresh(), 'paid', $this->sign($payment->fresh(), 'paid'));
        } finally {
            $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);
        }
    }

    public function test_a_failed_try_leaves_the_invoice_open_and_a_new_try_can_succeed(): void
    {
        $invoice = $this->invoice();
        $payments = app(InvoicePayments::class);
        $first = $payments->start($invoice, $this->admin);
        $payments->settle($first, 'failed', $this->sign($first, 'failed'));

        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);
        $this->assertSame('Failed', $first->fresh()->statusLabel());

        $second = $payments->start($invoice, $this->admin);
        $payments->settle($second, 'paid', $this->sign($second, 'paid'));

        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
    }

    public function test_a_payment_after_a_manual_payment_is_flagged_and_the_invoice_is_not_changed(): void
    {
        $invoice = $this->invoice();
        $payments = app(InvoicePayments::class);
        $payment = $payments->start($invoice, $this->admin);
        app(InvoiceService::class)->markPaid($invoice, $this->admin, 'BANK-1', now());

        $payments->settle($payment, 'paid', $this->sign($payment, 'paid'));

        $this->assertSame('manual', $invoice->fresh()->payment_method);
        $this->assertSame(1, AuditLog::where('action', 'invoice.paid_twice')->count());
    }

    public function test_no_try_can_start_for_a_paid_or_void_invoice(): void
    {
        $invoice = $this->invoice();
        app(InvoiceService::class)->void($invoice, $this->admin, 'Mistake');

        $this->expectException(InvoiceNotOpen::class);
        app(InvoicePayments::class)->start($invoice->fresh(), $this->admin);
    }

    public function test_the_pay_page_and_its_buttons_work_end_to_end(): void
    {
        $invoice = $this->invoice();
        $payment = app(InvoicePayments::class)->start($invoice, $this->admin);

        $this->get(route('pay.show', $payment))->assertOk()->assertSee($invoice->number)->assertSee('Pay now');
        $this->post(route('pay.result', $payment), ['outcome' => 'paid', 'signature' => 'forged'])->assertSessionHas('error');
        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);

        $this->post(route('pay.result', $payment), ['outcome' => 'paid', 'signature' => $this->sign($payment, 'paid')])->assertRedirect(route('pay.show', $payment));
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);
        $this->get(route('pay.show', $payment))->assertSee('Payment received');
    }

    public function test_an_expired_try_is_refused(): void
    {
        $invoice = $this->invoice();
        $payment = app(InvoicePayments::class)->start($invoice, $this->admin);
        Carbon::setTestNow(now()->addMinutes(61));

        $this->post(route('pay.result', $payment), ['outcome' => 'paid', 'signature' => $this->sign($payment, 'paid')])->assertSessionHas('error');
        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);
    }

    public function test_an_admin_can_open_a_payment_page_and_sees_the_tries(): void
    {
        $invoice = $this->invoice();

        $response = $this->as()->post(route('platform.invoices.pay-link', $invoice));
        $payment = InvoicePayment::sole();
        $response->assertRedirect(route('pay.show', $payment));

        $this->as()->get(route('platform.invoices.show', $invoice))->assertOk()->assertSee('Online payments')->assertSee('Waiting');
    }

    public function test_the_dashboard_counts_overdue_invoices_and_ended_plans(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['due_on' => '2026-10-01']);
        Organization::find($invoice->org_id)->update(['subscription_ends_at' => '2026-10-01']);
        $invoice->assignment->update(['ends_on' => '2026-10-01']);

        $attention = app(\App\Services\DashboardService::class)->attention();
        $types = collect($attention['items'])->pluck('kind')->all();

        $this->assertContains('invoices_overdue', $types);
        $this->assertContains('subscriptions_ended', $types);
        $this->assertSame('ended', Organization::find($invoice->org_id)->subscriptionState());
    }
}
