@push('styles')
    <style>
        .cs-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .cs-lapis { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-layout
    title="Register as a Counselor — MyKaunsel"
    image-slot="counselor-signup-visual"
    aside-title="Bring your practice online."
    aside-subtitle="Join as a verified counselor and accept bookings on your own schedule — no need to build your own system."
    content-max-width="420px"
>
    @error('lkm')
        <div id="lkmNotFound" class="cs-lapis text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full" style="background:rgba(196,87,74,.1);color:#C4574A">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
            </span>
            <h1 class="mt-6 text-[26px] font-semibold leading-[1.15] tracking-tightest">{{ __("We couldn't verify your registration") }}</h1>
            <p class="mx-auto mt-2.5 max-w-[36ch] text-[14px] leading-relaxed text-navy/55">{{ __("We couldn't find a matching record in the LKM directory.") }}</p>

            <div class="mt-6 rounded-xl p-4 text-left" style="background:rgba(196,87,74,.06);border-left:3px solid #C4574A">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em]" style="color:#C4574A">{{ __('Possible reason') }}</p>
                <p class="mt-1.5 text-[13.5px] leading-relaxed text-navy">{{ $message }}</p>
            </div>

            <div class="mt-6 flex gap-3">
                <a href="{{ route('counselors.signup.create') }}" class="flex-1 rounded-full border border-navy/25 px-6 py-3 text-[14px] font-medium text-navy transition-all duration-300 hover:-translate-y-0.5 hover:border-navy hover:bg-navy/5">{{ __('Try again') }}</a>
                <a href="mailto:support@mykaunsel.com" class="flex-1 rounded-full px-6 py-3 text-center text-[14px] font-medium text-white transition-all duration-300 hover:-translate-y-0.5" style="background:#4A7DFF">{{ __('Contact support') }}</a>
            </div>
        </div>
    @else
        <div id="csForm" class="cs-lapis">
            <h1 class="text-[24px] font-semibold leading-[1.15] tracking-tightest">{{ __('Register as a Counselor') }}</h1>
            <p class="mt-2 text-[13.5px] leading-relaxed text-navy/60">{{ __('We will verify your credentials with the Malaysian Board of Counsellors (LKM) before your account is activated.') }}</p>

            <form id="borang" method="POST" action="{{ route('counselors.signup.store') }}" class="mt-6" novalidate>
                @csrf

                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Full Name') }}</span>
                    <input type="text" name="name" id="csName" value="{{ old('name') }}" placeholder="Ahmad bin Ismail" autocomplete="name" class="medan" @error('name') aria-invalid="true" @enderror>
                    @error('name')
                        <p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Must match your name exactly as registered with LKM.') }}</p>
                    @enderror
                </label>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Email') }}</span>
                    <input type="email" name="email" id="csEmail" value="{{ old('email') }}" placeholder="name@example.com" autocomplete="email" class="medan" @error('email') aria-invalid="true" @enderror>
                    @error('email')
                        <p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[11.5px] leading-relaxed text-navy/45">{{ __('Use your personal email. This is separate from your LKM records.') }}</p>
                    @enderror
                </label>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('KB Number') }}</span>
                        <input type="text" name="kb_number" id="csKB" value="{{ old('kb_number') }}" placeholder="KB08253" maxlength="7" class="medan" @error('kb_number') aria-invalid="true" @enderror>
                        @error('kb_number')<p class="mt-1.5 text-[11.5px] leading-relaxed" style="color:#C4574A">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('PA Number') }}</span>
                        <input type="text" name="pa_number" id="csPA" value="{{ old('pa_number') }}" placeholder="PA07841" maxlength="7" class="medan" @error('pa_number') aria-invalid="true" @enderror>
                        @error('pa_number')<p class="mt-1.5 text-[11.5px] leading-relaxed" style="color:#C4574A">{{ $message }}</p>@enderror
                    </label>
                </div>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Password') }}</span>
                    <span class="relative block">
                        <input type="password" name="password" id="csPassword" placeholder="••••••••" class="medan pr-12" autocomplete="new-password" @error('password') aria-invalid="true" @enderror>
                        <button type="button" class="cs-mata absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="csPassword" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    @error('password')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>
                <ul class="mt-3 flex flex-col gap-2.5 rounded-[10px] p-3" style="background:rgba(14,42,51,.04)">
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="len" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('At least 8 characters') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="upper" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one uppercase letter') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="num" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one number') }}</span>
                    </li>
                    <li class="rp-syarat flex items-center gap-2.5 text-[12.5px] text-navy/55" data-syarat="spec" data-keadaan="kosong">
                        <span class="rp-ikon grid h-[16px] w-[16px] shrink-0 place-items-center rounded-full border border-navy/30" aria-hidden="true">
                            <svg class="rp-semak" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                            <svg class="rp-silang" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                        </span><span>{{ __('Contains one special character') }}</span>
                    </li>
                </ul>

                <label class="mt-4 block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">{{ __('Confirm Password') }}</span>
                    <span class="relative block">
                        <input type="password" name="password_confirmation" id="csConfirm" placeholder="••••••••" class="medan pr-12" autocomplete="new-password">
                        <button type="button" class="cs-mata absolute right-1 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-lg text-navy/45 transition-colors duration-200 hover:text-navy" data-untuk="csConfirm" aria-label="Show password">
                            <svg data-mata="tutup" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.87 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.87 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-mata="buka" hidden width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.64 0 8.57 3.22 9.94 6.65a1 1 0 0 1 0 .7 10.9 10.9 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2.06 11.65a1 1 0 0 0 0 .7A10.75 10.75 0 0 0 12 19a9.7 9.7 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </span>
                    <p id="csConfirmNota" hidden class="mt-2 flex items-center gap-1.5 text-[12.5px]"></p>
                    @error('password_confirmation')<p class="mt-1.5 text-[11.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
                </label>

                <label class="mt-4 flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="confirm_accurate" id="csChkAccurate" value="1" @checked(old('confirm_accurate')) class="mt-0.5 h-[17px] w-[17px] shrink-0 rounded-[5px] border border-navy/25 accent-teal">
                    <span class="text-[13.5px] leading-snug text-navy/75">{{ __('I confirm this information is accurate and matches my LKM registration records') }}</span>
                </label>
                <label class="mt-3 flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="confirm_terms" id="csChkTerms" value="1" @checked(old('confirm_terms')) class="mt-0.5 h-[17px] w-[17px] shrink-0 rounded-[5px] border border-navy/25 accent-teal">
                    <span class="text-[13.5px] leading-snug text-navy/75">{{ __('I agree to the Terms of Service and Privacy Policy') }}</span>
                </label>

                <button type="submit" id="csSubmitBtn" class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">
                    <span data-btn="label">{{ __('Verify and Continue') }}</span>
                    <svg data-btn="spin" hidden class="putar" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.22-8.56"/></svg>
                </button>
            </form>

            <p class="mt-6 text-center text-[13.5px] text-navy/60">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-medium text-teal">{{ __('Log in') }}</a></p>
        </div>
    @enderror

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var f = document.getElementById('borang');
                if (!f) return;
                var btn = document.getElementById('csSubmitBtn');
                var label = btn.querySelector('[data-btn="label"]');
                var spin = btn.querySelector('[data-btn="spin"]');

                f.addEventListener('submit', function () {
                    btn.disabled = true; label.hidden = true; spin.hidden = false;
                });

                [].slice.call(document.querySelectorAll('.cs-mata')).forEach(function (b) {
                    var medan = document.getElementById(b.getAttribute('data-untuk'));
                    var t = b.querySelector('[data-mata="tutup"]'), o = b.querySelector('[data-mata="buka"]');
                    b.addEventListener('click', function () {
                        var lihat = medan.type === 'password';
                        medan.type = lihat ? 'text' : 'password';
                        t.hidden = lihat; o.hidden = !lihat;
                    });
                });

                var pass = document.getElementById('csPassword');
                var confirmEl = document.getElementById('csConfirm');
                var syarat = [].slice.call(document.querySelectorAll('.rp-syarat'));
                var nota = document.getElementById('csConfirmNota');
                var tunda = null;
                var uji = {
                    len: function (v) { return v.length >= 8; },
                    upper: function (v) { return /[A-Z]/.test(v); },
                    num: function (v) { return /[0-9]/.test(v); },
                    spec: function (v) { return /[^A-Za-z0-9]/.test(v); }
                };
                function semak() {
                    var v = pass.value;
                    syarat.forEach(function (li) {
                        var k = li.getAttribute('data-syarat'), lulus = uji[k](v);
                        li.setAttribute('data-keadaan', v.length === 0 ? 'kosong' : (lulus ? 'ok' : 'gagal'));
                    });
                }
                function notaPadan(lambat) {
                    if (confirmEl.value.length === 0) { nota.hidden = true; confirmEl.removeAttribute('aria-invalid'); return; }
                    if (confirmEl.value === pass.value) {
                        nota.hidden = false; nota.style.color = '#0F6B7D';
                        nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg> Passwords match';
                        confirmEl.removeAttribute('aria-invalid');
                        return;
                    }
                    if (!lambat) { nota.hidden = false; nota.style.color = 'rgba(14,42,51,.55)'; nota.textContent = 'Keep typing...'; return; }
                    nota.hidden = false; nota.style.color = '#C4574A';
                    nota.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Passwords don\'t match';
                    confirmEl.setAttribute('aria-invalid', 'true');
                }
                pass.addEventListener('input', function () { semak(); notaPadan(false); });
                confirmEl.addEventListener('input', function () {
                    notaPadan(false);
                    clearTimeout(tunda);
                    tunda = setTimeout(function () { notaPadan(true); }, 400);
                });
            });
        </script>
    @endpush
</x-auth-layout>
