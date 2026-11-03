@props(['title', 'text' => null])

{{-- Empty state for "no sessions today": a calendar page with free time slots, a warm cup of coffee and a little sun. Same style as the other empty states. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-8 text-center']) }}>
    <svg width="210" height="150" viewBox="0 0 230 165" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="112" cy="156" rx="86" ry="5" fill="#1B2A4E" opacity=".08"/>

        {{-- a little sun --}}
        <circle cx="186" cy="36" r="15" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M186 10v5M186 57v5M160 36h5M207 36h5M168 18l3.500 3.500M200.500 50.500 204 54M204 18l-3.500 3.500M171.500 50.500 168 54" stroke="#E3AF39" stroke-width="3"/>

        {{-- the calendar --}}
        <rect x="34" y="28" width="116" height="118" rx="12" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M34 54h116V40a12 12 0 0 0-12-12H46a12 12 0 0 0-12 12z" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M62 18v18M122 18v18" stroke="#1B2A4E" stroke-width="4.500"/>
        <path d="M62 18v18M122 18v18" stroke="#A9C4FF" stroke-width="1.600"/>
        {{-- today, circled; the rest of the page is free --}}
        <circle cx="92" cy="94" r="17" fill="#FFF4D6" stroke="#1B2A4E" stroke-width="2.4"/>
        <path d="m84 94 6 6 11-12" stroke="#1B2A4E" stroke-width="3.200"/>
        <g fill="#1B2A4E" opacity=".22">
            <circle cx="52" cy="72" r="3.400"/><circle cx="72" cy="72" r="3.400"/><circle cx="92" cy="72" r="3.400"/><circle cx="112" cy="72" r="3.400"/><circle cx="132" cy="72" r="3.400"/>
            <circle cx="52" cy="94" r="3.400"/><circle cx="72" cy="94" r="3.400"/><circle cx="112" cy="94" r="3.400"/><circle cx="132" cy="94" r="3.400"/>
            <circle cx="52" cy="116" r="3.400"/><circle cx="72" cy="116" r="3.400"/><circle cx="92" cy="116" r="3.400"/><circle cx="112" cy="116" r="3.400"/><circle cx="132" cy="116" r="3.400"/>
        </g>

        {{-- a cup of coffee --}}
        <path d="M146 112h46v18a18 18 0 0 1-18 18h-10a18 18 0 0 1-18-18z" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M192 118h6a9 9 0 0 1 0 18h-8" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M150 122h38" stroke="#D62839" stroke-width="3.500"/>
        <path d="M160 100c-4-5 4-8 0-13M174 100c-4-5 4-8 0-13" stroke="#1B2A4E" stroke-width="2.200" opacity=".4"/>
        <ellipse cx="169" cy="150" rx="26" ry="3" fill="#EEF1F7" stroke="#1B2A4E" stroke-width="2"/>
    </svg>

    <p class="mt-2 text-[17px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[44ch] text-[13.5px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
