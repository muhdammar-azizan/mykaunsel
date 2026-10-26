{{-- A temporary page: search engines must not keep it (the answer is a 503 with X-Robots-Tag too). --}}
@push('styles')
    <meta name="robots" content="noindex, nofollow">
@endpush

<x-auth-centered-layout title="Maintenance — MyKaunsel" content-max-width="520px">
    <div class="rounded-2xl border border-navy/10 bg-white/80 p-8 text-center shadow-sm">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber/15 text-amber" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.5-.5-.5-2.5z"/></svg>
        </span>
        <h1 class="mt-5 text-[22px] font-semibold tracking-tight">{{ __('Down for maintenance') }}</h1>
        <p class="mt-3 whitespace-pre-line break-words text-[14.5px] leading-relaxed text-navy/65">{{ $message }}</p>
        @if (! empty($reopensAt))
            <p class="mt-4 text-[13.5px] font-medium text-navy/80">{{ __('We expect to be back around :time.', ['time' => $reopensAt->format('j M Y, g:i A').' ('.$reopensAt->timezoneName.', UTC'.$reopensAt->format('P').')']) }}</p>
        @endif
        {{-- Already signed in (perhaps in another role)? The sign-in page would only send them back here, so go to the role chooser. --}}
        @auth
            <a href="{{ route('context.select') }}" class="mt-6 inline-block text-[13px] font-medium text-teal">{{ __('Platform Admin: choose your role') }}</a>
        @else
            <a href="{{ route('login') }}" class="mt-6 inline-block text-[13px] font-medium text-teal">{{ __('Platform Admin sign in') }}</a>
        @endauth
    </div>
</x-auth-centered-layout>
