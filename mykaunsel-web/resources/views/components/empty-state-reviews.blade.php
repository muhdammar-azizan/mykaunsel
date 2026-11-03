@props(['title', 'text' => null])

{{-- Empty state for reviews: a review card with five empty stars, a speech bubble and one yellow star waiting to land. Same style as the other empty states. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-10 text-center']) }}>
    <svg width="220" height="170" viewBox="0 0 230 178" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="112" cy="169" rx="74" ry="5" fill="#1B2A4E" opacity=".08"/>

        {{-- the review card, a little tilted --}}
        <g transform="rotate(-3 112 100)">
            <rect x="42" y="44" width="136" height="112" rx="14" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
            {{-- who wrote it: an empty avatar and two lines --}}
            <circle cx="68" cy="70" r="11" fill="#EEF1F7" stroke="#1B2A4E" stroke-width="2.2"/>
            <path d="M63 76c1.500-4 8.500-4 10 0" stroke="#1B2A4E" stroke-width="1.8" opacity=".5"/>
            <circle cx="68" cy="67" r="3.200" fill="#1B2A4E" opacity=".45"/>
            <path d="M86 66h50" stroke="#1B2A4E" stroke-width="3.200" opacity=".28"/>
            <path d="M86 76h32" stroke="#1B2A4E" stroke-width="2.400" opacity=".18"/>
            {{-- five stars, none filled yet --}}
            <g stroke="#1B2A4E" stroke-width="2.200" fill="#F4F6FA">
                <path id="mk-rv-star" d="m60 98 3.300 6.700 7.400 1.100-5.400 5.200 1.300 7.400L60 115l-6.600 3.400 1.300-7.400-5.400-5.200 7.400-1.100z"/>
                <use href="#mk-rv-star" x="23"/>
                <use href="#mk-rv-star" x="46"/>
                <use href="#mk-rv-star" x="69"/>
                <use href="#mk-rv-star" x="92"/>
            </g>
            {{-- the comment, not written yet --}}
            <path d="M54 134h96M54 144h62" stroke="#1B2A4E" stroke-width="2" stroke-dasharray="3 6" opacity=".35"/>
        </g>

        {{-- a speech bubble with a heart --}}
        <path d="M150 8a8 8 0 0 1 8-8h40a8 8 0 0 1 8 8v22a8 8 0 0 1-8 8h-28l-10 9 1-9h-3a8 8 0 0 1-8-8z" transform="translate(0 6)" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M178 33c-5-3-8-5.500-8-8.500 0-2.200 1.700-3.800 3.800-3.800 1.700 0 3.300.9 4.200 2.400.9-1.500 2.500-2.400 4.200-2.400 2.100 0 3.800 1.600 3.800 3.800 0 3-3 5.500-8 8.500z" transform="translate(0 2)" fill="#fff"/>

        {{-- one yellow star, floating --}}
        <g transform="rotate(14 30 40)">
            <path d="m30 22 5.200 10.600 11.800 1.700-8.500 8.300 2 11.700L30 48.600l-10.500 5.700 2-11.700-8.500-8.300 11.800-1.700z" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.400"/>
        </g>
        <path d="M206 96v12M200 102h12" stroke="#E3AF39" stroke-width="3"/>
        <circle cx="16" cy="112" r="3" fill="#D62839" stroke="#1B2A4E" stroke-width="1.800"/>
    </svg>

    <p class="mt-3 text-[18px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[46ch] text-[14px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
