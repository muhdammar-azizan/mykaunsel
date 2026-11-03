{{-- A page of the planned website that is not built yet (docs/LANDING_PAGE.md): a calm page instead of a dead link or a 404. --}}
@php
    $name = $page ? \Illuminate\Support\Str::headline($page) : null;
@endphp

<x-landing-layout :title="__('landing.meta.soon_title')">
    <section class="px-5 py-24 lg:px-12 lg:py-36" aria-labelledby="soon-title">
        @if ($name)
            <p class="text-[12.5px] font-semibold uppercase tracking-[0.12em] text-brand-ink/50">{{ $name }}</p>
        @endif
        <h1 id="soon-title" class="lp-h mt-3 max-w-[20ch] text-[38px] font-normal sm:text-[52px]"><span class="font-medium">{{ __('landing.soon.strong') }}</span> <span class="text-[#6F7E9C]">{{ __('landing.soon.soft') }}</span></h1>
        <a href="{{ route('home') }}" class="mt-9 inline-flex min-h-[46px] items-center gap-2 rounded-md bg-brand-blue px-6 text-[15px] font-medium text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2"><span aria-hidden="true">‹</span> {{ __('landing.soon.back') }}</a>
    </section>
</x-landing-layout>
