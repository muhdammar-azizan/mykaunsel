<?php

namespace App\Services;

use App\Facades\Audit;
use App\Models\Invoice;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

/**
 * Receipts (docs/PAYMENTS_PLAN.md, "Invoices and receipts"). Called inside the transaction that marks an invoice paid, so a paid invoice and its receipt
 * are made together or not at all.
 *
 * - One receipt per invoice (a unique key), so a repeated call, or two payments at once, gives the one that exists.
 * - None for an invoice that is not paid, one of RM 0.00 (nothing was paid), or a voided one.
 * - The number is RCT-<year>-<n>, n restarting every year, taken from a locked counter row like the invoice's. The year is the day it was paid (Malaysian).
 * - Everything is copied from the invoice as it was: who was paid, who paid, the amounts, the tax, how it was paid. It is never edited or deleted.
 */
class ReceiptService
{
    public function issueFor(Invoice $invoice): ?Receipt
    {
        if ($invoice->status !== Invoice::PAID || $invoice->total_cents <= 0 || $invoice->paid_at === null) {
            return null;
        }

        return DB::transaction(function () use ($invoice) {
            $existing = Receipt::query()->where('invoice_id', $invoice->getKey())->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $year = $invoice->paid_at->copy()->timezone((string) config('platform-settings.public_timezone', 'Asia/Kuala_Lumpur'))->year;

            $receipt = Receipt::create([
                'number' => $this->nextNumber($year),
                'invoice_id' => $invoice->getKey(),
                'org_id' => $invoice->org_id,
                'paid_at' => $invoice->paid_at,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'description' => $invoice->description,
                'period_starts_on' => $invoice->period_starts_on,
                'period_ends_on' => $invoice->period_ends_on,
                'subtotal_cents' => $invoice->subtotal_cents,
                'discount_cents' => $invoice->discount_cents,
                'tax_name' => $invoice->tax_name,
                'tax_rate_bp' => $invoice->tax_rate_bp,
                'tax_cents' => $invoice->tax_cents,
                'total_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'seller' => $invoice->seller,
                'buyer' => $invoice->buyer,
            ]);

            Audit::log('receipt.issued', $invoice, ['receipt_id' => $receipt->getKey(), 'invoice_id' => $invoice->getKey(), 'org_id' => $invoice->org_id, 'total_cents' => $receipt->total_cents, 'method' => $receipt->payment_method], 'system');

            // E-mailed to the organization's admins once this is committed.
            app(BillingMail::class)->receiptIssued($receipt);

            return $receipt;
        });
    }

    /** "RCT-2026-0001". The year's counter row is locked while the number is taken. */
    private function nextNumber(int $year): string
    {
        DB::table('receipt_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        $last = (int) DB::table('receipt_counters')->where('year', $year)->lockForUpdate()->value('last_number');
        DB::table('receipt_counters')->where('year', $year)->update(['last_number' => $last + 1]);

        return 'RCT-'.$year.'-'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }
}
