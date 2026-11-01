@php
    $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6';
    $primary = 'inline-flex min-h-[44px] items-center rounded-md bg-brand-blue px-5 text-[14px] font-semibold text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2';
    $outline = 'inline-flex min-h-[40px] items-center rounded-md border border-brand-ink/70 bg-white px-4 text-[14px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $rm = fn (int $cents) => \App\Support\Money::rm($cents);
    $statePill = ['active' => ['Active', 'bg-emerald-50 text-emerald-700'], 'ending_soon' => ['Ending soon', 'bg-brand-yellow/25 text-[#6B4C0C]'], 'ended' => ['Ended', 'bg-brand-red/10 text-brand-red'], 'none' => ['No plan', 'bg-brand-ink/10 text-brand-ink/65']][$state];
    $invoicePill = ['Paid' => 'bg-emerald-50 text-emerald-700', 'Overdue' => 'bg-brand-red/10 text-brand-red', 'Unpaid' => 'bg-brand-yellow/25 text-[#6B4C0C]'];
@endphp

<x-portal-layout title="Subscription">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Subscription</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">Your plan with MyKaunsel, your invoices and receipts, and a way to ask for a renewal.</p>

    @if (session('status'))
        <div role="status" class="mt-4 rounded-xl border border-emerald-300 bg-emerald-50 px-5 py-3 text-[14px] font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[14px] font-semibold text-brand-red">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    @unless ($applicable)
        <p class="{{ $card }} mt-5 text-[14px] text-brand-ink/65">This kind of organization has no subscription plan.</p>
    @else
        <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <section class="{{ $card }}" aria-labelledby="plan-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="plan-title" class="text-[20px] font-bold tracking-tight">Your plan</h2>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $statePill[1] }}">{{ $statePill[0] }}</span>
                </div>

                @if ($current)
                    <dl class="mt-3 divide-y divide-[#EEEDEA] text-[14px]">
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Plan</dt><dd class="font-semibold">{{ $current['plan_name'] }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Price for the year</dt><dd class="text-right font-semibold">{{ $rm($current['price_cents']) }}@if ($current['discount_cents'] > 0)<span class="block text-[12.5px] font-normal text-brand-ink/60">Plan price {{ $rm($current['list_price_cents']) }}, discount {{ $rm($current['discount_cents']) }}</span>@endif</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Started</dt><dd class="font-semibold">{{ $current['starts_on']->format('j M Y') }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Ends</dt><dd class="font-semibold">{{ $current['ends_on']->format('j M Y') }}</dd></div>
                        <div class="flex justify-between gap-4 py-3"><dt class="text-brand-ink/65">Active members</dt><dd class="font-semibold">{{ number_format($members) }}@if ($max_members !== null) of {{ number_format($max_members) }}@endif</dd></div>
                    </dl>
                    @if ($over_limit)
                        <p class="mt-3 rounded-lg bg-[#FDF4E3] px-4 py-2.5 text-[13px] font-semibold text-[#6B4C0C]">You have more members than your plan covers. Nothing is blocked; ask MyKaunsel for a bigger plan when you renew.</p>
                    @endif
                @else
                    <p class="mt-3 text-[14px] text-brand-ink/65">Your organization has no plan yet. MyKaunsel sets one up with you.</p>
                @endif

                @if ($state === 'ended')
                    <p class="mt-3 rounded-lg bg-brand-red/[.06] px-4 py-2.5 text-[13px] font-semibold text-brand-red">Your plan has ended.</p>
                @endif
            </section>

            <section class="{{ $card }} self-start" aria-labelledby="renew-title">
                <h2 id="renew-title" class="text-[20px] font-bold tracking-tight">Renew</h2>
                @if ($renewal_requested_at)
                    <p class="mt-3 text-[14px]">You asked MyKaunsel to renew on <span class="font-semibold">{{ $renewal_requested_at->setTimezone(auth()->user()->display_timezone)->format('j M Y') }}</span>. They will be in touch. Nothing is charged until you pay an invoice.</p>
                @else
                    <p class="mt-3 text-[14px] text-brand-ink/65">Ask MyKaunsel for a renewal (or a first plan). They will agree the plan with you and send an invoice. Asking costs nothing.</p>
                    <form method="POST" action="{{ route('org.subscription.renewal') }}" class="mt-4">
                        @csrf
                        <button class="{{ $primary }}">Ask to renew</button>
                    </form>
                @endif
            </section>
        </div>

        <section class="{{ $card }} mt-5" aria-labelledby="invoices-title">
            <h2 id="invoices-title" class="text-[20px] font-bold tracking-tight">Invoices and receipts</h2>
            <ul class="mt-3 divide-y divide-[#EEEDEA] border-t border-[#EEEDEA]">
                @forelse ($invoices as $invoice)
                    @php $label = $invoice->statusLabel(); @endphp
                    <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold tabular-nums">{{ $invoice->number }}</p>
                            <p class="text-[13px] text-brand-ink/65">{{ $invoice->description }} · issued {{ $invoice->issued_on->format('j M Y') }}@if ($invoice->status === \App\Models\Invoice::UNPAID) · due {{ $invoice->due_on->format('j M Y') }}@endif</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="text-[15px] font-semibold tabular-nums">{{ $rm($invoice->total_cents) }}</span>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[12px] font-bold {{ $invoicePill[$label] ?? 'bg-brand-ink/10 text-brand-ink/65' }}">{{ $label }}</span>
                            <a href="{{ route('org.subscription.invoice', $invoice->public_id) }}" class="{{ $outline }}">{{ $invoice->status === \App\Models\Invoice::PAID ? 'Receipt' : 'View' }}</a>
                            @if ($invoice->status === \App\Models\Invoice::UNPAID)
                                <form method="POST" action="{{ route('org.subscription.pay', $invoice->public_id) }}">@csrf<button class="{{ $primary }} !min-h-[40px]">Pay</button></form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-[14px] text-brand-ink/65">No invoices yet. One is made when MyKaunsel gives your organization a plan.</li>
                @endforelse
            </ul>
            <p class="mt-3 text-[12.5px] text-brand-ink/55">Paying uses a test page for now: no real money moves. The bank details on each invoice also work for a bank transfer; MyKaunsel then marks it paid.</p>
        </section>
    @endunless
</x-portal-layout>
