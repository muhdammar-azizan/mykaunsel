<x-auth-layout
    title="Register Your Organization — MyKaunsel"
    image-slot="org-signup-visual"
    aside-title="Bring counseling support to your organization."
    aside-subtitle="Set up your organization's MyKaunsel workspace."
    content-max-width="380px"
>
    <div class="text-center">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Coming soon') }}</h1>
        <p class="mt-3 text-[14px] leading-relaxed text-navy/60">
            {{ __('The organization sign-up wizard is being built in the next phase.') }}
        </p>
        <a href="{{ route('register.type') }}" class="mt-7 inline-flex items-center gap-1.5 text-[13.5px] text-navy/55">
            <span aria-hidden="true">←</span> {{ __('Back') }}
        </a>
    </div>
</x-auth-layout>
