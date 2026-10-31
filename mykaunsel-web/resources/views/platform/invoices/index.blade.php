@php
    $pill = fn (\App\Models\Invoice $invoice) => match ($invoice->statusLabel()) {
        'Paid' => 'bg-emerald-50 text-emerald-700',
        'Overdue' => 'bg-brand-red/10 text-brand-red',
        'Void' => 'bg-brand-ink/10 text-brand-ink/60',
        default => 'bg-brand-yellow/20 text-[#6B4C0C]',
    };
@endphp

<x-platform-layout title="Invoices">
    <x-platform.page-header title="Invoices" subtitle="The yearly subscriptions of universities, corporates and clinics. An invoice is made when an organization is given a plan." />

    @if (session('status'))
        <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[13.5px] font-semibold text-emerald-800" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    <x-platform.status-tabs :tabs="$tabs" :active="$tab" label="Invoice status" />

    <form method="GET" action="{{ route('platform.invoices') }}" class="mt-5 flex flex-wrap items-center gap-3">
        @if ($tab !== 'open')<input type="hidden" name="tab" value="{{ $tab }}">@endif
        <label for="invoice-search" class="sr-only">Search by invoice number or organization</label>
        <input id="invoice-search" type="search" name="q" value="{{ $search }}" placeholder="Invoice number or organization" maxlength="100"
               class="w-full max-w-sm rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
        <button type="submit" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white">Search</button>
    </form>

    <x-platform.table :headings="['Invoice', 'Organization', 'Issued', 'Due', 'Total', 'Status', '']" empty="No invoices here.">
        @foreach ($invoices as $invoice)
            <tr class="border-t border-brand-ink/[.07] transition hover:bg-brand-blue/[.03]">
                <td class="whitespace-nowrap px-4 py-3.5 text-[14px] font-semibold tabular-nums">{{ $invoice->number }}</td>
                <td class="px-4 py-3.5 text-[14px]">{{ $invoice->organization->name }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-[13px] text-brand-ink/65">{{ $invoice->issued_on->format('j M Y') }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-[13px] text-brand-ink/65">{{ $invoice->due_on->format('j M Y') }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-[14px] tabular-nums">{{ \App\Support\Money::rm($invoice->total_cents) }}</td>
                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $pill($invoice) }}">{{ $invoice->statusLabel() }}</span></td>
                <td class="px-4 py-3.5 text-right"><a href="{{ route('platform.invoices.show', $invoice) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">View</a></td>
            </tr>
        @endforeach
    </x-platform.table>

    @if ($invoices->hasPages())
        <div class="mt-5">{{ $invoices->links() }}</div>
    @endif
</x-platform-layout>
