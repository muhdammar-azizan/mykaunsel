@props(['invoice'])

@php
    $seller = $invoice->seller ?? [];
    $buyer = $invoice->buyer ?? [];
    $rm = fn (int $cents) => \App\Support\Money::rm($cents);
    $rate = rtrim(rtrim(number_format($invoice->tax_rate_bp / 100, 2, '.', ''), '0'), '.');
@endphp

{{-- One invoice as a document. Used by the Platform Admin now and by the organization's own page later. Everything printed is the snapshot kept on the invoice. --}}
<article class="rounded-[20px] border border-brand-ink/10 bg-white p-6 sm:p-8" aria-label="Invoice {{ $invoice->number }}">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-[22px] font-extrabold tracking-tight">Invoice</h2>
            <p class="mt-1 text-[14px] font-semibold tabular-nums">{{ $invoice->number }}</p>
        </div>
        <dl class="text-right text-[13px]">
            <div><dt class="inline text-brand-ink/55">Issued</dt> <dd class="inline font-semibold">{{ $invoice->issued_on->format('j M Y') }}</dd></div>
            <div><dt class="inline text-brand-ink/55">Due</dt> <dd class="inline font-semibold">{{ $invoice->due_on->format('j M Y') }}</dd></div>
        </dl>
    </header>

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
        <section aria-label="From">
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-brand-ink/55">From</h3>
            <p class="mt-1 text-[14px] font-semibold">{{ $seller['seller_name'] ?? '' }}</p>
            @if (! empty($seller['seller_address']))<p class="whitespace-pre-line text-[13px] text-brand-ink/70">{{ $seller['seller_address'] }}</p>@endif
            @if (! empty($seller['seller_ssm']))<p class="text-[13px] text-brand-ink/70">SSM: {{ $seller['seller_ssm'] }}</p>@endif
            @if (! empty($seller['seller_tax_no']))<p class="text-[13px] text-brand-ink/70">{{ $invoice->tax_name ?: 'Tax' }} no.: {{ $seller['seller_tax_no'] }}</p>@endif
        </section>
        <section aria-label="Billed to">
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-brand-ink/55">Billed to</h3>
            <p class="mt-1 text-[14px] font-semibold">{{ $buyer['name'] ?? '' }}</p>
            @if (! empty($buyer['ssm_number']))<p class="text-[13px] text-brand-ink/70">SSM: {{ $buyer['ssm_number'] }}</p>@endif
        </section>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="w-full min-w-[420px] text-left text-[14px]">
            <caption class="sr-only">Invoice lines</caption>
            <thead>
                <tr class="border-b border-brand-ink/15 text-[11px] font-bold uppercase tracking-wide text-brand-ink/60">
                    <th scope="col" class="py-2 pr-4">Description</th>
                    <th scope="col" class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-brand-ink/[.07]">
                    <td class="py-3 pr-4">
                        {{ $invoice->description }}
                        <span class="block text-[12.5px] text-brand-ink/60">{{ $invoice->period_starts_on->format('j M Y') }} to {{ $invoice->period_ends_on->format('j M Y') }}</span>
                    </td>
                    <td class="py-3 text-right tabular-nums">{{ $rm($invoice->subtotal_cents) }}</td>
                </tr>
                @if ($invoice->discount_cents > 0)
                    <tr class="border-b border-brand-ink/[.07]">
                        <td class="py-3 pr-4">Discount</td>
                        <td class="py-3 text-right tabular-nums">- {{ $rm($invoice->discount_cents) }}</td>
                    </tr>
                @endif
                <tr class="border-b border-brand-ink/[.07]">
                    <td class="py-3 pr-4">{{ $invoice->tax_name ?: 'Tax' }} ({{ $rate }}%)</td>
                    <td class="py-3 text-right tabular-nums">{{ $rm($invoice->tax_cents) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="pt-4 text-right text-[14px] font-bold">Total</th>
                    <td class="pt-4 text-right text-[18px] font-extrabold tabular-nums">{{ $rm($invoice->total_cents) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($invoice->status === \App\Models\Invoice::UNPAID && ! empty($seller['bank_account_no']))
        <section class="mt-6 rounded-xl bg-brand-ink/[.04] px-4 py-3 text-[13px]" aria-label="How to pay by bank transfer">
            <h3 class="font-bold">Pay by bank transfer</h3>
            <p class="mt-1">{{ $seller['bank_name'] ?? '' }} · {{ $seller['bank_account_name'] ?? '' }} · {{ $seller['bank_account_no'] }}</p>
            <p class="text-brand-ink/60">Use {{ $invoice->number }} as the reference.</p>
        </section>
    @endif
</article>
