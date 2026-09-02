<x-auth-layout
    title="Register as a Counselor — MyKaunsel"
    image-slot="counselor-signup-visual"
    aside-title="Join MyKaunsel as a registered counselor."
    aside-subtitle="Verify your LKM credentials and set up your practice."
    content-max-width="380px"
>
    <div class="text-center">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Coming soon') }}</h1>
        <p class="mt-3 text-[14px] leading-relaxed text-navy/60">
            {{ __('The counselor sign-up wizard is being built in the next phase.') }}
        </p>
        <a href="{{ route('register.type') }}" class="mt-7 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55">
            <span aria-hidden="true">←</span> {{ __('Back') }}
        </a>
    </div>
</x-auth-layout>
