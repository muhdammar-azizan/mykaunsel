<?php

namespace App\Services\Payments;

use App\Exceptions\InvoiceNotOpen;
use App\Facades\Audit;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Paying an invoice online (docs/PAYMENTS_PLAN.md).
 *
 * - `start()` opens a try for an unpaid invoice.
 * - `settle()` is what the provider's callback calls. It is safe to call twice or from two places at once: the try and the invoice are locked,
 *   a try that is already settled is returned as it is, and a result that does not match the signature or the amount is refused.
 * - A success marks the invoice paid (method = the provider). If the invoice was paid another way meanwhile, the try is kept as succeeded and
 *   flagged in the audit log ("paid twice"), so an admin can refund by hand.
 */
class InvoicePayments
{
    public function gateway(): PaymentGateway
    {
        return match (config('payments.provider')) {
            'simulated' => app(SimulatedGateway::class),
            default => throw new InvalidArgumentException('Unknown payment provider.'),
        };
    }

    /** @throws InvoiceNotOpen */
    public function start(Invoice $invoice, ?User $actor): InvoicePayment
    {
        if (! $invoice->isOpen()) {
            throw new InvoiceNotOpen($invoice->status);
        }

        return InvoicePayment::create([
            'invoice_id' => $invoice->getKey(),
            'provider' => $this->gateway()->name(),
            'amount_cents' => $invoice->total_cents,
            'started_by' => $actor?->getKey(),
        ]);
    }

    /** @throws InvalidArgumentException when the result can not be trusted */
    public function settle(InvoicePayment $payment, string $outcome, string $signature): InvoicePayment
    {
        if (! in_array($outcome, ['paid', 'failed'], true) || ! $this->gateway()->verify($payment, $outcome, $signature)) {
            throw new InvalidArgumentException('This payment result is not valid.');
        }

        return DB::transaction(function () use ($payment, $outcome) {
            $locked = InvoicePayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status !== InvoicePayment::PENDING) {
                return $locked;
            }

            $invoice = Invoice::query()->lockForUpdate()->findOrFail($locked->invoice_id);
            $context = ['invoice_id' => $invoice->getKey(), 'org_id' => $invoice->org_id, 'payment_id' => $locked->getKey()];

            if ($outcome === 'failed') {
                $locked->update(['status' => InvoicePayment::FAILED, 'settled_at' => now()]);
                Audit::log('invoice.payment_failed', $invoice, $context, 'system');

                return $locked;
            }

            if ($locked->amount_cents !== $invoice->total_cents) {
                throw new InvalidArgumentException('The amount paid does not match the invoice.');
            }

            $locked->update(['status' => InvoicePayment::SUCCEEDED, 'settled_at' => now(), 'provider_reference' => 'SIM-'.strtoupper(substr($locked->public_id, -8))]);

            if ($invoice->isOpen()) {
                $invoice->update(['status' => Invoice::PAID, 'paid_at' => now(), 'payment_method' => $locked->provider, 'payment_reference' => $locked->provider_reference]);
                Audit::log('invoice.paid_online', $invoice, $context + ['total_cents' => $invoice->total_cents, 'method' => $locked->provider], 'system');
            } else {
                Audit::log('invoice.paid_twice', $invoice, $context + ['total_cents' => $locked->amount_cents], 'system');
            }

            return $locked;
        });
    }
}
