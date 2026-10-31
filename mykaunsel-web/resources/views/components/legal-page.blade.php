@props(['title'])

<x-auth-centered-layout :title="$title.' — MyKaunsel'" content-max-width="720px">
    <article class="rounded-2xl border border-navy/10 bg-white/85 p-6 shadow-sm md:p-10">
        @if (config('legal.draft'))
            <p role="note" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[14px] leading-relaxed text-amber-900">
                {{ __('This is a draft that is still being reviewed. It describes what MyKaunsel does today. Parts marked "To be confirmed" will be filled in before launch.') }}
            </p>
        @endif

        <h1 class="text-[26px] font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-1 text-[13px] text-navy/55">{{ __('Last updated: :date', ['date' => config('legal.updated')]) }}</p>

        <div class="mt-6 space-y-4 text-[15px] leading-relaxed text-navy/80 [&_h2]:mt-8 [&_h2]:text-[18px] [&_h2]:font-semibold [&_h2]:text-navy [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-5 [&_a]:font-medium [&_a]:underline">
            {{ $slot }}
        </div>
    </article>
</x-auth-centered-layout>
