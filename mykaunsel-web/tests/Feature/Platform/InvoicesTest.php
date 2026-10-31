<?php

namespace Tests\Feature\Platform;

use App\Exceptions\InvoiceNotOpen;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\SubscriptionAssignment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PlatformSettings;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Counselor\CounselorTestCase;

/** Invoices of the yearly subscription (docs/PAYMENTS_PLAN.md): made with the plan, the tax line, marking paid by hand, voiding, the pages. */
class InvoicesTest extends CounselorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSamples();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Asia/Kuala_Lumpur'));
    }

    private function org(string $type = 'university'): Organization
    {
        $org = Organization::where('org_type', $type)->orderByRaw("subscription_status = 'active' desc")->first()
            ?? Organization::create(['name' => ucfirst($type).' Test Sdn Bhd', 'org_type' => $type, 'access_model' => 'closed', 'subscription_status' => 'active', 'ssm_number' => '201901000099']);
        $org->update(['subscription_status' => 'active']);

        return $org;
    }

    private function plan(int $price = 120000, string $type = 'university', string $name = 'Starter'): SubscriptionPlan
    {
        return SubscriptionPlan::create(['org_type' => $type, 'name' => $name, 'min_members' => 1, 'max_members' => 500, 'price_cents' => $price, 'active' => true, 'version' => 1]);
    }

    private function assign(Organization $org, SubscriptionPlan $plan, ?string $code = null): SubscriptionAssignment
    {
        return app(SubscriptionAssignmentService::class)->assign($this->admin, $org, $plan->id, $code);
    }

    private function settings(array $over = []): void
    {
        app(PlatformSettings::class)->setInvoiceSettings($this->admin, $over + [
            'tax_name' => 'SST', 'tax_rate_percent' => '8', 'due_days' => 14, 'seller_name' => 'MyKaunsel Sdn Bhd', 'seller_address' => "1 Jalan Contoh\n25000 Kuantan",
            'seller_ssm' => '202301000001', 'seller_tax_no' => 'W10-0000-00000000', 'bank_name' => 'Bank Contoh', 'bank_account_name' => 'MyKaunsel Sdn Bhd', 'bank_account_no' => '1234567890',
        ]);
    }

    // ---- making an invoice -------------------------------------------------------------------------------------------

    public function test_giving_a_plan_makes_an_invoice_with_a_tax_line_a_number_and_a_due_date(): void
    {
        $this->settings();
        $assignment = $this->assign($this->org(), $this->plan(120000));

        $invoice = Invoice::sole();

        $this->assertSame('INV-2026-0001', $invoice->number);
        $this->assertSame($assignment->id, $invoice->assignment_id);
        $this->assertSame(Invoice::UNPAID, $invoice->status);
        $this->assertSame(120000, $invoice->subtotal_cents);
        $this->assertSame(0, $invoice->discount_cents);
        $this->assertSame('SST', $invoice->tax_name);
        $this->assertSame(800, $invoice->tax_rate_bp);
        $this->assertSame(9600, $invoice->tax_cents);
        $this->assertSame(129600, $invoice->total_cents);
        $this->assertSame('2026-11-02', $invoice->issued_on->toDateString());
        $this->assertSame('2026-11-16', $invoice->due_on->toDateString());
        $this->assertSame($assignment->starts_on->toDateString(), $invoice->period_starts_on->toDateString());
        $this->assertSame('MyKaunsel Sdn Bhd', $invoice->seller['seller_name']);
        $this->assertSame($this->org()->name, $invoice->buyer['name']);
        $this->assertSame('MYR', $invoice->currency);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $invoice->public_id);
    }

    public function test_a_discount_comes_off_before_tax_and_tax_rounds_to_the_nearest_sen(): void
    {
        $this->settings(['tax_rate_percent' => '6.5']);
        PromoCode::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(5), 'applies_to' => 'all', 'version' => 1]);

        $this->assign($this->org(), $this->plan(99999), 'SAVE10');
        $invoice = Invoice::sole();

        $this->assertSame(99999, $invoice->subtotal_cents);
        $this->assertSame(10000, $invoice->discount_cents); // 9999.9 rounds to 10000
        $this->assertSame(89999, $invoice->subtotal_cents - $invoice->discount_cents);
        $this->assertSame(5850, $invoice->tax_cents);          // 89999 × 6.5% = 5849.935
        $this->assertSame(89999 + 5850, $invoice->total_cents);

        $this->assertSame(1, InvoiceService::tax(1, 5000), '0.5 of a sen rounds up');
        $this->assertSame(0, InvoiceService::tax(1, 4999), 'just under half rounds down');
        $this->assertSame(5, InvoiceService::tax(10, 5000));
        $this->assertSame(0, InvoiceService::tax(123456, 0));
    }

    public function test_a_total_of_nothing_is_paid_at_once(): void
    {
        $this->settings(['tax_rate_percent' => '0']);
        PromoCode::create(['code' => 'FREE', 'type' => 'percent', 'value' => 100, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(5), 'applies_to' => 'all', 'version' => 1]);

        $this->assign($this->org(), $this->plan(), 'FREE');
        $invoice = Invoice::sole();

        $this->assertSame(0, $invoice->total_cents);
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('no_charge', $invoice->payment_method);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_numbers_follow_one_another_and_start_again_each_year(): void
    {
        $this->settings();
        $plan = $this->plan();
        $this->assign($this->org(), $plan);
        $this->assign($this->org('corporate'), $this->plan(type: 'corporate', name: 'Corp'));

        $this->assertSame(['INV-2026-0001', 'INV-2026-0002'], Invoice::orderBy('id')->pluck('number')->all());

        Carbon::setTestNow(Carbon::parse('2027-01-05 10:00', 'Asia/Kuala_Lumpur'));
        $this->assign($this->org('clinic'), $this->plan(type: 'clinic', name: 'Clinic'));

        $this->assertSame('INV-2027-0001', Invoice::orderByDesc('id')->value('number'));
    }

    public function test_an_assignment_has_one_invoice_however_often_it_is_asked_for(): void
    {
        $this->settings();
        $assignment = $this->assign($this->org(), $this->plan());

        $again = app(InvoiceService::class)->issueForAssignment($assignment, $this->admin);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(Invoice::sole()->id, $again->id);
        $this->assertSame(1, DB::table('invoice_counters')->where('year', 2026)->value('last_number'));
    }

    public function test_changing_the_settings_afterwards_changes_no_invoice(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan(120000));

        $this->settings(['tax_rate_percent' => '0', 'seller_name' => 'Another Name']);

        $invoice = Invoice::sole();
        $this->assertSame(9600, $invoice->tax_cents);
        $this->assertSame('MyKaunsel Sdn Bhd', $invoice->seller['seller_name']);
    }

    public function test_a_failure_while_making_the_invoice_undoes_the_plan(): void
    {
        $this->settings();
        $this->app->bind(InvoiceService::class, fn () => new class extends InvoiceService
        {
            public function issueForAssignment(SubscriptionAssignment $assignment, ?User $actor): Invoice
            {
                throw new \RuntimeException('boom');
            }
        });

        try {
            $this->assign($this->org(), $this->plan());
            $this->fail('expected the failure');
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, SubscriptionAssignment::count());
        $this->assertNull($this->org()->fresh()->current_assignment_id);
    }

    public function test_a_clinic_gets_its_invoice_like_any_other_organization(): void
    {
        $this->settings();
        $this->assign($this->org('clinic'), $this->plan(50000, 'clinic', 'Clinic'));

        $this->assertSame('clinic', Invoice::sole()->buyer['type']);
    }

    // ---- marking paid and voiding -----------------------------------------------------------------------------------

    public function test_marking_paid_keeps_the_reference_the_day_and_who_did_it(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan());
        $invoice = Invoice::sole();

        app(InvoiceService::class)->markPaid($invoice, $this->admin, '  MBB-778899  ', Carbon::parse('2026-11-01', 'Asia/Kuala_Lumpur'));

        $invoice = $invoice->fresh();
        $this->assertSame(Invoice::PAID, $invoice->status);
        $this->assertSame('manual', $invoice->payment_method);
        $this->assertSame('MBB-778899', $invoice->payment_reference);
        $this->assertSame($this->admin->id, $invoice->paid_marked_by);
        $this->assertSame('2026-11-01', $invoice->paid_at->timezone('Asia/Kuala_Lumpur')->toDateString());
    }

    public function test_a_paid_invoice_can_not_be_paid_again_or_voided_and_a_void_one_can_not_be_paid(): void
    {
        $this->settings();
        $service = app(InvoiceService::class);
        $this->assign($this->org(), $this->plan());
        $paid = Invoice::sole();
        $service->markPaid($paid, $this->admin, 'REF', now());

        $this->assertThrows(fn () => $service->markPaid($paid, $this->admin, 'REF2', now()), InvoiceNotOpen::class, 'already paid');
        $this->assertThrows(fn () => $service->void($paid, $this->admin, 'oops'), InvoiceNotOpen::class);
        $this->assertSame('REF', $paid->fresh()->payment_reference);

        $this->assign($this->org('corporate'), $this->plan(type: 'corporate', name: 'Corp'));
        $open = Invoice::orderByDesc('id')->first();
        $service->void($open, $this->admin, 'Made by mistake');

        $this->assertThrows(fn () => $service->markPaid($open, $this->admin, 'REF', now()), InvoiceNotOpen::class, 'already void');
        $this->assertSame(Invoice::VOID, $open->fresh()->status);
        $this->assertSame('Made by mistake', $open->fresh()->void_reason);
    }

    public function test_voiding_does_not_touch_the_plan_the_organization_was_given(): void
    {
        $this->settings();
        $org = $this->org();
        $assignment = $this->assign($org, $this->plan());

        app(InvoiceService::class)->void(Invoice::sole(), $this->admin, 'Made by mistake');

        $this->assertSame($assignment->id, $org->fresh()->current_assignment_id);
        $this->assertNotNull($org->fresh()->subscription_ends_at);
    }

    public function test_the_audit_trail_holds_ids_and_amounts_never_the_bank_details_or_the_reference(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan());
        $invoice = Invoice::sole();
        app(InvoiceService::class)->markPaid($invoice, $this->admin, 'SECRET-REF-123', now());

        $this->assertSame(1, AuditLog::where('action', 'invoice.issued')->count());
        $this->assertSame(1, AuditLog::where('action', 'invoice.marked_paid')->count());

        $json = AuditLog::whereIn('action', ['invoice.issued', 'invoice.marked_paid', 'invoice.settings_updated'])->get()->map(fn ($l) => json_encode($l->metadata))->implode(' ');

        foreach (['1234567890', 'SECRET-REF-123', 'Jalan Contoh', 'MyKaunsel Sdn Bhd', 'Bank Contoh'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    // ---- the settings ---------------------------------------------------------------------------------------------

    public function test_the_settings_are_checked_and_saving_the_same_again_changes_nothing(): void
    {
        $service = app(PlatformSettings::class);
        $good = ['tax_name' => 'SST', 'tax_rate_percent' => '8', 'due_days' => 14, 'seller_name' => 'Seller'];

        foreach ([['tax_rate_percent' => '101'], ['tax_rate_percent' => '8.123'], ['tax_rate_percent' => '-1'], ['tax_rate_percent' => 'x'], ['due_days' => 0], ['due_days' => 91], ['due_days' => 'abc'], ['seller_name' => '   ']] as $bad) {
            try {
                $service->setInvoiceSettings($this->admin, $bad + $good);
                $this->fail('accepted '.json_encode($bad));
            } catch (\InvalidArgumentException) {
            }
        }

        $this->assertTrue($service->setInvoiceSettings($this->admin, $good));
        $this->assertFalse($service->setInvoiceSettings($this->admin, $good));
        $this->assertSame(1, AuditLog::where('action', 'invoice.settings_updated')->count());
        $this->assertSame(800, $service->invoiceSettings()['tax_rate_bp']);
    }

    public function test_the_settings_tab_saves_and_shows_the_values_and_errors(): void
    {
        $this->as()->get(route('platform.pricing', ['tab' => 'invoicing']))->assertOk()->assertSee('Invoice settings')->assertSee('Tax rate');

        $this->as()->post(route('platform.pricing.invoicing'), ['tax_rate_percent' => 'abc', 'due_days' => 14, 'seller_name' => 'X'])
            ->assertSessionHasErrorsIn('invoicing', 'tax_rate_percent');
        $this->as()->post(route('platform.pricing.invoicing'), ['tax_rate_percent' => '200', 'due_days' => 14, 'seller_name' => 'X'])
            ->assertSessionHasErrorsIn('invoicing', 'form');

        $this->as()->post(route('platform.pricing.invoicing'), ['tax_name' => 'SST', 'tax_rate_percent' => '8', 'due_days' => 21, 'seller_name' => 'MyKaunsel Sdn Bhd', 'bank_account_no' => '99887766'])
            ->assertSessionHasNoErrors()->assertRedirect(route('platform.pricing', ['tab' => 'invoicing']));

        $this->as()->get(route('platform.pricing', ['tab' => 'invoicing']))->assertSee('99887766')->assertSee('value="21"', false);
    }

    // ---- the pages -------------------------------------------------------------------------------------------------

    public function test_the_invoice_pages_are_for_platform_admins_only(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan());
        $invoice = Invoice::sole();
        $someone = User::factory()->create();

        foreach ([route('platform.invoices'), route('platform.invoices.show', $invoice)] as $url) {
            $this->get($url)->assertRedirect();
            $this->actingAs($someone)->get($url)->assertRedirect();
        }

        $this->actingAs($someone)->post(route('platform.invoices.paid', $invoice), ['reference' => 'X', 'paid_on' => '2026-11-01'])->assertRedirect();
        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);
    }

    public function test_the_list_has_tabs_counts_search_and_a_link_to_each_invoice(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan());
        $this->assign($this->org('corporate'), $this->plan(type: 'corporate', name: 'Corp'));
        $overdue = Invoice::orderBy('id')->first();
        $overdue->update(['issued_on' => '2026-09-01', 'due_on' => '2026-09-15']);
        $paid = Invoice::orderByDesc('id')->first();
        app(InvoiceService::class)->markPaid($paid, $this->admin, 'R1', now());

        $page = $this->as()->get(route('platform.invoices'))->assertOk();
        $page->assertSee($overdue->number)->assertDontSee($paid->number)->assertSee('Overdue')->assertSee(route('platform.invoices.show', $overdue), false);

        $this->as()->get(route('platform.invoices', ['tab' => 'paid']))->assertSee($paid->number)->assertDontSee($overdue->number);
        $this->as()->get(route('platform.invoices', ['tab' => 'overdue']))->assertSee($overdue->number);
        $this->as()->get(route('platform.invoices', ['tab' => 'all']))->assertSee($overdue->number)->assertSee($paid->number);
        $this->as()->get(route('platform.invoices', ['tab' => 'all', 'q' => $paid->number]))->assertSee($paid->number)->assertDontSee($overdue->number);
        $this->as()->get(route('platform.invoices', ['tab' => 'all', 'q' => $this->org('corporate')->name]))->assertSee($paid->number)->assertDontSee($overdue->number);
        $this->as()->get(route('platform.invoices', ['tab' => 'all', 'q' => '%']))->assertOk();
    }

    public function test_one_invoice_reads_like_an_invoice(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan(120000));
        $invoice = Invoice::sole();

        $this->as()->get(route('platform.invoices.show', $invoice))->assertOk()
            ->assertSee('INV-2026-0001')->assertSee('MyKaunsel Sdn Bhd')->assertSee('202301000001')->assertSee($this->org()->name)
            ->assertSee('RM 1,200.00')->assertSee('SST (8%)')->assertSee('RM 96.00')->assertSee('RM 1,296.00')
            ->assertSee('Pay by bank transfer')->assertSee('1234567890')->assertSee('Mark as paid');
    }

    public function test_marking_paid_and_voiding_from_the_page_validate_and_refuse_a_second_time(): void
    {
        $this->settings();
        $this->assign($this->org(), $this->plan());
        $invoice = Invoice::sole();
        $paid = route('platform.invoices.paid', $invoice);

        $this->as()->post($paid, ['reference' => '', 'paid_on' => '2026-11-01'])->assertSessionHasErrorsIn('paid', 'reference');
        $this->as()->post($paid, ['reference' => 'R', 'paid_on' => '2026-12-31'])->assertSessionHasErrorsIn('paid', 'paid_on');
        $this->as()->post($paid, ['reference' => 'R', 'paid_on' => 'tomorrow'])->assertSessionHasErrorsIn('paid', 'paid_on');
        $this->assertSame(Invoice::UNPAID, $invoice->fresh()->status);

        $this->as()->post($paid, ['reference' => 'MBB-1', 'paid_on' => '2026-11-02'])->assertSessionHas('status');
        $this->assertSame(Invoice::PAID, $invoice->fresh()->status);

        $this->as()->post($paid, ['reference' => 'MBB-2', 'paid_on' => '2026-11-02'])->assertSessionHas('error');
        $this->as()->post(route('platform.invoices.void', $invoice), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('MBB-1', $invoice->fresh()->payment_reference);

        $this->assign($this->org('corporate'), $this->plan(type: 'corporate', name: 'Corp'));
        $open = Invoice::orderByDesc('id')->first();
        $this->as()->post(route('platform.invoices.void', $open), ['reason' => ''])->assertSessionHasErrorsIn('void', 'reason');
        $this->as()->post(route('platform.invoices.void', $open), ['reason' => 'Wrong plan'])->assertSessionHas('status');
        $this->assertSame(Invoice::VOID, $open->fresh()->status);
    }

    public function test_an_organization_given_a_plan_before_invoices_existed_can_get_its_invoice(): void
    {
        $this->settings();
        $org = $this->org();
        $assignment = $this->assign($org, $this->plan());
        Invoice::query()->delete();

        $this->as()->get(route('platform.organizations.show', $org))->assertOk()->assertSee('No invoice for the current plan yet')->assertSee('Issue invoice for the current plan');

        $response = $this->as()->post(route('platform.organizations.invoice', $org));
        $invoice = Invoice::sole();
        $response->assertRedirect(route('platform.invoices.show', $invoice));
        $this->assertSame($assignment->id, $invoice->assignment_id);

        $this->as()->get(route('platform.organizations.show', $org))->assertSee($invoice->number)->assertDontSee('Issue invoice for the current plan');

        $this->as()->post(route('platform.organizations.invoice', $this->org('clinic')))->assertSessionHas('error');
    }

    public function test_the_menu_has_an_invoices_item(): void
    {
        $this->as()->get(route('platform.dashboard'))->assertSee(route('platform.invoices'), false)->assertSee('Invoices');
    }
}
