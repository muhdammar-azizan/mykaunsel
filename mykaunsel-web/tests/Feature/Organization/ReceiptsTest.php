<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\Payments\InvoicePayments;
use App\Services\Payments\SimulatedGateway;
use App\Services\PlatformSettings;
use App\Services\ReceiptPdf;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** Receipts: the proof an invoice was paid, a document of its own with its own number. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class ReceiptsTest extends TestCase
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

        app(PlatformSettings::class)->setInvoiceSettings($this->platformAdmin, [
            'tax_name' => 'SST', 'tax_rate_percent' => '8', 'due_days' => 14, 'seller_name' => 'MyKaunsel Sdn Bhd', 'seller_address' => "1 Jalan Contoh\n25000 Kuantan",
            'seller_ssm' => '202301000001', 'seller_tax_no' => 'W10-0000-00000000', 'bank_name' => 'Bank Contoh', 'bank_account_name' => 'MyKaunsel Sdn Bhd', 'bank_account_no' => '1234567890',
        ]);
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

    private function invoice(?Organization $org = null, ?string $code = null): Invoice
    {
        $org ??= $this->acme;
        $plan = SubscriptionPlan::query()->where('org_type', $org->org_type->value)->first()
            ?? SubscriptionPlan::create(['org_type' => $org->org_type->value, 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true, 'version' => 1]);
        app(SubscriptionAssignmentService::class)->assign($this->platformAdmin, $org, $plan->id, $code);

        return Invoice::where('org_id', $org->id)->latest('id')->firstOrFail();
    }

    private function payOnline(Invoice $invoice): void
    {
        $payments = app(InvoicePayments::class);
        $try = $payments->start($invoice, $this->platformAdmin);
        $payments->settle($try, 'paid', app(SimulatedGateway::class)->sign($try, 'paid'));
    }

    // ---- when a receipt is made ---------------------------------------------------------------------------------

    public function test_paying_online_makes_one_receipt_with_the_invoices_snapshot(): void
    {
        $invoice = $this->invoice();
        $this->assertNull($invoice->receipt);

        $this->payOnline($invoice);

        $receipt = Receipt::sole();
        $this->assertSame('RCT-2026-0001', $receipt->number);
        $this->assertSame($invoice->id, $receipt->invoice_id);
        $this->assertSame($this->acme->id, $receipt->org_id);
        $this->assertSame(129600, $receipt->total_cents);
        $this->assertSame(9600, $receipt->tax_cents);
        $this->assertSame('SST', $receipt->tax_name);
        $this->assertSame('simulated', $receipt->payment_method);
        $this->assertSame('MyKaunsel Sdn Bhd', $receipt->seller['seller_name']);
        $this->assertSame('Acme Berhad', $receipt->buyer['name']);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $receipt->public_id);
        $this->assertSame(1, AuditLog::where('action', 'receipt.issued')->count());
    }

    public function test_marking_paid_by_hand_makes_the_receipt_too(): void
    {
        $invoice = $this->invoice();

        app(InvoiceService::class)->markPaid($invoice, $this->platformAdmin, 'BANK-REF-1', Carbon::parse('2026-11-01'));

        $receipt = Receipt::sole();
        $this->assertSame('manual', $receipt->payment_method);
        $this->assertSame('BANK-REF-1', $receipt->payment_reference);
        $this->assertSame('2026-10-31', $receipt->paid_at->format('Y-m-d'));    // the chosen day, as the first moment of that day in Malaysia (UTC)
    }

    public function test_a_second_payment_of_the_same_invoice_makes_no_second_receipt(): void
    {
        $invoice = $this->invoice();
        $payments = app(InvoicePayments::class);
        $first = $payments->start($invoice, $this->platformAdmin);
        $second = $payments->start($invoice, $this->platformAdmin);

        $payments->settle($first, 'paid', app(SimulatedGateway::class)->sign($first, 'paid'));
        $payments->settle($second, 'paid', app(SimulatedGateway::class)->sign($second, 'paid'));
        $payments->settle($first, 'paid', app(SimulatedGateway::class)->sign($first, 'paid'));

        $this->assertSame(1, Receipt::count());
        $this->assertSame(1, AuditLog::where('action', 'invoice.paid_twice')->count());
    }

    public function test_an_unpaid_a_voided_and_a_free_invoice_have_no_receipt(): void
    {
        $unpaid = $this->invoice();
        $this->assertNull(app(\App\Services\ReceiptService::class)->issueFor($unpaid));

        app(InvoiceService::class)->void($unpaid, $this->platformAdmin, 'Mistake');
        $this->assertNull(app(\App\Services\ReceiptService::class)->issueFor($unpaid->fresh()));

        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        PromoCode::create(['code' => 'FREE100', 'type' => 'percent', 'value' => 100, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(5), 'applies_to' => 'all', 'version' => 1]);
        $free = $this->invoice($beta, 'FREE100');

        $this->assertSame(Invoice::PAID, $free->status);
        $this->assertSame(0, $free->total_cents);
        $this->assertSame(0, Receipt::count());
    }

    // ---- the number ---------------------------------------------------------------------------------------------

    public function test_the_number_counts_up_inside_a_year_and_starts_again_in_the_next_one(): void
    {
        $first = $this->invoice();
        $second = $this->invoice($this->org('Beta', AccessModel::Closed, OrgType::Corporate));
        $this->payOnline($first);
        $this->payOnline($second);

        $this->assertSame(['RCT-2026-0001', 'RCT-2026-0002'], Receipt::orderBy('id')->pluck('number')->all());

        Carbon::setTestNow(Carbon::parse('2027-01-05 10:00', 'Asia/Kuala_Lumpur'));
        $third = $this->invoice($this->org('Gamma', AccessModel::Closed, OrgType::Corporate));
        $this->payOnline($third);

        $this->assertSame('RCT-2027-0001', Receipt::orderByDesc('id')->value('number'));
    }

    public function test_a_receipt_keeps_what_it_said_when_things_change_later(): void
    {
        $invoice = $this->invoice();
        $this->payOnline($invoice);

        $this->acme->update(['name' => 'Acme Renamed Berhad']);
        app(PlatformSettings::class)->setInvoiceSettings($this->platformAdmin, ['tax_name' => 'SST', 'tax_rate_percent' => '0', 'due_days' => 14, 'seller_name' => 'Someone Else Sdn Bhd']);

        $receipt = Receipt::sole()->fresh();
        $this->assertSame('Acme Berhad', $receipt->buyer['name']);
        $this->assertSame('MyKaunsel Sdn Bhd', $receipt->seller['seller_name']);
        $this->assertSame(9600, $receipt->tax_cents);
    }

    public function test_invoices_paid_before_receipts_existed_get_theirs_dated_the_day_they_were_paid(): void
    {
        $old = $this->invoice();
        $old->forceFill(['status' => Invoice::PAID, 'paid_at' => '2026-03-10 02:00:00', 'payment_method' => 'manual', 'payment_reference' => 'OLD-1'])->save();
        $newer = $this->invoice($this->org('Beta', AccessModel::Closed, OrgType::Corporate));
        $newer->forceFill(['status' => Invoice::PAID, 'paid_at' => '2026-06-01 02:00:00', 'payment_method' => 'manual'])->save();
        $free = $this->invoice($this->org('Gamma', AccessModel::Closed, OrgType::Corporate));
        $free->forceFill(['status' => Invoice::PAID, 'paid_at' => '2026-05-01 02:00:00', 'total_cents' => 0])->save();
        $this->assertSame(0, Receipt::count());

        // Running the migration again, as it runs on a database that already holds paid invoices.
        $migration = require database_path('migrations/2026_11_24_000001_create_receipts_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(['RCT-2026-0001', 'RCT-2026-0002'], Receipt::orderBy('id')->pluck('number')->all());
        $this->assertSame($old->id, Receipt::where('number', 'RCT-2026-0001')->value('invoice_id'));
        $this->assertSame('OLD-1', Receipt::where('number', 'RCT-2026-0001')->value('payment_reference'));
        $this->assertSame('2026-03-10', Receipt::where('number', 'RCT-2026-0001')->first()->paid_at->format('Y-m-d'));

        // The next receipt carries on from the counter.
        $this->payOnline($this->invoice($this->org('Delta', AccessModel::Closed, OrgType::Corporate)));
        $this->assertSame('RCT-2026-0003', Receipt::orderByDesc('id')->value('number'));
    }

    // ---- the documents ------------------------------------------------------------------------------------------

    public function test_the_html_of_the_receipt_pdf_says_who_paid_what_for_which_invoice_and_how(): void
    {
        $invoice = $this->invoice();
        $this->payOnline($invoice);
        $receipt = Receipt::sole();

        $html = view('pdf.receipt', ['receipt' => $receipt->load('invoice')])->render();

        foreach (['Receipt', 'RCT-2026-0001', 'For invoice', $invoice->number, 'Received by', 'MyKaunsel Sdn Bhd', 'Received from', 'Acme Berhad', 'Amount received', 'RM 1,296.00',
            'Online payment (test)', 'SIM-', 'SST (8%)', 'RM 96.00', 'Total paid', 'PAID'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        $this->assertStringContainsString('Receipt RCT-2026-0001', view('pdf.invoice', ['invoice' => $invoice->fresh()->load('receipt')])->render());
        $this->assertStringStartsWith('%PDF-', app(ReceiptPdf::class)->render($receipt));
    }

    public function test_an_organization_admin_sees_and_downloads_its_own_receipt(): void
    {
        $invoice = $this->invoice();
        $this->payOnline($invoice);
        $receipt = Receipt::sole();

        $this->hr()->get(route('org.subscription'))->assertSee(route('org.subscription.receipt', $receipt->public_id), false);
        $this->hr()->get(route('org.subscription.invoice', $invoice->public_id))->assertSee('Receipt RCT-2026-0001');
        $this->hr()->get(route('org.subscription.receipt', $receipt->public_id))->assertOk()->assertSee('Receipt RCT-2026-0001')->assertSee('RM 1,296.00')->assertSee('Download PDF');

        $pdf = $this->hr()->get(route('org.subscription.receipt.pdf', $receipt->public_id))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="RCT-2026-0001.pdf"', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_another_organizations_receipt_is_a_404_and_other_roles_are_refused(): void
    {
        $beta = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $this->payOnline($this->invoice($beta));
        $theirs = Receipt::sole();

        $this->hr()->get(route('org.subscription.receipt', $theirs->public_id))->assertNotFound();
        $this->hr()->get(route('org.subscription.receipt.pdf', $theirs->public_id))->assertNotFound();

        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.subscription.receipt', $theirs->public_id))->assertForbidden();
    }

    public function test_a_platform_admin_downloads_any_receipt_from_the_invoice_page(): void
    {
        $invoice = $this->invoice();
        $this->payOnline($invoice);
        $receipt = Receipt::sole();
        $admin = fn () => $this->actingAs($this->platformAdmin)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin']);

        $admin()->get(route('platform.invoices.show', $invoice))->assertSee('receipt RCT-2026-0001', false)->assertSee(route('platform.receipts.pdf', $receipt), false);

        $pdf = $admin()->get(route('platform.receipts.pdf', $receipt))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
