@push('styles')
    <style>
        .fp-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .fp-sampul { animation: sampul .45s cubic-bezier(.34,1.56,.64,1) both .1s; }
        @keyframes sampul { from { opacity: 0; transform: scale(.7); } to { opacity: 1; transform: none; } }
        .rp-syarat { transition: color .2s ease; }
        .rp-ikon { transition: background-color .2s ease, border-color .2s ease; }
        .rp-semak, .rp-silang { display: none; }
        .rp-syarat[data-keadaan="ok"] { color: rgba(14,42,51,.75); }
        .rp-syarat[data-keadaan="ok"] .rp-ikon { background: #0F6B7D; border-color: #0F6B7D; }
        .rp-syarat[data-keadaan="ok"] .rp-semak { display: block; }
        .rp-syarat[data-keadaan="gagal"] { color: #C4574A; }
        .rp-syarat[data-keadaan="gagal"] .rp-ikon { background: #C4574A; border-color: #C4574A; }
        .rp-syarat[data-keadaan="gagal"] .rp-silang { display: block; }
        @media (prefers-reduced-motion: reduce) {
            .fp-lapis, .fp-sampul { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-simple-layout title="Reset Password — MyKaunsel" content-max-width="380px">

    @error('email')
        <div id="rpLuput" class="fp-lapis text-center">
            <span class="fp-sampul mx-auto grid h-[52px] w-[52px] place-items-center rounded-full" style="background:rgba(217,143,74,.12);color:#D98F4A">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </span>
            <h1 class="mt-6 text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('This link has expired') }}</h1>
            <p class="mx-auto mt-3 max-w-[38ch] text-[14px] leading-relaxed text-navy/60">{{ $message }}</p>
            <a href="{{ route('password.request') }}" class="btn-utama mt-7 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Request new link') }}</a>
        </div>
    @else
        <div id="rpBorang" class="fp-lapis">
            <h1 class="text-[26px] font-semibold leading-[1.15] tracking-tightest md:text-[30px]">{{ __('Reset your password') }}</h1>
            <p class="mt-2.5 text-[14px] leading-relaxed text-navy/55">{{ __('Please enter a new password below.') }}</p>

            <form id="rpForm" method="POST" action="{{ route('password.store') }}" class="mt-7" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="email" value="{{ old('email', $request->email) }}">

                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('New password') }}</span>
                    <span class="relative block">
                        <input type="password" id="rpBaru" name="password" placeholder="Type your new password" class="medan pr-12" autocomplete="new-password">
                        <button type="button" class="rp-mata absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="rpBaru" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    @error('password')
                        <p class="mt-2 flex items-center gap-1.5 text-[12.5px]" style="color:#C4574A">{{ $message }}</p>
                    @enderror
                </label>

                <ul class="mt-3 flex flex-col gap-2.5 rounded-[10px] p-3" style="background:rgba(14,42,51,.04)">
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="len" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span>
                        <span>{{ __('At least 8 characters') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="upper" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span>
                        <span>{{ __('Contains one uppercase letter') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="num" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span>
                        <span>{{ __('Contains one number') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="spec" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span>
                        <span>{{ __('Contains one special character') }}</span>
                    </li>
                </ul>

                <label class="mt-5 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Confirm password') }}</span>
                    <span class="relative block">
                        <input type="password" id="rpSah" name="password_confirmation" placeholder="Repeat your new password" class="medan pr-12" autocomplete="new-password">
                        <button type="button" class="rp-mata absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="rpSah" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    <p id="rpPadan" hidden class="mt-2 flex items-center gap-1.5 text-[12.5px]"></p>
                    @error('password_confirmation')
                        <p class="mt-2 flex items-center gap-1.5 text-[12.5px]" style="color:#C4574A">{{ $message }}</p>
                    @enderror
                </label>

                <button type="submit" id="rpBtn" class="btn-utama mt-6 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Confirm') }}</button>
            </form>
        </div>
    @enderror

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var f = document.getElementById('rpForm');
                if (!f) return;
                var baru = document.getElementById('rpBaru');
                var sah = document.getElementById('rpSah');
                var nota = document.getElementById('rpPadan');
                var syarat = [].slice.call(document.querySelectorAll('.rp-syarat'));
                var tunda = null;
                var uji = {
                    len: function (v) { return v.length >= 8; },
                    upper: function (v) { return /[A-Z]/.test(v); },
                    num: function (v) { return /[0-9]/.test(v); },
                    spec: function (v) { return /[^A-Za-z0-9]/.test(v); }
                };

                [].slice.call(document.querySelectorAll('.rp-mata')).forEach(function (b) {
                    var medan = document.getElementById(b.getAttribute('data-untuk'));
                    var t = b.querySelector('[data-mata="tutup"]'), o = b.querySelector('[data-mata="buka"]');
                    b.addEventListener('click', function () {
                        var lihat = medan.type === 'password';
                        medan.type = lihat ? 'text' : 'password';
                        t.hidden = lihat; o.hidden = !lihat;
                        b.setAttribute('aria-label', lihat ? 'Hide password' : 'Show password');
                    });
                });

                function semak() {
                    var v = baru.value;
                    syarat.forEach(function (li) {
                        var k = li.getAttribute('data-syarat'), lulus = uji[k](v);
                        li.setAttribute('data-keadaan', v.length === 0 ? 'kosong' : (lulus ? 'ok' : 'gagal'));
                    });
                }

                function notaPadan(lambat) {
                    if (sah.value.length === 0) { nota.hidden = true; sah.removeAttribute('aria-invalid'); return; }
                    if (sah.value === baru.value) {
                        nota.hidden = false;
                        nota.style.color = '#0F6B7D';
                        nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg> Passwords match';
                        sah.removeAttribute('aria-invalid');
                        return;
                    }
                    if (!lambat) {
                        nota.hidden = false;
                        nota.style.color = 'rgba(14,42,51,.55)';
                        nota.textContent = 'Keep typing...';
                        return;
                    }
                    nota.hidden = false;
                    nota.style.color = '#C4574A';
                    nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Passwords don\'t match';
                    sah.setAttribute('aria-invalid', 'true');
                }

                baru.addEventListener('input', function () { semak(); notaPadan(false); });
                sah.addEventListener('input', function () {
                    semak(); notaPadan(false);
                    clearTimeout(tunda);
                    tunda = setTimeout(function () { notaPadan(true); }, 400);
                });
            });
        </script>
    @endpush
</x-auth-simple-layout>
