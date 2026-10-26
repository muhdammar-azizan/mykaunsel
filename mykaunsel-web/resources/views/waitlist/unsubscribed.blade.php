{{-- Shown every time the opt-out is used, also if it was already done or the address was deleted long ago. --}}
<x-auth-centered-layout title="You are opted out — MyKaunsel" content-max-width="520px">
    <div class="rounded-2xl border border-navy/10 bg-white/80 p-8 text-center shadow-sm">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-700" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <h1 class="mt-5 text-[22px] font-semibold tracking-tight">{{ __('You are opted out') }}</h1>
        <p class="mt-3 text-[14.5px] leading-relaxed text-navy/65">{{ __('We will not email you when :word sign-up reopens.', ['word' => $word]) }}</p>
    </div>
</x-auth-centered-layout>
