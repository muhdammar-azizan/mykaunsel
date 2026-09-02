@push('styles')
    <style>
        .jk-pilih { opacity: 0; transform: translateY(16px);
            animation: masuk .5s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda, 0s);
            transition: border-color .25s ease, background-color .25s ease, transform .25s ease, box-shadow .25s ease; }
        .jk-pilih:hover { border-color: rgba(14,42,51,.25); transform: translateY(-2px); box-shadow: 0 16px 36px -18px rgba(14,42,51,.25); }
        .jk-pilih[aria-pressed="true"] { border: 2px solid var(--aksen); padding: 31px;
            background: color-mix(in srgb, var(--aksen) 4%, #FAF8F5); transform: translateY(-2px);
            box-shadow: 0 16px 36px -18px rgba(14,42,51,.2); }
        .jk-radio { transition: background-color .25s ease, border-color .25s ease; }
        .jk-pilih[aria-pressed="true"] .jk-radio { background: var(--aksen); border-color: var(--aksen); }
        .jk-semak { transform: scale(0); transition: transform .2s cubic-bezier(.34,1.56,.64,1); }
        .jk-pilih[aria-pressed="true"] .jk-semak { transform: scale(1); }
        #btnTeruskan { transition: opacity .3s ease, background-color .2s ease, transform .2s ease; }
        #btnTeruskan:disabled { opacity: .4; cursor: default; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) {
            .jk-pilih, .jk-pilih:hover, .jk-pilih[aria-pressed="true"] { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-centered-layout title="Choose Account Type — MyKaunsel" content-max-width="720px">
    <h1 class="text-center text-[28px] font-semibold leading-[1.15] tracking-tightest md:text-[34px]">{{ __('How would you like to use MyKaunsel?') }}</h1>
    <p class="mt-3 text-center text-[15px] text-navy/55">{{ __("We'll set up the right experience for you.") }}</p>

    <div class="mt-12 grid gap-6 md:grid-cols-2">
        <button type="button" class="jk-pilih group relative flex flex-col items-center rounded-2xl border border-navy/12 bg-cream p-8 text-center" data-href="{{ route('organizations.signup.create') }}" data-aksen="#D98F4A" aria-pressed="false" style="--aksen:#D98F4A; --tunda:0.00s">
            <span class="jk-radio absolute right-5 top-5 grid h-[22px] w-[22px] place-items-center rounded-full border-2 border-navy/20" aria-hidden="true">
                <svg class="jk-semak" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
            </span>
            <span class="grid h-[84px] w-[84px] place-items-center rounded-2xl" style="background:color-mix(in srgb, var(--aksen) 10%, transparent); color:var(--aksen)">
                <svg width="48" height="48" viewBox="0 0 48 48" aria-hidden="true"><rect x="7" y="20" width="17" height="24" rx="3" fill="currentColor" opacity=".3"/><rect x="20" y="9" width="21" height="35" rx="3" fill="currentColor" opacity=".6"/><rect x="27" y="17" width="7" height="7" rx="1.6" fill="#FAF8F5"/><rect x="27" y="29" width="7" height="7" rx="1.6" fill="#FAF8F5"/></svg>
            </span>
            <span class="mt-6 block text-[17px] font-semibold tracking-tight text-navy">{{ __('Organization') }}</span>
            <span class="mt-2.5 block max-w-[26ch] text-[13.5px] leading-relaxed text-navy/55">{{ __('Manage counselors, availability, and bookings for your organization.') }}</span>
        </button>
        <button type="button" class="jk-pilih group relative flex flex-col items-center rounded-2xl border border-navy/12 bg-cream p-8 text-center" data-href="{{ route('counselors.signup.create') }}" data-aksen="#4A7DFF" aria-pressed="false" style="--aksen:#4A7DFF; --tunda:0.08s">
            <span class="jk-radio absolute right-5 top-5 grid h-[22px] w-[22px] place-items-center rounded-full border-2 border-navy/20" aria-hidden="true">
                <svg class="jk-semak" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#FAF8F5" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
            </span>
            <span class="grid h-[84px] w-[84px] place-items-center rounded-2xl" style="background:color-mix(in srgb, var(--aksen) 10%, transparent); color:var(--aksen)">
                <svg width="48" height="48" viewBox="0 0 48 48" aria-hidden="true"><rect x="9" y="12" width="30" height="30" rx="5" fill="currentColor" opacity=".3"/><path d="M9 20h30" stroke="currentColor" stroke-width="2.6" opacity=".55"/><circle cx="24" cy="31" r="9" fill="currentColor"/><path d="m20 31 3 3 5-5.5" stroke="#FAF8F5" stroke-width="2.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="mt-6 block text-[17px] font-semibold tracking-tight text-navy">{{ __('Registered Counselor') }}</span>
            <span class="mt-2.5 block max-w-[26ch] text-[13.5px] leading-relaxed text-navy/55">{{ __('Accept bookings and manage your own schedule on the platform.') }}</span>
        </button>
    </div>

    <div class="mt-12 flex flex-col items-center gap-5">
        <button type="button" id="btnTeruskan" disabled
                class="btn-utama grid h-[46px] w-[200px] place-items-center rounded-full text-[14px] font-medium text-white"
                style="background:#4A7DFF">{{ __('Continue') }}</button>
        <p class="flex items-center gap-1.5 text-[13.5px] text-navy/55">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-medium text-teal">{{ __('Log in') }}</a>
        </p>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var kad = [].slice.call(document.querySelectorAll('.jk-pilih'));
                var btn = document.getElementById('btnTeruskan');
                var terpilih = null;

                kad.forEach(function (el) {
                    el.addEventListener('click', function () {
                        kad.forEach(function (o) { o.setAttribute('aria-pressed', o === el ? 'true' : 'false'); });
                        btn.disabled = false;
                        terpilih = el.getAttribute('data-href');
                    });
                });

                btn.addEventListener('click', function () {
                    if (terpilih) window.location.href = terpilih;
                });
            });
        </script>
    @endpush
</x-auth-centered-layout>
