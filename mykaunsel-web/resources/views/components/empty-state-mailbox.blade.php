@props(['title', 'text' => null])

{{-- A quiet empty state: a mailbox with a small bird and a speech bubble. Drawn here in the brand colours (navy outline, blue bubble, red flag, yellow beak). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-12 text-center']) }}>
    <svg width="230" height="190" viewBox="0 0 230 190" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <defs>
            <linearGradient id="mbShade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#F4F6F9"/>
                <stop offset="1" stop-color="#D9DEE7"/>
            </linearGradient>
        </defs>

        {{-- ground shadow and post --}}
        <ellipse cx="116" cy="181" rx="34" ry="4.5" fill="#1B2A4E" opacity=".08"/>
        <path d="M108 108v72M124 108v72" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M109 108h14v72h-14z" fill="#D9DEE7"/>

        {{-- mailbox body --}}
        <path d="M30 96c0-20 14-34 34-34h92c22 0 40 12 40 34v18a4 4 0 0 1-4 4H34a4 4 0 0 1-4-4z" fill="url(#mbShade)" stroke="#1B2A4E" stroke-width="2.5"/>
        {{-- door --}}
        <path d="M34 114V96c0-18 11-30 30-32 18 2 28 14 28 32v18" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M44 108V96c0-12 7-21 20-23" stroke="#1B2A4E" stroke-width="1.8" opacity=".35"/>
        <path d="M60 74a4 4 0 0 1 6 0" stroke="#1B2A4E" stroke-width="2"/>
        <circle cx="96" cy="118" r="2" fill="#1B2A4E"/>
        {{-- flag --}}
        <circle cx="150" cy="90" r="7" fill="#fff" stroke="#1B2A4E" stroke-width="2.2"/>
        <path d="M150 90h26" stroke="#D62839" stroke-width="5"/>
        <path d="M172 82h14v16l-7-5-7 5z" fill="#D62839" stroke="#1B2A4E" stroke-width="2"/>

        {{-- bird --}}
        <path d="M82 62c0-12 9-20 21-20 10 0 17 6 17 16 0 9-6 16-14 17H92c-6-1-10-6-10-13z" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M84 58c-8 1-14 6-17 14 6 4 14 5 22 1" fill="#EEF1F6" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M118 49l9 2-8 5z" fill="#E3AF39" stroke="#1B2A4E" stroke-width="1.8"/>
        <circle cx="111" cy="50" r="1.8" fill="#1B2A4E"/>
        <path d="M96 78v6M104 78v6" stroke="#1B2A4E" stroke-width="2"/>

        {{-- speech bubble --}}
        <path d="M130 18a8 8 0 0 1 8-8h46a8 8 0 0 1 8 8v20a8 8 0 0 1-8 8h-36l-12 10 2-12a8 8 0 0 1-8-6z" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <circle cx="147" cy="28" r="2.6" fill="#fff"/>
        <circle cx="161" cy="28" r="2.6" fill="#fff"/>
        <circle cx="175" cy="28" r="2.6" fill="#fff"/>
    </svg>

    <p class="mt-4 text-[18px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[42ch] text-[14px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
