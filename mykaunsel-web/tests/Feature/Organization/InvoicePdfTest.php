<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InvoicePdf;
use App\Services\InvoiceService;
use App\Services\PlatformSettings;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The PDF file of an invoice: for the organization that owns it and for the Platform Admin. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class InvoicePdfTest extends TestCase
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

    private function admin()
    {
        return $this->actingAs($this->platformAdmin)->withSession(['current_org_id' => $this->platform->id, 'current_role' => 'platform_admin']);
    }

    private function invoice(?Organization $org = null): Invoice
    {
        $org ??= $this->acme;
        $plan = SubscriptionPlan::create(['org_type' => $org->org_type->value, 'name' => 'Starter', 'min_members' => 1, 'max_members' => 500, 'price_cents' => 120000, 'active' => true, 'version' => 1]);
        app(SubscriptionAssignmentService::class)->assign($this->platformAdmin, $org, $plan->id, null);

        return Invoice::where('org_id', $org->id)->latest('id')->firstOrFail();
    }

    // ---- the document ---------------------------------------------------------------------------------------------

    public function test_the_html_of_an_unpaid_invoice_has_every_line_the_tax_and_how_to_pay(): void
    {
        $invoice = $this->invoice();

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();

        foreach (['INV-2026-0001', 'MyKaunsel Sdn Bhd', '202301000001', 'W10-0000-00000000', 'Acme Berhad', 'Starter — yearly subscription', 'SST (8%)', 'RM 96.00', 'RM 1,296.00', 'UNPAID',
            'Pay by bank transfer', 'Bank Contoh', '1234567890', 'INV-2026-0001 as the reference'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_a_paid_invoice_says_when_how_and_with_what_reference_and_has_no_bank_box(): void
    {
        $invoice = $this->invoice();
        app(InvoiceService::class)->markPaid($invoice, $this->platformAdmin, 'BANK-REF-9', Carbon::parse('2026-11-01'));

        $html = view('pdf.invoice', ['invoice' => $invoice->fresh()])->render();

        $this->assertStringContainsString('PAID', $html);
        $this->assertStringContainsString('Paid on', $html);
        $this->assertStringContainsString('1 Nov 2026', $html);
        $this->assertStringContainsString('Bank transfer', $html);
        $this->assertStringContainsString('BANK-REF-9', $html);
        $this->assertStringNotContainsString('Pay by bank transfer', $html);
    }

    public function test_a_voided_invoice_is_marked_and_a_discount_has_its_own_line(): void
    {
        $invoice = $this->invoice();
        app(InvoiceService::class)->void($invoice, $this->platformAdmin, 'Mistake');
        $invoice->forceFill(['discount_cents' => 10000])->save();

        $html = view('pdf.invoice', ['invoice' => $invoice->fresh()])->render();

        $this->assertStringContainsString('VOID', $html);
        $this->assertStringContainsString('must not be paid', $html);
        $this->assertStringContainsString('- RM 100.00', $html);
    }

    public function test_what_people_typed_is_escaped_and_letters_with_marks_make_a_real_pdf(): void
    {
        $this->acme->update(['name' => '<script>alert(1)</script> Syarikat Ïñ Berhad']);
        $invoice = $this->invoice();

        $html = view('pdf.invoice', ['invoice' => $invoice])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $bytes = app(InvoicePdf::class)->render($invoice);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1500, strlen($bytes));
    }

    // ---- the organization ---------------------------------------------------------------------------------------

    public function test_an_organization_admin_downloads_the_pdf_of_its_own_invoice(): void
    {
        $invoice = $this->invoice();

        $response = $this->hr()->get(route('org.subscription.invoice.pdf', $invoice->public_id))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="INV-2026-0001.pdf"', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->hr()->get(route('org.subscription.invoice', $invoice->public_id))->assertSee(route('org.subscription.invoice.pdf', $invoice->public_id), false)->assertSee('Download PDF');
    }

    public function test_another_organizations_invoice_and_a_voided_one_are_a_404(): void
    {
        $other = $this->invoice($this->org('Beta', AccessModel::Closed, OrgType::Corporate));
        $this->hr()->get(route('org.subscription.invoice.pdf', $other->public_id))->assertNotFound();

        $mine = $this->invoice();
        app(InvoiceService::class)->void($mine, $this->platformAdmin, 'Mistake');
        $this->hr()->get(route('org.subscription.invoice.pdf', $mine->public_id))->assertNotFound();
    }

    public function test_other_roles_and_signed_out_visitors_get_nothing(): void
    {
        $invoice = $this->invoice();
        $employee = User::factory()->create();
        Membership::create(['user_id' => $employee->id, 'org_id' => $this->acme->id, 'role' => MembershipRole::Employee, 'status' => MembershipStatus::Active, 'joined_at' => now()]);

        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('org.subscription.invoice.pdf', $invoice->public_id))->assertForbidden();
        $this->actingAs($employee)->withSession(['current_org_id' => $this->acme->id, 'current_role' => 'employee'])->get(route('platform.invoices.pdf', $invoice->public_id))->assertForbidden();
    }

    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $invoice = $this->invoice();

        $this->get(route('org.subscription.invoice.pdf', $invoice->public_id))->assertRedirect();
        $this->get(route('platform.invoices.pdf', $invoice->public_id))->assertRedirect();
    }

    // ---- the Platform Admin ---------------------------------------------------------------------------------------

    public function test_a_platform_admin_downloads_any_invoice_including_a_voided_one(): void
    {
        $invoice = $this->invoice();
        app(InvoiceService::class)->void($invoice, $this->platformAdmin, 'Mistake');

        $response = $this->admin()->get(route('platform.invoices.pdf', $invoice->public_id))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->admin()->get(route('platform.invoices.show', $invoice))->assertSee(route('platform.invoices.pdf', $invoice), false)->assertSee('Download PDF');
    }
}
