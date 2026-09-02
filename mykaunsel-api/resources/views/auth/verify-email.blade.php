<x-auth-layout
    title="Verify Email — MyKaunsel"
    image-slot="verify-email-visual"
    aside-title="One more step."
    aside-subtitle="Verify your email to activate your MyKaunsel account."
>
    @push('styles')
        <style>
            .ve-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
            @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
            #veSampul { animation: goyangSampul 4s ease-in-out infinite; }
            @keyframes goyangSampul { 0%,55%,100% { transform: rotate(0); } 60% { transform: rotate(-3deg); } 65% { transform: rotate(3deg); } 70% { transform: rotate(-2deg); } 75% { transform: rotate(0); } }
            #veToast { transition: opacity .3s ease, transform .3s ease; transform: translateY(8px); }
            #veToast.ve-show { opacity: 1; transform: translateY(0); }
            @media (prefers-reduced-motion: reduce) {
                .ve-lapis { animation: none; opacity: 1; transform: none; }
                #veSampul { animation: none; }
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

    <div id="veWaiting" class="ve-lapis text-center">
        <span id="veSampul" class="mx-auto grid h-16 w-16 place-items-center rounded-full" style="background:rgba(15,107,125,.10);color:#0F6B7D">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 8.24 5.5a1.4 1.4 0 0 0 1.52 0L21 7"/></svg>
        </span>
        <h1 class="mt-6 text-[28px] font-semibold leading-[1.15] tracking-tightest md:text-[32px]">{{ __('Check your email') }}</h1>
        <p class="mx-auto mt-3 max-w-[36ch] text-[14.5px] leading-relaxed text-navy/60">
            {{ __('We sent a verification link to') }}<br>
            <strong class="font-semibold text-navy">{{ auth()->user()->email }}</strong><br>
            {{ __('Click the link to activate your account.') }}
        </p>

        <div class="mt-7 flex gap-2.5 rounded-[10px] bg-navy/[.04] p-3.5 text-left">
            <svg width="15" height="15" class="mt-0.5 shrink-0 text-navy/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
            <p class="text-[12.5px] leading-relaxed text-navy/55">{{ __("This confirms your personal email address. If you're joining through an organization, your organization's domain is verified separately by our team.") }}</p>
        </div>

        <p class="mt-6 text-[13px] text-navy/50">{{ __("Didn't receive the email? Check your spam folder.") }}</p>

        <form method="POST" action="{{ route('verification.send') }}" class="mt-2 inline-block">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 text-[13.5px] font-medium text-teal">
                <span aria-hidden="true">↻</span> {{ __('Resend email') }}
            </button>
        </form>

        <div class="mt-8">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-[13px] text-navy/45">{{ __('Wrong email? Log out and use a different address') }}</button>
            </form>
        </div>
    </div>
</x-auth-layout>
