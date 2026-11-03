@props(['title', 'text' => null])

{{-- Empty state for invoices, in the same style as empty-state-mailbox: a blank invoice with empty rows, a small bird on top and a bubble that says RM, and a few coins. Brand colours. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-10 text-center']) }}>
    <svg width="230" height="190" viewBox="0 0 230 190" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <defs>
            <linearGradient id="invShade" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#FFFFFF"/>
                <stop offset="1" stop-color="#EEF1F7"/>
            </linearGradient>
        </defs>

        <ellipse cx="116" cy="181" rx="62" ry="5" fill="#1B2A4E" opacity=".08"/>

        {{-- the invoice, slightly tilted --}}
        <g transform="rotate(-4 112 108)">
            <path d="M62 44a8 8 0 0 1 8-8h64l28 28v94a8 8 0 0 1-8 8H70a8 8 0 0 1-8-8z" fill="url(#invShade)" stroke="#1B2A4E" stroke-width="2.5"/>
            <path d="M134 36v20a8 8 0 0 0 8 8h20" fill="#DDE5F3" stroke="#1B2A4E" stroke-width="2.5"/>
            {{-- header --}}
            <rect x="74" y="50" width="42" height="9" rx="4.5" fill="#0A4F88"/>
            <path d="M74 66h26" stroke="#1B2A4E" stroke-width="2" opacity=".3"/>
            {{-- empty rows --}}
            <path d="M74 88h76M74 104h76M74 120h76" stroke="#1B2A4E" stroke-width="2" stroke-dasharray="3 6" opacity=".35"/>
            {{-- total --}}
            <path d="M74 138h76" stroke="#1B2A4E" stroke-width="2.5"/>
            <rect x="112" y="147" width="38" height="12" rx="6" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2"/>
            <path d="M74 153h24" stroke="#1B2A4E" stroke-width="2.5" opacity=".5"/>
        </g>

        {{-- coins --}}
        <circle cx="180" cy="160" r="15" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.5"/>
        <circle cx="180" cy="160" r="8.5" stroke="#1B2A4E" stroke-width="1.8" opacity=".45"/>
        <circle cx="198" cy="168" r="11" fill="#F1C96A" stroke="#1B2A4E" stroke-width="2.5"/>
        <circle cx="198" cy="168" r="5.5" stroke="#1B2A4E" stroke-width="1.6" opacity=".45"/>

        {{-- bird, on the top edge of the invoice --}}
        <g transform="translate(-4 -34)">
            <path d="M82 62c0-12 9-20 21-20 10 0 17 6 17 16 0 9-6 16-14 17H92c-6-1-10-6-10-13z" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
            <path d="M84 58c-8 1-14 6-17 14 6 4 14 5 22 1" fill="#EEF1F6" stroke="#1B2A4E" stroke-width="2.5"/>
            <path d="M118 49l9 2-8 5z" fill="#E3AF39" stroke="#1B2A4E" stroke-width="1.8"/>
            <circle cx="111" cy="50" r="1.8" fill="#1B2A4E"/>
            <path d="M96 78v6M104 78v6" stroke="#1B2A4E" stroke-width="2"/>
        </g>

        {{-- speech bubble: RM --}}
        <path d="M138 6a8 8 0 0 1 8-8h40a8 8 0 0 1 8 8v20a8 8 0 0 1-8 8h-40a8 8 0 0 1-8-8z" transform="translate(0 6)" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M138 24l-12 8 12 4" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
        <text x="166" y="28.5" text-anchor="middle" font-family="Plus Jakarta Sans, Arial, sans-serif" font-size="14" font-weight="800" fill="#fff" stroke="none">RM</text>
    </svg>

    <p class="mt-4 text-[18px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[42ch] text-[14px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
