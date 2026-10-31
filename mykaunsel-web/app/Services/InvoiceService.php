<?php

namespace App\Services;

use App\Exceptions\InvoiceNotOpen;
use App\Facades\Audit;
use App\Models\Invoice;
use App\Models\SubscriptionAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invoices for the yearly subscription (docs/PAYMENTS_PLAN.md).
 *
 * - An invoice is made when an organization is given a plan (SubscriptionAssignmentService), in the same transaction, from that assignment's
 *   snapshots: subtotal = the plan price, discount = the promo code, tax on what is left, total. It also keeps who sold, who was billed, the
 *   tax name and rate, and the invoice settings of that day; changing any of them later changes no invoice.
 * - One invoice per assignment (a unique key), so "issue the invoice" pressed twice, or two admins at once, makes one.
 * - The number is INV-<year>-<n>, n restarting every year. The year's counter row is locked while a number is taken.
 * - A total of RM 0.00 (a 100% promo code) is paid at once, with the method "no_charge".
 * - Marking paid and voiding lock the invoice and only work on an unpaid one. A paid invoice is never voided here (that would be a credit note).
 * - Money is whole sen; tax is rounded to the nearest sen, half up. Nothing here sends money anywhere: until a payment provider is connected a
 *   Platform Admin marks an invoice paid by hand once the transfer has arrived.
 */
class InvoiceService
{
    /** Tax in sen: round(amount × rate), half up, with the rate in basis points. */
    public static function tax(int $taxableCents, int $rateBp): int
    {
        return intdiv($taxableCents * $rateBp * 2 + 10_000, 20_000);
    }

    public function issueForAssignment(SubscriptionAssignment $assignment, ?User $actor): Invoice
    {
        return DB::transaction(function () use ($assignment, $actor) {
            $existing = Invoice::query()->where('assignment_id', $assignment->getKey())->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $assignment->loadMissing(['plan', 'organization']);
            $org = $assignment->organization;
            $settings = app(PlatformSettings::class)->invoiceSettings();

            $taxable = $assignment->final_price_cents;
            $tax = self::tax($taxable, $settings['tax_rate_bp']);
            $total = $taxable + $tax;

            $issued = Invoice::today();
            $free = $total === 0;

            $invoice = Invoice::create([
                'number' => $this->nextNumber($issued->year),
                'org_id' => $org->getKey(),
                'assignment_id' => $assignment->getKey(),
                'status' => $free ? Invoice::PAID : Invoice::UNPAID,
                'issued_on' => $issued->toDateString(),
                'due_on' => $issued->copy()->addDays($settings['due_days'])->toDateString(),
                'period_starts_on' => $assignment->starts_on->toDateString(),
                'period_ends_on' => $assignment->ends_on->toDateString(),
                'description' => mb_substr($assignment->plan->name.' — yearly subscription', 0, 200),
                'subtotal_cents' => $assignment->plan_price_cents,
                'discount_cents' => $assignment->discount_cents,
                'tax_name' => $settings['tax_name'],
                'tax_rate_bp' => $settings['tax_rate_bp'],
                'tax_cents' => $tax,
                'total_cents' => $total,
                'currency' => (string) config('invoicing.currency'),
                'seller' => collect($settings)->only(['seller_name', 'seller_address', 'seller_ssm', 'seller_tax_no', 'bank_name', 'bank_account_name', 'bank_account_no'])->all(),
                'buyer' => ['name' => $org->name, 'ssm_number' => $org->ssm_number, 'type' => $org->org_type->value],
                'paid_at' => $free ? now() : null,
                'payment_method' => $free ? 'no_charge' : null,
                'issued_by' => $actor?->getKey(),
            ]);

            Audit::log('invoice.issued', $invoice, [
                'invoice_id' => $invoice->getKey(),
                'org_id' => $org->getKey(),
                'assignment_id' => $assignment->getKey(),
                'subtotal_cents' => $invoice->subtotal_cents,
                'discount_cents' => $invoice->discount_cents,
                'tax_rate_bp' => $invoice->tax_rate_bp,
                'tax_cents' => $invoice->tax_cents,
                'total_cents' => $invoice->total_cents,
            ], $actor ?? 'system');

            return $invoice;
        });
    }

    /**
     * The money has arrived (a bank transfer): the invoice is paid. Only an unpaid invoice.
     *
     * @throws InvoiceNotOpen
     */
    public function markPaid(Invoice $invoice, User $admin, string $reference, CarbonInterface $paidOn): Invoice
    {
        return DB::transaction(function () use ($invoice, $admin, $reference, $paidOn) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->isOpen()) {
                throw new InvoiceNotOpen($locked->status);
            }

            $locked->update([
                'status' => Invoice::PAID,
                'paid_at' => $paidOn->copy()->setTimezone((string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur'))->startOfDay()->utc(),
                'payment_method' => 'manual',
                'payment_reference' => mb_substr(trim($reference), 0, 100),
                'paid_marked_by' => $admin->getKey(),
            ]);

            Audit::log('invoice.marked_paid', $locked, ['invoice_id' => $locked->getKey(), 'org_id' => $locked->org_id, 'total_cents' => $locked->total_cents, 'method' => 'manual'], $admin);

            return $locked;
        });
    }

    /**
     * Cancels an invoice nobody should pay (made by mistake, or replaced). Only an unpaid invoice; the plan and the dates the organization was
     * given do not change.
     *
     * @throws InvoiceNotOpen
     */
    public function void(Invoice $invoice, User $admin, string $reason): Invoice
    {
        return DB::transaction(function () use ($invoice, $admin, $reason) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if (! $locked->isOpen()) {
                throw new InvoiceNotOpen($locked->status);
            }

            $locked->update(['status' => Invoice::VOID, 'voided_at' => now(), 'void_reason' => mb_substr(trim($reason), 0, 200), 'voided_by' => $admin->getKey()]);

            Audit::log('invoice.voided', $locked, ['invoice_id' => $locked->getKey(), 'org_id' => $locked->org_id, 'total_cents' => $locked->total_cents], $admin);

            return $locked;
        });
    }

    /** "INV-2026-0001". The year's counter row is locked while the number is taken. */
    private function nextNumber(int $year): string
    {
        DB::table('invoice_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('invoice_counters')->where('year', $year)->lockForUpdate()->value('last_number');
        DB::table('invoice_counters')->where('year', $year)->update(['last_number' => $last + 1]);

        return config('invoicing.prefix').'-'.$year.'-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
