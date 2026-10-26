<x-auth-centered-layout title="Sign-up closed — MyKaunsel" content-max-width="520px">
    <div class="rounded-2xl border border-navy/10 bg-white/80 p-8 text-center shadow-sm">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-amber/15 text-amber" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        </span>
        <h1 class="mt-5 text-[22px] font-semibold tracking-tight">{{ __('Sign-up is closed for now') }}</h1>
        <p class="mt-3 whitespace-pre-line break-words text-[14.5px] leading-relaxed text-navy/65">{{ $message }}</p>
        {{-- The waitlist: for Organization and Counselor sign-up only (never individuals). The same answer for everyone who submits. --}}
        @if (session('waitlist_status'))
            <p class="mt-6 rounded-xl bg-emerald-50 px-4 py-3 text-[13.5px] text-emerald-800" role="status">{{ session('waitlist_status') }}</p>
        @elseif (! empty($waitlistTypes))
            @if (session('waitlist_error'))
                <p class="mt-6 rounded-xl bg-amber/10 px-4 py-3 text-[13.5px] text-navy/80" role="alert">{{ session('waitlist_error') }}</p>
            @endif
            @foreach ($waitlistTypes as $waitlistType)
                <x-waitlist-form :type="$waitlistType" />
            @endforeach
        @endif

        <p class="mt-6 text-[13.5px] text-navy/55">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-teal">{{ __('Log in') }}</a>
        </p>
    </div>
</x-auth-centered-layout>
