@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $bag = $errors->getBag('invoicing');
    $val = fn (string $key, $default) => $bag->any() ? old($key, $default) : $default;
    $rate = rtrim(rtrim(number_format($invoicing['tax_rate_bp'] / 100, 2, '.', ''), '0'), '.');
@endphp

<section class="mt-8" aria-labelledby="invoicing-title">
    <x-platform.panel title="Invoice settings">
        <p id="invoicing-title" class="text-[13.5px] text-brand-ink/70">What is printed on every invoice. Each invoice keeps the values it was made with, so changing them here never changes an invoice that was already issued.</p>

        @if ($bag->has('form'))
            <p class="mt-3 text-[13px] font-semibold text-brand-red" role="alert">{{ $bag->first('form') }}</p>
        @endif

        <form method="POST" action="{{ route('platform.pricing.invoicing') }}" x-data="{ busy: false }" @submit="busy = true" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf

            <div>
                <label for="tax_name" class="block text-[12.5px] font-semibold text-brand-ink/70">Tax name</label>
                <input id="tax_name" name="tax_name" type="text" maxlength="20" value="{{ $val('tax_name', $invoicing['tax_name']) }}" class="{{ $input }}">
                @if ($bag->has('tax_name'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $bag->first('tax_name') }}</p>@endif
            </div>
            <div>
                <label for="tax_rate_percent" class="block text-[12.5px] font-semibold text-brand-ink/70">Tax rate (%)</label>
                <input id="tax_rate_percent" name="tax_rate_percent" type="text" inputmode="decimal" required value="{{ $val('tax_rate_percent', $rate) }}" class="{{ $input }}" aria-describedby="rate-note">
                <p id="rate-note" class="mt-1 text-[12px] text-brand-ink/55">0 while no tax is charged. Added on top of the plan price after the discount.</p>
                @if ($bag->has('tax_rate_percent'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $bag->first('tax_rate_percent') }}</p>@endif
            </div>
            <div>
                <label for="due_days" class="block text-[12.5px] font-semibold text-brand-ink/70">Days until an invoice is due</label>
                <input id="due_days" name="due_days" type="number" min="{{ config('invoicing.limits.due_days')[0] }}" max="{{ config('invoicing.limits.due_days')[1] }}" required value="{{ $val('due_days', $invoicing['due_days']) }}" class="{{ $input }}">
                @if ($bag->has('due_days'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $bag->first('due_days') }}</p>@endif
            </div>

            <div class="sm:col-span-2"><h3 class="mt-2 text-[14px] font-extrabold">Seller</h3></div>
            <div>
                <label for="seller_name" class="block text-[12.5px] font-semibold text-brand-ink/70">Name</label>
                <input id="seller_name" name="seller_name" type="text" maxlength="100" required value="{{ $val('seller_name', $invoicing['seller_name']) }}" class="{{ $input }}">
                @if ($bag->has('seller_name'))<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red" role="alert">{{ $bag->first('seller_name') }}</p>@endif
            </div>
            <div>
                <label for="seller_ssm" class="block text-[12.5px] font-semibold text-brand-ink/70">SSM number</label>
                <input id="seller_ssm" name="seller_ssm" type="text" maxlength="50" value="{{ $val('seller_ssm', $invoicing['seller_ssm']) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="seller_tax_no" class="block text-[12.5px] font-semibold text-brand-ink/70">Tax registration number</label>
                <input id="seller_tax_no" name="seller_tax_no" type="text" maxlength="50" value="{{ $val('seller_tax_no', $invoicing['seller_tax_no']) }}" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2">
                <label for="seller_address" class="block text-[12.5px] font-semibold text-brand-ink/70">Address</label>
                <textarea id="seller_address" name="seller_address" rows="3" maxlength="300" class="{{ $input }}">{{ $val('seller_address', $invoicing['seller_address']) }}</textarea>
            </div>

            <div class="sm:col-span-2"><h3 class="mt-2 text-[14px] font-extrabold">Bank account for transfers</h3></div>
            <div>
                <label for="bank_name" class="block text-[12.5px] font-semibold text-brand-ink/70">Bank</label>
                <input id="bank_name" name="bank_name" type="text" maxlength="100" value="{{ $val('bank_name', $invoicing['bank_name']) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="bank_account_name" class="block text-[12.5px] font-semibold text-brand-ink/70">Account name</label>
                <input id="bank_account_name" name="bank_account_name" type="text" maxlength="100" value="{{ $val('bank_account_name', $invoicing['bank_account_name']) }}" class="{{ $input }}">
            </div>
            <div>
                <label for="bank_account_no" class="block text-[12.5px] font-semibold text-brand-ink/70">Account number</label>
                <input id="bank_account_no" name="bank_account_no" type="text" maxlength="50" value="{{ $val('bank_account_no', $invoicing['bank_account_no']) }}" class="{{ $input }}">
            </div>

            <div class="sm:col-span-2">
                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">
                    <span x-show="!busy">Save</span><span x-show="busy" x-cloak>Saving…</span>
                </button>
            </div>
        </form>
    </x-platform.panel>
</section>
