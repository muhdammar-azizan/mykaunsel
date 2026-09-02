{{--
    Transient confirmation shown right after a user clicks the verification
    link, before auto-redirecting to the dashboard. Not yet wired into the
    real verification.verify flow — see docs/design-conversion notes.
--}}
<x-auth-layout
    title="Verify Email — MyKaunsel"
    image-slot="verify-email-visual"
    aside-title="You're verified."
    aside-subtitle="Taking you to your account."
>
    @push('styles')
        <style>
            .ve-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
            @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
            .ve-spring { animation: sampul .45s cubic-bezier(.34,1.56,.64,1) both; }
            @keyframes sampul { from { opacity: 0; transform: scale(.7); } to { opacity: 1; transform: none; } }
            #veBar { transition: width 1.5s linear; }
            @media (prefers-reduced-motion: reduce) {
                .ve-lapis, .ve-spring { animation: none; opacity: 1; transform: none; }
            }
        </style>
    @endpush

    <div id="veSuccess" class="ve-lapis text-center">
        <span class="ve-spring grid h-16 w-16 mx-auto place-items-center rounded-full" style="background:#0F6B7D;color:#FAF8F5">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 6 6L20 6"/></svg>
        </span>
        <h1 class="mt-6 text-[26px] font-semibold leading-[1.15] tracking-tightest">{{ __('Email verified') }}</h1>
        <p class="mx-auto mt-3 max-w-[34ch] text-[14px] leading-relaxed text-navy/60">{{ __('Your account is now active. Taking you to your account...') }}</p>
        <div class="mx-auto mt-6 h-[2px] w-full max-w-[220px] overflow-hidden rounded-full bg-navy/10">
            <div id="veBar" class="h-full rounded-full" style="background:#0F6B7D;width:0%"></div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var bar = document.getElementById('veBar');
                setTimeout(function () { bar.style.width = '100%'; }, 30);
                setTimeout(function () { window.location.href = @json(route('dashboard')); }, 1700);
            });
        </script>
    @endpush
</x-auth-layout>
