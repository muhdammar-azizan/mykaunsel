@push('styles')
    <style>
        .fp-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .fp-sampul { animation: sampul .45s cubic-bezier(.34,1.56,.64,1) both .1s; }
        @keyframes sampul { from { opacity: 0; transform: scale(.7); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .fp-lapis, .fp-sampul { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-simple-layout title="Forgot Password — MyKaunsel" content-max-width="360px">

    @if (session('status'))
        <div id="fpHantar" class="fp-lapis text-center">
            <span class="fp-sampul mx-auto grid h-[52px] w-[52px] place-items-center rounded-full" style="background:rgba(15,107,125,.10);color:#0F6B7D">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3 7 8.24 5.5a1.4 1.4 0 0 0 1.52 0L21 7"/></svg>
            </span>
            <h1 class="mt-6 text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Check your email') }}</h1>
            <p class="mx-auto mt-3 max-w-[40ch] text-[14px] leading-relaxed text-navy/70">
                {{ __("We've sent a password reset link to") }}
                <strong class="font-semibold text-navy">{{ old('email') }}</strong>.
                {{ __('The link expires in 30 minutes.') }}
            </p>
            <p class="mx-auto mt-5 max-w-[40ch] text-[12.5px] leading-relaxed text-navy/55">
                {{ __("Didn't receive the email? Check your spam folder or") }}
            </p>

            <form method="POST" action="{{ route('password.email') }}" class="mt-2 inline-block">
                @csrf
                <input type="hidden" name="email" value="{{ old('email') }}">
                <button type="submit" id="fpSemula" class="font-medium text-teal disabled:font-normal disabled:text-navy/35">{{ __('Send again') }}</button>
            </form>

            <div class="mt-8">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-[13.5px] text-navy/55"><span aria-hidden="true">←</span> {{ __('Back to log in') }}</a>
            </div>
        </div>
    @else
        <div id="fpBorang" class="fp-lapis">
            <h1 class="text-center text-[26px] font-semibold leading-[1.15] tracking-tightest md:text-[30px]">{{ __('Reset your password') }}</h1>
            <p class="mx-auto mt-3 max-w-[38ch] text-center text-[14px] leading-relaxed text-navy/55">{{ __("Enter the email linked to your account and we'll send you a reset link.") }}</p>

            <form id="fpForm" method="POST" action="{{ route('password.email') }}" class="mt-8" novalidate>
                @csrf
                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Email') }}</span>
                    <input type="email" id="fpEmel" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com" class="medan" @error('email') aria-invalid="true" @enderror>
                    @error('email')
                        <p class="mt-2 flex items-center gap-1.5 text-[12.5px]" style="color:#C4574A">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            {{ $message }}
                        </p>
                    @enderror
                </label>
                <button type="submit" class="btn-utama mt-5 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Send reset link') }}</button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-[13.5px] text-navy/55"><span aria-hidden="true">←</span> {{ __('Back to log in') }}</a>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var f = document.getElementById('fpForm');
                if (!f) return;
                var emel = document.getElementById('fpEmel');
                f.addEventListener('submit', function (e) {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emel.value.trim())) {
                        e.preventDefault();
                        emel.setAttribute('aria-invalid', 'true');
                        emel.classList.remove('goyang'); void emel.offsetWidth; emel.classList.add('goyang');
                        emel.focus();
                    }
                });
            });
        </script>
    @endpush
</x-auth-simple-layout>
