{{--
    Shown when a verification link's signed URL has expired or is invalid.
    Not yet wired into real signature-expiry detection — see
    docs/design-conversion notes.
--}}
<x-auth-layout
    title="Verify Email — MyKaunsel"
    image-slot="verify-email-visual"
    aside-title="Link expired."
    aside-subtitle="Request a fresh verification link to continue."
>
    @push('styles')
        <style>
            .ve-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
            @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
            #veToast { transition: opacity .3s ease, transform .3s ease; transform: translateY(8px); }
            #veToast.ve-show { opacity: 1; transform: translateY(0); }
            @media (prefers-reduced-motion: reduce) {
                .ve-lapis { animation: none; opacity: 1; transform: none; }
            }
        </style>
    @endpush

    @if (session('status') == 'verification-link-sent')
        <div id="veToast" class="pointer-events-none fixed left-0 right-0 top-24 z-50 flex justify-center opacity-0 md:left-[45%]" aria-live="polite">
            <span class="inline-flex items-center gap-1.5 rounded-full px-[18px] py-2.5 text-[13.5px] font-medium text-cream" style="background:#0F6B7D">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                {{ __('Verification email sent') }}
            </span>
        </div>
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var toast = document.getElementById('veToast');
                    if (toast) {
                        requestAnimationFrame(function () { toast.classList.add('ve-show'); });
                        setTimeout(function () { toast.classList.remove('ve-show'); }, 3000);
                    }
                });
            </script>
        @endpush
    @endif

    <div id="veExpired" class="ve-lapis text-center">
        <span class="grid h-14 w-14 mx-auto place-items-center rounded-full" style="background:rgba(217,143,74,.14);color:#D98F4A">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        </span>
        <h1 class="mt-6 text-[26px] font-semibold leading-[1.15] tracking-tightest">{{ __('This link has expired') }}</h1>
        <p class="mx-auto mt-3 max-w-[34ch] text-[14px] leading-relaxed text-navy/60">{{ __('Verification links are valid for 24 hours. Request a new one below.') }}</p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn-utama mt-7 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">
                {{ __('Send new verification email') }}
            </button>
        </form>
    </div>
</x-auth-layout>
