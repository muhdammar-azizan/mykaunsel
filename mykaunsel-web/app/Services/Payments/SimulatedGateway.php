<?php

namespace App\Services\Payments;

use App\Models\InvoicePayment;

/** The stand-in provider: a page of ours with a Pay and a Fail button. Its result is signed with the app key, like a real provider's callback. */
class SimulatedGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulated';
    }

    public function payUrl(InvoicePayment $payment): string
    {
        return route('pay.show', $payment);
    }

    public function sign(InvoicePayment $payment, string $outcome): string
    {
        return hash_hmac('sha256', $payment->public_id.'|'.$payment->amount_cents.'|'.$outcome, (string) config('app.key'));
    }

    public function verify(InvoicePayment $payment, string $outcome, string $signature): bool
    {
        return hash_equals($this->sign($payment, $outcome), $signature);
    }
}
