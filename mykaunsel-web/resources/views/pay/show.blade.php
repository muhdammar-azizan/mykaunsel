<x-auth-centered-layout title="Pay invoice — MyKaunsel" content-max-width="520px">
    <article class="rounded-2xl border border-navy/10 bg-white/85 p-6 shadow-sm md:p-8">
        <p role="note" class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[13.5px] text-amber-900">Test payment page. No real money moves. A real payment provider will replace this page.</p>

        <h1 class="text-[22px] font-semibold tracking-tight">Invoice {{ $invoice->number }}</h1>
        <p class="mt-1 text-[14px] text-navy/65">{{ $invoice->organization->name }} · {{ $invoice->description }}</p>
        <p class="mt-4 text-[28px] font-semibold tabular-nums">{{ \App\Support\Money::rm($payment->amount_cents) }}</p>

        @if (session('error'))
            <p class="mt-4 rounded-xl border border-red-300 bg-red-50 px-4 py-2.5 text-[13.5px] font-semibold text-red-700" role="alert">{{ session('error') }}</p>
        @endif

        @if ($payment->status === \App\Models\InvoicePayment::SUCCEEDED)
            <p class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-[14px] font-semibold text-emerald-800" role="status">Payment received. Thank you. Reference {{ $payment->provider_reference }}.</p>
        @elseif ($payment->status === \App\Models\InvoicePayment::FAILED)
            <p class="mt-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-[14px] font-semibold text-red-700" role="alert">The payment failed. Nothing was charged. Ask for a new payment page to try again.</p>
        @elseif ($payment->isExpired())
            <p class="mt-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[14px] font-semibold text-amber-900">This payment page has expired. Ask for a new one.</p>
        @elseif ($invoice->status !== \App\Models\Invoice::UNPAID)
            <p class="mt-5 rounded-xl border border-navy/15 px-4 py-3 text-[14px] font-semibold">This invoice is {{ strtolower($invoice->statusLabel()) }}. There is nothing to pay.</p>
        @else
            <div class="mt-5 flex flex-wrap gap-3">
                @foreach (['paid' => ['Pay now', 'bg-brand-blue text-white'], 'failed' => ['Make it fail', 'border border-navy/25']] as $outcome => [$label, $class])
                    <form method="POST" action="{{ route('pay.result', $payment) }}">
                        @csrf
                        <input type="hidden" name="outcome" value="{{ $outcome }}">
                        <input type="hidden" name="signature" value="{{ $gateway->sign($payment, $outcome) }}">
                        <button type="submit" class="inline-flex min-h-[44px] items-center rounded-full px-6 text-[14px] font-semibold {{ $class }}">{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </article>
</x-auth-centered-layout>
