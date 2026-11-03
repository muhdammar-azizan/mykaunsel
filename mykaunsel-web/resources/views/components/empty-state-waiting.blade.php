@props(['title', 'text' => null])

{{-- Empty state for "nobody is waiting": a person with a clipboard beside an empty bench and a wall clock, and a bubble that says all is clear. Same style as
     empty-state-mailbox (navy outline, blue, yellow, red). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-8 text-center']) }}>
    <svg width="230" height="190" viewBox="0 0 230 190" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="124" cy="181" rx="84" ry="5" fill="#1B2A4E" opacity=".08"/>

        {{-- wall clock --}}
        <path d="M168 34v-6" stroke="#1B2A4E" stroke-width="2.5"/>
        <circle cx="168" cy="58" r="22" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <circle cx="168" cy="58" r="16.5" stroke="#1B2A4E" stroke-width="1.6" opacity=".25"/>
        <path d="M168 58V46M168 58l8 5" stroke="#1B2A4E" stroke-width="2.6"/>
        <circle cx="168" cy="58" r="2.4" fill="#D62839"/>

        {{-- the empty bench --}}
        <rect x="104" y="108" width="112" height="22" rx="7" fill="#EEF1F7" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M160 108v22" stroke="#1B2A4E" stroke-width="2" opacity=".3"/>
        <rect x="100" y="130" width="120" height="13" rx="6.5" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M112 143v33M208 143v33" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M106 176h14M200 176h14" stroke="#1B2A4E" stroke-width="2.5"/>

        {{-- the person --}}
        <path d="M36 176v-34M60 176v-34" stroke="#1B2A4E" stroke-width="9"/>
        <path d="M30 177h13M54 177h13" stroke="#1B2A4E" stroke-width="5"/>
        <rect x="27" y="84" width="42" height="64" rx="16" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M48 88v24" stroke="#fff" stroke-width="2" opacity=".35"/>
        <circle cx="48" cy="64" r="15" fill="#F2CDA9" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M33 62c0-12 8-18 17-18 9 0 14 6 14 14-6-1-11-5-13-9-3 6-10 10-18 13z" fill="#1B2A4E"/>
        <circle cx="43" cy="66" r="1.6" fill="#1B2A4E"/>
        <circle cx="54" cy="66" r="1.6" fill="#1B2A4E"/>
        <path d="M44 72c2.5 2.5 6 2.5 8.5 0" stroke="#1B2A4E" stroke-width="2"/>
        {{-- arm and clipboard --}}
        <path d="M66 98c8 4 11 11 11 18" stroke="#0A4F88" stroke-width="9"/>
        <path d="M66 98c8 4 11 11 11 18" stroke="#1B2A4E" stroke-width="2.5" opacity=".0"/>
        <rect x="70" y="104" width="25" height="33" rx="4" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <rect x="76" y="100" width="13" height="8" rx="3" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2"/>
        <path d="M76 118h13M76 125h13M76 132h8" stroke="#1B2A4E" stroke-width="2" opacity=".4"/>
        <circle cx="76" cy="124" r="5" fill="#F2CDA9" stroke="#1B2A4E" stroke-width="2"/>

        {{-- speech bubble: all clear --}}
        <path d="M30 14a8 8 0 0 1 8-8h50a8 8 0 0 1 8 8v22a8 8 0 0 1-8 8H66l-10 9 1-9h-19a8 8 0 0 1-8-8z" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M51 25l7 7 14-15" stroke="#fff" stroke-width="3.2"/>
    </svg>

    <p class="mt-3 text-[17px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[44ch] text-[13.5px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
