@php
    $seller = $receipt->seller ?? [];
    $buyer = $receipt->buyer ?? [];
    $rm = fn (int $cents) => \App\Support\Money::rm($cents);
    $rate = rtrim(rtrim(number_format($receipt->tax_rate_bp / 100, 2, '.', ''), '0'), '.');
    $paidOn = $receipt->paid_at->copy()->timezone($timezone)->format('j M Y, g:i A');
    $methods = ['simulated' => 'Online (test page)', 'billplz' => 'Online', 'manual' => 'Bank transfer', 'no_charge' => 'Nothing to pay'];
@endphp

<x-portal-layout :title="'Receipt '.$receipt->number">
    <p class="print:hidden"><a href="{{ route('org.subscription') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">&larr; Subscription</a></p>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-[24px] font-bold leading-tight tracking-tight">Receipt {{ $receipt->number }}</h1>
        <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[12px] font-bold text-emerald-700">Paid</span>
        <span class="ml-auto flex gap-2 print:hidden">
            <a href="{{ route('org.subscription.receipt.pdf', $receipt->public_id) }}" class="inline-flex min-h-[40px] items-center rounded-md bg-brand-blue px-4 text-[14px] font-semibold text-white hover:bg-brand-deep">Download PDF</a>
            <button type="button" onclick="window.print()" class="inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink hover:bg-brand-ink/5">Print</button>
        </span>
    </div>

    <article class="mt-5 max-w-3xl rounded-xl border border-[#E4E2DE] bg-white p-6 sm:p-8" aria-label="Receipt {{ $receipt->number }}">
        <div class="grid gap-6 sm:grid-cols-2">
            <section aria-label="Received by">
                <h2 class="text-[11px] font-bold uppercase tracking-wide text-brand-ink/55">Received by</h2>
                <p class="mt-1 text-[14px] font-semibold">{{ $seller['seller_name'] ?? '' }}</p>
                @if (! empty($seller['seller_address']))<p class="whitespace-pre-line text-[13px] text-brand-ink/70">{{ $seller['seller_address'] }}</p>@endif
                @if (! empty($seller['seller_ssm']))<p class="text-[13px] text-brand-ink/70">SSM: {{ $seller['seller_ssm'] }}</p>@endif
                @if (! empty($seller['seller_tax_no']))<p class="text-[13px] text-brand-ink/70">{{ $receipt->tax_name ?: 'Tax' }} no.: {{ $seller['seller_tax_no'] }}</p>@endif
            </section>
            <section aria-label="Received from">
                <h2 class="text-[11px] font-bold uppercase tracking-wide text-brand-ink/55">Received from</h2>
                <p class="mt-1 text-[14px] font-semibold">{{ $buyer['name'] ?? '' }}</p>
                @if (! empty($buyer['ssm_number']))<p class="text-[13px] text-brand-ink/70">SSM: {{ $buyer['ssm_number'] }}</p>@endif
            </section>
        </div>

        <div class="mt-6 rounded-xl bg-emerald-50 px-5 py-4">
            <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-800/70">Amount received</p>
            <p class="mt-1 text-[26px] font-extrabold tabular-nums">{{ $rm($receipt->total_cents) }}</p>
            <p class="mt-1 text-[13px] text-brand-ink/70">{{ $paidOn }} · {{ $methods[$receipt->payment_method] ?? $receipt->payment_method }}@if ($receipt->payment_reference) · reference {{ $receipt->payment_reference }}@endif</p>
            <p class="text-[13px] text-brand-ink/70">For invoice <a href="{{ route('org.subscription.invoice', $receipt->invoice->public_id) }}" class="font-semibold text-brand-blue hover:underline">{{ $receipt->invoice->number }}</a></p>
        </div>

        <table class="mt-6 w-full text-left text-[14px]">
            <caption class="sr-only">What was paid for</caption>
            <thead><tr class="border-b border-brand-ink/15 text-[11px] font-bold uppercase tracking-wide text-brand-ink/60"><th scope="col" class="py-2 pr-4">In payment of</th><th scope="col" class="py-2 text-right">Amount</th></tr></thead>
            <tbody>
                <tr class="border-b border-brand-ink/[.07]"><td class="py-3 pr-4">{{ $receipt->description }}<span class="block text-[12.5px] text-brand-ink/60">{{ $receipt->period_starts_on->format('j M Y') }} to {{ $receipt->period_ends_on->format('j M Y') }}</span></td><td class="py-3 text-right tabular-nums">{{ $rm($receipt->subtotal_cents) }}</td></tr>
                @if ($receipt->discount_cents > 0)<tr class="border-b border-brand-ink/[.07]"><td class="py-3 pr-4">Discount</td><td class="py-3 text-right tabular-nums">- {{ $rm($receipt->discount_cents) }}</td></tr>@endif
                <tr class="border-b border-brand-ink/[.07]"><td class="py-3 pr-4">{{ $receipt->tax_name ?: 'Tax' }} ({{ $rate }}%)</td><td class="py-3 text-right tabular-nums">{{ $rm($receipt->tax_cents) }}</td></tr>
            </tbody>
            <tfoot><tr><th scope="row" class="pt-4 text-right text-[14px] font-bold">Total paid</th><td class="pt-4 text-right text-[18px] font-extrabold tabular-nums">{{ $rm($receipt->total_cents) }}</td></tr></tfoot>
        </table>
    </article>
</x-portal-layout>
