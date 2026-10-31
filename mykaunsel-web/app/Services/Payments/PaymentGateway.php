<?php

namespace App\Services\Payments;

use App\Models\InvoicePayment;

/** A payment provider. It only knows where to send the payer and how to trust a result; the invoice rules are in InvoicePayments. */
interface PaymentGateway
{
    public function name(): string;

    /** The address the payer is sent to, to pay this try. */
    public function payUrl(InvoicePayment $payment): string;

    /** True when `$signature` proves the provider sent this result for this try. */
    public function verify(InvoicePayment $payment, string $outcome, string $signature): bool;
}
