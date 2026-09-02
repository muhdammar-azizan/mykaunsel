@push('styles')
    <style>
        .cs-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .lkm-row { opacity: 0; transform: translateY(4px); animation: masuk .4s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda, 0s); }
        @media (prefers-reduced-motion: reduce) {
            .cs-lapis, .lkm-row { animation: none; opacity: 1; transform: none; }
        }
    </style>
@endpush

<x-auth-layout
    title="Verified — MyKaunsel"
    image-slot="lkm-visual"
    aside-title="Bring your practice online."
    aside-subtitle="Join as a verified counselor and accept bookings on your own schedule — no need to build your own system."
    content-max-width="420px"
>
    <div class="cs-lapis text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full" style="background:#0F6B7D;color:#FAF8F5">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
        </span>
        <h1 class="mt-6 text-[26px] font-semibold leading-[1.15] tracking-tightest">{{ __('Match found') }}</h1>
        <p class="mx-auto mt-2.5 max-w-[36ch] text-[14px] leading-relaxed text-navy/55">{{ __('We found your registration in the LKM directory.') }}</p>

        <div class="mt-7 rounded-[14px] p-5 text-left" style="background:rgba(14,42,51,.045);border-left:3px solid #0F6B7D">
            <div class="lkm-row flex items-center justify-between gap-3" style="--tunda:0s"><span class="whitespace-nowrap text-[12.5px] text-navy/50">{{ __('Name') }}</span><span class="whitespace-nowrap text-[13.5px] font-semibold text-navy">{{ $lkmRecord->full_name }}</span></div>
            <div class="lkm-row mt-2.5 flex items-center justify-between gap-3" style="--tunda:.08s"><span class="whitespace-nowrap text-[12.5px] text-navy/50">{{ __('KB Number') }}</span><span class="whitespace-nowrap text-[13.5px] font-semibold text-navy">{{ $lkmRecord->kb_number }}</span></div>
            <div class="lkm-row mt-2.5 flex items-center justify-between gap-3" style="--tunda:.16s"><span class="whitespace-nowrap text-[12.5px] text-navy/50">{{ __('PA Number') }}</span><span class="whitespace-nowrap text-[13.5px] font-semibold text-navy">{{ $lkmRecord->pa_number }}</span></div>
            <div class="lkm-row mt-2.5 flex items-center justify-between gap-3" style="--tunda:.24s"><span class="whitespace-nowrap text-[12.5px] text-navy/50">{{ __('Registration status') }}</span><span class="whitespace-nowrap text-[13.5px] font-semibold text-teal">{{ $lkmRecord->status }}</span></div>
            <div class="lkm-row mt-2.5 flex items-center justify-between gap-3" style="--tunda:.32s"><span class="whitespace-nowrap text-[12.5px] text-navy/50">{{ __('PA valid until') }}</span><span class="whitespace-nowrap text-[13.5px] font-semibold text-navy">{{ $lkmRecord->pa_valid_until->format('d F Y') }}</span></div>
        </div>
        <p class="mt-3 text-[11.5px] leading-relaxed text-navy/45">{{ __('This information is sourced from the LKM directory and will be used on your public profile.') }}</p>

        <a href="{{ route('counselors.signup.documents') }}" class="btn-utama mt-6 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Continue to document upload') }}</a>
    </div>
</x-auth-layout>
