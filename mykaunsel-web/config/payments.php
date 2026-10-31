<?php

/**
 * Online payment of invoices (docs/PAYMENTS_PLAN.md). Only the simulated provider exists: it shows a page with a "Pay" and a "Fail" button so the
 * whole flow can be tried without a company account. A real provider (Billplz) is another class behind App\Services\Payments\PaymentGateway.
 */
return [
    // simulated (billplz later)
    'provider' => env('PAYMENT_PROVIDER', 'simulated'),

    // A started payment that nobody finished is shown as expired after this many minutes.
    'attempt_minutes' => 60,
];
