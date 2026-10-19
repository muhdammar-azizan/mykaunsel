@props(['bag' => 'default'])

{{-- New password + confirmation with show/hide, live requirements checklist and live "passwords match" note.
     Requirements mirror Password::defaults() in AppServiceProvider (the server is still the source of truth). --}}
@php
    $input = 'block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 pr-16 text-[14px] text-brand-ink placeholder:text-brand-ink/35 focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
    $errors = $errors->getBag($bag);
@endphp

<div x-data="{
        pwd: '',
        confirm: '',
        showNew: false,
        showConfirm: false,
        rules: [
            { key: 'len', text: 'At least 8 characters', test: v => v.length >= 8 },
            { key: 'lower', text: 'One lowercase letter', test: v => /[a-z]/.test(v) },
            { key: 'upper', text: 'One uppercase letter', test: v => /[A-Z]/.test(v) },
            { key: 'num', text: 'One number', test: v => /[0-9]/.test(v) },
            { key: 'spec', text: 'One special character', test: v => /[^A-Za-z0-9]/.test(v) },
        ],
        get allMet() { return this.rules.every(r => r.test(this.pwd)); },
        get matches() { return this.confirm.length > 0 && this.confirm === this.pwd; },
        get mismatch() { return this.confirm.length > 0 && this.confirm !== this.pwd; },
        get valid() { return this.allMet && this.matches; },
    }" x-modelable="valid" {{ $attributes }}>

    <div>
        <label for="password" class="{{ $label }}">New password</label>
        <div class="relative mt-1.5">
            <input id="password" name="password" x-model="pwd" :type="showNew ? 'text' : 'password'" required autocomplete="new-password" class="{{ $input }}">
            <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="showNew ? 'Hide' : 'Show'">Show</button>
        </div>
        @if ($errors->has('password'))
            <p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $errors->first('password') }}</p>
        @endif

        <ul class="mt-3 grid grid-cols-1 gap-1.5 sm:grid-cols-2" aria-label="Password requirements">
            <template x-for="r in rules" :key="r.key">
                <li class="flex items-center gap-2 text-[12.5px] transition-colors"
                    :class="pwd.length === 0 ? 'text-brand-ink/50' : (r.test(pwd) ? 'text-emerald-700' : 'text-brand-red')">
                    <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full border transition-colors"
                          :class="pwd.length === 0 ? 'border-brand-ink/25' : (r.test(pwd) ? 'border-emerald-600 bg-emerald-600' : 'border-brand-red bg-brand-red')" aria-hidden="true">
                        <svg x-show="pwd.length > 0 && r.test(pwd)" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                        <svg x-show="pwd.length > 0 && !r.test(pwd)" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </span>
                    <span x-text="r.text"></span>
                </li>
            </template>
        </ul>
    </div>

    <div class="mt-4">
        <label for="password_confirmation" class="{{ $label }}">Confirm new password</label>
        <div class="relative mt-1.5">
            <input id="password_confirmation" name="password_confirmation" x-model="confirm" :type="showConfirm ? 'text' : 'password'" required autocomplete="new-password"
                   :aria-invalid="mismatch.toString()" class="{{ $input }}">
            <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="showConfirm ? 'Hide' : 'Show'">Show</button>
        </div>
        <p x-show="matches" x-cloak class="mt-1.5 text-[12.5px] font-medium text-emerald-700">Passwords match</p>
        <p x-show="mismatch" x-cloak class="mt-1.5 text-[12.5px] font-medium text-brand-red">Passwords don't match</p>
    </div>
</div>
