<x-auth-simple-layout title="Invitation — MyKaunsel" content-max-width="420px">
    <div class="text-center">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('This invitation link is no longer valid') }}</h1>
        <p class="mx-auto mt-3 max-w-[40ch] text-[14px] leading-relaxed text-navy/60">{{ __('It may have been used already, replaced by a newer one, cancelled, or it ran out of time. Ask the Platform Admin who invited you to send it again.') }}</p>
        <a href="{{ route('login') }}" class="btn-utama mt-7 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Go to sign in') }}</a>
    </div>
</x-auth-simple-layout>
