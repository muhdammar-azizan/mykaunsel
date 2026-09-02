@push('styles')
    <style>
        .masuk { animation: masuk .5s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .goyang { animation: goyang .3s ease-in-out 1; }
        @keyframes goyang { 0%,100%{transform:translateX(0)} 20%{transform:translateX(-6px)} 50%{transform:translateX(6px)} 80%{transform:translateX(-3px)} }
        .putar { animation: putar .7s linear infinite; }
        @keyframes putar { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            .masuk { animation: none; opacity: 1; transform: none; }
            .goyang { animation: none; }
        }
    </style>
@endpush

<x-auth-layout
    title="Log in — MyKaunsel"
    image-slot="login-visual"
    aside-title="The right support, connected the right way."
    aside-subtitle="Log in to continue to your account."
    content-max-width="300px"
>
    <div class="masuk">
        <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Log in to MyKaunsel') }}</h1>
        <p class="mt-2 text-[13.5px] text-navy/60">{{ __('Enter your email and password') }}</p>

        <x-auth-session-status class="mt-4" :status="session('status')" />

        <form class="mt-6" id="borang" method="POST" action="{{ route('login') }}" novalidate>
            @csrf

            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Email') }}</span>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="name@example.com" class="medan" @error('email') aria-invalid="true" @enderror autofocus>
                @error('email')
                    <p class="mt-2 flex items-center gap-1.5 text-[12.5px]" style="color:#C4574A">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </label>

            <label class="mt-4 block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Password') }}</span>
                <span class="relative block">
                    <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" class="medan pr-12">
                    <button type="button" id="mata" aria-label="Show password"
                            class="absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy">
                        <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                    </button>
                </span>
            </label>
            <div class="mt-2 flex justify-end">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-[13.5px] text-navy/60 transition-colors duration-200 hover:text-teal">{{ __('Forgot password?') }}</a>
                @endif
            </div>

            <label class="mt-4 flex cursor-pointer items-center gap-2.5">
                <input type="checkbox" name="remember" class="h-[17px] w-[17px] rounded-[5px] border border-navy/25 accent-teal">
                <span class="text-[14.5px] text-navy/75">{{ __('Remember me') }}</span>
            </label>

            <button type="submit" id="btnMasuk"
                    class="btn-utama mt-5 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">
                <span data-btn="label">{{ __('Continue') }}</span>
                <svg data-btn="spin" hidden class="putar" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56" /></svg>
            </button>

            <div class="my-5 flex items-center gap-4">
                <span class="h-px flex-1 bg-navy/12"></span>
                <span class="text-[13px] text-navy/45">{{ __('or') }}</span>
                <span class="h-px flex-1 bg-navy/12"></span>
            </div>

            <button type="button" disabled title="{{ __('Coming soon') }}" class="group flex h-[44px] w-full items-center justify-center gap-0 overflow-hidden rounded-full border border-navy/20 bg-white text-[14px] font-medium text-navy/50 cursor-not-allowed">
                <svg width="18" height="18" viewBox="0 0 48 48" class="shrink-0" aria-hidden="true"><path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84a10.13 10.13 0 0 1-4.4 6.65v5.52h7.11c4.16-3.83 6.57-9.47 6.57-16.18"/><path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.3-9.07H4.34v5.7A22 22 0 0 0 24 46"/><path fill="#FBBC05" d="M11.7 28.18A13.2 13.2 0 0 1 11.01 24c0-1.45.25-2.86.69-4.18v-5.7H4.34A21.99 21.99 0 0 0 2 24c0 3.55.85 6.91 2.34 9.88z"/><path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.36 5.7c1.72-5.2 6.57-9.07 12.3-9.07"/></svg>
                <span class="pl-[10px]">{{ __('Continue with Google') }}</span>
            </button>
        </form>

        @if (Route::has('register'))
            <p class="mt-7 flex items-center justify-center gap-1.5 text-[14px] text-navy/60">
                {{ __("Don't have an account?") }}
                <a href="{{ route('register') }}" class="font-medium text-teal">{{ __('Sign up') }}</a>
            </p>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var f = document.getElementById('borang');
                var btn = document.getElementById('btnMasuk');
                var label = btn.querySelector('[data-btn="label"]');
                var spin = btn.querySelector('[data-btn="spin"]');
                var email = document.getElementById('email');

                function sah() { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()); }

                f.addEventListener('submit', function (e) {
                    if (!sah()) {
                        e.preventDefault();
                        email.setAttribute('aria-invalid', 'true');
                        email.classList.remove('goyang'); void email.offsetWidth; email.classList.add('goyang');
                        email.focus();
                        return;
                    }
                    btn.disabled = true; label.hidden = true; spin.hidden = false;
                });

                var mata = document.getElementById('mata');
                var password = document.getElementById('password');
                var iTutup = mata.querySelector('[data-mata="tutup"]');
                var iBuka = mata.querySelector('[data-mata="buka"]');
                mata.addEventListener('click', function () {
                    var lihat = password.type === 'password';
                    password.type = lihat ? 'text' : 'password';
                    iTutup.hidden = lihat; iBuka.hidden = !lihat;
                    mata.setAttribute('aria-label', lihat ? 'Hide password' : 'Show password');
                });
            });
        </script>
    @endpush
</x-auth-layout>
