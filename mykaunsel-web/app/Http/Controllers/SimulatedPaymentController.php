<?php

namespace App\Http\Controllers;

use App\Models\InvoicePayment;
use App\Services\Payments\InvoicePayments;
use App\Services\Payments\SimulatedGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The page of the SIMULATED payment provider (docs/PAYMENTS_PLAN.md): shows what is being paid and two buttons, Pay and Fail. The buttons send
 * a signed result to InvoicePayments::settle(), exactly like a real provider's callback would. It does not exist when another provider is set.
 */
class SimulatedPaymentController extends Controller
{
    public function show(InvoicePayment $payment): View
    {
        $this->ensureSimulated();
        $payment->load('invoice.organization:id,name');

        return view('pay.show', ['payment' => $payment, 'invoice' => $payment->invoice, 'gateway' => app(SimulatedGateway::class)]);
    }

    public function result(Request $request, InvoicePayment $payment, InvoicePayments $payments): RedirectResponse
    {
        $this->ensureSimulated();
        $data = $request->validate(['outcome' => ['required', 'in:paid,failed'], 'signature' => ['required', 'string', 'max:128']]);

        if ($payment->isExpired()) {
            return back()->with('error', 'This payment page has expired. Ask for a new one.');
        }

        try {
            $payments->settle($payment, $data['outcome'], $data['signature']);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pay.show', $payment);
    }

    private function ensureSimulated(): void
    {
        abort_unless(config('payments.provider') === 'simulated', 404);
    }
}
