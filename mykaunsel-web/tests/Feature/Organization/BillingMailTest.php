<?php

namespace Tests\Feature\Organization;

use App\Enums\AccessModel;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgType;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\Receipt;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\InvoiceIssuedMail;
use App\Notifications\ReceiptIssuedMail;
use App\Services\InvoiceService;
use App\Services\Payments\InvoicePayments;
use App\Services\Payments\SimulatedGateway;
use App\Services\PlatformSettings;
use App\Services\SubscriptionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Api\AvailabilityFixtures;
use Tests\TestCase;

/** The invoice and the receipt by e-mail, with the PDF attached, to every admin of the organization. Clock: Monday 2 November 2026, 09:00 Malaysian time. */
class BillingMailTest extends TestCase
{
    use AvailabilityFixtures;
    use RefreshDatabase;

    private Organization $acme;

    private User $hana;

    private User $ismail;

    private User $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startClock();
        $this->acme = $this->org('Acme Berhad', AccessModel::Closed, OrgType::Corporate);
        $this->hana = $this->admin($this->acme, 'Hana HR');
        $this->ismail = $this->admin($this->acme, 'Ismail HR');
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

    private function admin(Organization $org, string $name, bool $verified = true, MembershipStatus $status = MembershipStatus::Active): User
    {
        $user = User::factory()->create(['name' => $name, 'email_verified_at' => $verified ? now() : null]);
        Membership::create(['user_id' => $user->id, 'org_id' => $org->id, 'role' => MembershipRole::OrgAdmin, 'status' => $status, 'joined_at' => now()]);

        return $user;
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

    // ---- the invoice --------------------------------------------------------------------------------------------

    public function test_giving_a_plan_mails_the_invoice_once_to_every_active_admin_with_a_confirmed_address_and_to_nobody_else(): void
    {
        Notification::fake();
        $unconfirmed = $this->admin($this->acme, 'Not Confirmed', verified: false);
        $removed = $this->admin($this->acme, 'Former Admin', status: MembershipStatus::Offboarded);
        $other = $this->org('Beta', AccessModel::Closed, OrgType::Corporate);
        $betaAdmin = $this->admin($other, 'Beta Admin');

        $this->invoice();

        Notification::assertSentTo([$this->hana, $this->ismail], InvoiceIssuedMail::class);
        Notification::assertNotSentTo([$unconfirmed, $removed, $betaAdmin, $this->platformAdmin], InvoiceIssuedMail::class);
        Notification::assertCount(2);
    }

    public function test_the_mail_has_the_figures_the_way_to_pay_and_the_invoice_as_a_pdf(): void
    {
        $invoice = $this->invoice();

        $mail = (new InvoiceIssuedMail($invoice->id))->toMail($this->hana);

        $this->assertSame('Invoice INV-2026-0001 from MyKaunsel', $mail->subject);
        $text = implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines));
        $this->assertStringContainsString('Amount: RM 1,296.00 (tax included)', $text);
        $this->assertStringContainsString('Please pay by 16 Nov 2026', $text);
        $this->assertSame(route('org.subscription.invoice', $invoice->public_id), $mail->actionUrl);
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('INV-2026-0001.pdf', $mail->rawAttachments[0]['name']);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
        $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
    }

    public function test_an_invoice_that_is_already_paid_says_nothing_is_left_to_pay(): void
    {
        PromoCode::create(['code' => 'FREE100', 'type' => 'percent', 'value' => 100, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(5), 'applies_to' => 'all', 'version' => 1]);
        $invoice = $this->invoice(code: 'FREE100');

        $mail = (new InvoiceIssuedMail($invoice->id))->toMail($this->hana);

        $this->assertStringContainsString('Nothing is left to pay', implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines)));
        $this->assertNull($mail->actionUrl);
    }

    public function test_an_invoice_that_is_rolled_back_is_never_mailed(): void
    {
        Notification::fake();

        try {
            DB::transaction(function () {
                $this->invoice();

                throw new RuntimeException('something later failed');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, Invoice::count());
        Notification::assertNothingSent();
    }

    public function test_asking_again_for_the_invoice_of_the_same_plan_does_not_mail_again(): void
    {
        Notification::fake();
        $invoice = $this->invoice();

        app(InvoiceService::class)->issueForAssignment($invoice->assignment, $this->platformAdmin);

        Notification::assertSentToTimes($this->hana, InvoiceIssuedMail::class, 1);
    }

    // ---- the receipt --------------------------------------------------------------------------------------------

    public function test_paying_online_mails_the_receipt_to_every_admin_once(): void
    {
        $invoice = $this->invoice();
        Notification::fake();

        $this->payOnline($invoice);
        $this->payOnline($invoice);                                 // a second payment: flagged, no second receipt

        Notification::assertSentToTimes($this->hana, ReceiptIssuedMail::class, 1);
        Notification::assertSentToTimes($this->ismail, ReceiptIssuedMail::class, 1);
        Notification::assertNotSentTo($this->platformAdmin, ReceiptIssuedMail::class);
    }

    public function test_marking_paid_by_hand_mails_the_receipt_too(): void
    {
        $invoice = $this->invoice();
        Notification::fake();

        app(InvoiceService::class)->markPaid($invoice, $this->platformAdmin, 'BANK-REF-1', Carbon::parse('2026-11-01'));

        Notification::assertSentTo([$this->hana, $this->ismail], ReceiptIssuedMail::class);
    }

    public function test_the_receipt_mail_has_the_amount_the_date_and_the_receipt_as_a_pdf(): void
    {
        $invoice = $this->invoice();
        $this->payOnline($invoice);
        $receipt = Receipt::sole();

        $mail = (new ReceiptIssuedMail($receipt->id))->toMail($this->ismail);

        $this->assertSame('Receipt RCT-2026-0001 from MyKaunsel', $mail->subject);
        $text = implode("\n", array_map(fn ($line) => (string) $line, $mail->introLines));
        $this->assertStringContainsString('For invoice: INV-2026-0001', $text);
        $this->assertStringContainsString('Amount received: RM 1,296.00', $text);
        $this->assertSame(route('org.subscription.receipt', $receipt->public_id), $mail->actionUrl);
        $this->assertSame('RCT-2026-0001.pdf', $mail->rawAttachments[0]['name']);
        $this->assertStringStartsWith('%PDF-', $mail->rawAttachments[0]['data']);
    }

    public function test_an_invoice_of_nothing_to_pay_sends_no_receipt_mail(): void
    {
        PromoCode::create(['code' => 'FREE100', 'type' => 'percent', 'value' => 100, 'valid_from' => now()->subDay(), 'valid_until' => now()->addDays(5), 'applies_to' => 'all', 'version' => 1]);
        Notification::fake();

        $this->invoice(code: 'FREE100');

        Notification::assertSentToTimes($this->hana, InvoiceIssuedMail::class, 1);
        Notification::assertNotSentTo($this->hana, ReceiptIssuedMail::class);
    }

    // ---- really sent --------------------------------------------------------------------------------------------

    public function test_the_message_that_leaves_the_application_carries_the_pdf(): void
    {
        $invoice = $this->invoice();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $sent = $messages->filter(fn ($m) => str_contains((string) $m->getOriginalMessage()->getSubject(), 'Invoice '.$invoice->number));

        $this->assertCount(2, $sent);                                             // one for each admin
        $attachments = $sent->first()->getOriginalMessage()->getAttachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('INV-2026-0001.pdf', $attachments[0]->getFilename());
        $this->assertStringStartsWith('%PDF-', $attachments[0]->getBody());
    }
}
