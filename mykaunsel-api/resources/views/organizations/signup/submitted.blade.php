<x-auth-simple-layout title="Application Submitted — MyKaunsel" content-max-width="420px">
    <div class="text-center">
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full" style="background:rgba(15,107,125,.10);color:#0F6B7D">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 8.24 5.5a1.4 1.4 0 0 0 1.52 0L21 7"/></svg>
        </span>
        <h1 class="mt-6 text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Application submitted') }}</h1>
        <p class="mx-auto mt-3 max-w-[38ch] text-[14px] leading-relaxed text-navy/60">
            {{ __('Thanks for registering :name on MyKaunsel. Your application is being reviewed.', ['name' => $organization->name]) }}
        </p>
        <p class="mx-auto mt-3 max-w-[38ch] text-[12.5px] leading-relaxed text-navy/45">
            {{ __('Domain verification and application review screens are coming in the next phase — for now your organization has been created with pending status.') }}
        </p>
        <a href="{{ route('login') }}" class="mt-7 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55">
            <span aria-hidden="true">←</span> {{ __('Back to log in') }}
        </a>
    </div>
</x-auth-simple-layout>
