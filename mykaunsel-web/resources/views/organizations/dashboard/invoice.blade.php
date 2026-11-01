@php
    $primary = 'inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2';
    $when = fn ($value) => \Illuminate\Support\Carbon::parse($value, 'UTC')->timezone($timezone)->format('j M Y, g:i A');
    $paid = $invoice->status === \App\Models\Invoice::PAID;
    $label = $invoice->statusLabel();
    $methods = ['simulated' => 'Online (test page)', 'billplz' => 'Online', 'manual' => 'Bank transfer', 'no_charge' => 'Nothing to pay'];
@endphp

<x-portal-layout :title="($paid ? 'Receipt ' : 'Invoice ').$invoice->number">
    <p><a href="{{ route('org.subscription') }}" class="text-[14px] font-semibold text-brand-blue hover:underline">&larr; Subscription</a></p>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-[24px] font-bold leading-tight tracking-tight">{{ $paid ? 'Receipt' : 'Invoice' }} {{ $invoice->number }}</h1>
        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $paid ? 'bg-emerald-50 text-emerald-700' : ($label === 'Overdue' ? 'bg-brand-red/10 text-brand-red' : 'bg-brand-yellow/25 text-[#6B4C0C]') }}">{{ $label }}</span>
        <button type="button" onclick="window.print()" class="ml-auto inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink hover:bg-brand-ink/5 print:hidden">Print</button>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <x-invoice-document :invoice="$invoice" />

        <div class="space-y-5 print:hidden">
            <section class="rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="pay-title">
                <h2 id="pay-title" class="text-[20px] font-bold tracking-tight">{{ $paid ? 'Paid' : 'Payment' }}</h2>
                @if ($paid)
                    <dl class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Paid on</dt><dd class="text-right font-semibold">{{ $when($invoice->paid_at) }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Method</dt><dd class="text-right font-semibold">{{ $methods[$invoice->payment_method] ?? $invoice->payment_method }}</dd></div>
                        @if ($invoice->payment_reference)<div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Reference</dt><dd class="text-right font-semibold">{{ $invoice->payment_reference }}</dd></div>@endif
                    </dl>
                @else
                    <p class="mt-2 text-[14px] text-brand-ink/65">Pay online, or by bank transfer using the details on the invoice. MyKaunsel marks a transfer paid when it arrives.</p>
                    <form method="POST" action="{{ route('org.subscription.pay', $invoice->public_id) }}" class="mt-4">@csrf<button class="{{ $primary }}">Pay {{ \App\Support\Money::rm($invoice->total_cents) }}</button></form>
                @endif
            </section>

            @if ($attempts->isNotEmpty())
                <section class="rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="tries-title">
                    <h2 id="tries-title" class="text-[20px] font-bold tracking-tight">Payment tries</h2>
                    @foreach ($attempts as $attempt)
                        <p class="mt-2 text-[13px]"><span class="font-semibold">{{ $attempt->statusLabel() }}</span> <span class="text-brand-ink/55">· {{ $when($attempt->created_at) }}</span></p>
                    @endforeach
                </section>
            @endif
        </div>
    </div>
</x-portal-layout>
