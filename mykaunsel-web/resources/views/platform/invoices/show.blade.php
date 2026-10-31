@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $paidErrors = $errors->getBag('paid');
    $voidErrors = $errors->getBag('void');
    $when = fn ($value) => \Illuminate\Support\Carbon::parse($value, 'UTC')->timezone($timezone)->format('j M Y, g:i A');
    $label = $invoice->statusLabel();
@endphp

<x-platform-layout :title="'Invoice '.$invoice->number">
    <x-platform.page-header :title="'Invoice '.$invoice->number" :subtitle="$invoice->organization->name.' · '.$label" />

    <p class="mt-2"><a href="{{ route('platform.invoices') }}" class="text-[13px] font-semibold text-brand-blue hover:underline">All invoices</a> · <a href="{{ route('platform.organizations.show', $invoice->organization) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">{{ $invoice->organization->name }}</a></p>

    @if (session('status'))
        <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[13.5px] font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_340px]">
        <x-invoice-document :invoice="$invoice" />

        <div class="space-y-5">
            <x-platform.panel title="Payment">
                @if ($invoice->status === \App\Models\Invoice::PAID)
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="Paid on">{{ $when($invoice->paid_at) }}</x-platform.detail-row>
                        <x-platform.detail-row label="Method">{{ ['manual' => 'Bank transfer, marked by an admin', 'no_charge' => 'Nothing to pay', 'simulated' => 'Online (test page)', 'billplz' => 'Online (Billplz)'][$invoice->payment_method] ?? $invoice->payment_method }}</x-platform.detail-row>
                        @if ($invoice->payment_reference)
                            <x-platform.detail-row label="Reference">{{ $invoice->payment_reference }}</x-platform.detail-row>
                        @endif
                    </dl>
                @elseif ($invoice->status === \App\Models\Invoice::VOID)
                    <dl class="mt-2 divide-y divide-brand-ink/[.07]">
                        <x-platform.detail-row label="Voided">{{ $when($invoice->voided_at) }}</x-platform.detail-row>
                        <x-platform.detail-row label="Reason">{{ $invoice->void_reason }}</x-platform.detail-row>
                    </dl>
                @else
                    <p class="text-[13px] text-brand-ink/65">Waiting for payment. When the transfer has arrived, mark the invoice paid with the bank reference.</p>

                    <form method="POST" action="{{ route('platform.invoices.paid', $invoice) }}" class="mt-3" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <label for="reference" class="block text-[12.5px] font-semibold text-brand-ink/70">Bank reference</label>
                        <input id="reference" name="reference" type="text" maxlength="100" required value="{{ $paidErrors->any() ? old('reference') : '' }}" class="{{ $input }}">
                        @if ($paidErrors->has('reference'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $paidErrors->first('reference') }}</p>@endif

                        <label for="paid_on" class="mt-3 block text-[12.5px] font-semibold text-brand-ink/70">Day the money arrived</label>
                        <input id="paid_on" name="paid_on" type="date" required value="{{ $paidErrors->any() ? old('paid_on') : \App\Models\Invoice::today()->toDateString() }}" max="{{ \App\Models\Invoice::today()->toDateString() }}" class="{{ $input }}">
                        @if ($paidErrors->has('paid_on'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $paidErrors->first('paid_on') }}</p>@endif

                        <button type="submit" :disabled="busy" class="mt-4 inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">
                            <span x-show="!busy">Mark as paid</span><span x-show="busy" x-cloak>Saving…</span>
                        </button>
                    </form>
                @endif
            </x-platform.panel>

            <x-platform.panel title="Online payments">
                @forelse ($attempts as $attempt)
                    <p class="mt-2 text-[13px]"><span class="font-semibold">{{ $attempt->statusLabel() }}</span> <span class="text-brand-ink/55">· {{ \App\Support\Money::rm($attempt->amount_cents) }} · {{ $when($attempt->created_at) }}</span>@if ($attempt->provider_reference)<span class="block text-[12px] text-brand-ink/55">{{ $attempt->provider_reference }}</span>@endif</p>
                @empty
                    <p class="text-[13px] text-brand-ink/55">Nobody has tried to pay online.</p>
                @endforelse
                <p class="mt-3 text-[12.5px] text-brand-ink/50">Provider: {{ config('payments.provider') }} (a test page, no real money).</p>
                @if ($invoice->status === \App\Models\Invoice::UNPAID)
                    <form method="POST" action="{{ route('platform.invoices.pay-link', $invoice) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="inline-flex min-h-[38px] items-center rounded-full border border-brand-ink/20 px-4 text-[13px] font-semibold transition hover:bg-brand-ink/5">Open a payment page</button>
                    </form>
                @endif
            </x-platform.panel>

            @if ($invoice->status === \App\Models\Invoice::UNPAID)
                <x-platform.panel title="Cancel this invoice">
                    <p class="text-[13px] text-brand-ink/65">Only for an invoice made by mistake. It can not be undone, and the plan the organization was given does not change.</p>
                    <form method="POST" action="{{ route('platform.invoices.void', $invoice) }}" class="mt-3" onsubmit="return confirm('Void this invoice? It can not be undone.');">
                        @csrf
                        <label for="reason" class="block text-[12.5px] font-semibold text-brand-ink/70">Why</label>
                        <input id="reason" name="reason" type="text" maxlength="200" required value="{{ $voidErrors->any() ? old('reason') : '' }}" class="{{ $input }}">
                        @if ($voidErrors->has('reason'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $voidErrors->first('reason') }}</p>@endif
                        <button type="submit" class="mt-3 inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red">Void invoice</button>
                    </form>
                </x-platform.panel>
            @endif

            <x-platform.panel title="History">
                @forelse ($history as $entry)
                    <p class="mt-2 text-[13px]"><span class="font-semibold">{{ config('audit.actions')[$entry->action]['label'] ?? $entry->action }}</span> <span class="text-brand-ink/55">· {{ $when($entry->created_at) }}</span></p>
                @empty
                    <p class="text-[13px] text-brand-ink/55">Nothing yet.</p>
                @endforelse
            </x-platform.panel>
        </div>
    </div>
</x-platform-layout>
