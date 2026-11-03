@props(['title', 'text' => null])

{{-- Empty state for session notes: an open notebook with blank lines, a pencil, and a padlock (the notes are private and encrypted). Same style as the other empty states. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-10 text-center']) }}>
    <svg width="220" height="170" viewBox="0 0 230 178" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="112" cy="168" rx="82" ry="5" fill="#1B2A4E" opacity=".08"/>

        {{-- the open notebook --}}
        <path d="M30 50c26-8 56-6 82 8v96c-26-14-56-16-82-8z" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M194 50c-26-8-56-6-82 8v96c26-14 56-16 82-8z" fill="#F4F6FA" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M112 58v96" stroke="#1B2A4E" stroke-width="2.5"/>
        {{-- the pages are still blank: only faint lines --}}
        <path d="M44 74c18-3 38-2 56 6M44 90c18-3 38-2 56 6M44 106c18-3 38-2 56 6M44 122c18-3 38-2 56 6" stroke="#1B2A4E" stroke-width="2" opacity=".22"/>
        <path d="M180 74c-18-3-38-2-56 6M180 90c-18-3-38-2-56 6M180 106c-18-3-38-2-56 6" stroke="#1B2A4E" stroke-width="2" opacity=".22"/>
        {{-- a yellow bookmark ribbon --}}
        <path d="M150 62v30l7-5 7 5V68" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.2"/>

        {{-- the pencil, resting on the right page --}}
        <g transform="rotate(32 168 120)">
            <rect x="150" y="112" width="52" height="11" rx="2" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.4"/>
            <path d="M150 112 136 117.500 150 123z" fill="#F2CDA9" stroke="#1B2A4E" stroke-width="2.2"/>
            <path d="m136 117.500 5-1.900v3.800z" fill="#1B2A4E"/>
            <rect x="202" y="112" width="9" height="11" rx="2" fill="#D62839" stroke="#1B2A4E" stroke-width="2.4"/>
            <path d="M190 112v11" stroke="#1B2A4E" stroke-width="2" opacity=".5"/>
        </g>

        {{-- the padlock: private --}}
        <g transform="translate(-4 -2)">
            {{-- the shackle is centred on the body and closed --}}
            <path d="M54 36v-7a11 11 0 0 1 22 0v7" stroke="#1B2A4E" stroke-width="3.2"/>
            <rect x="46" y="34" width="38" height="30" rx="8" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
            <path d="M52 42h8" stroke="#fff" stroke-width="2" opacity=".35"/>
            <circle cx="65" cy="46.500" r="3.800" fill="#fff"/>
            <path d="M65 49v8" stroke="#fff" stroke-width="3"/>
        </g>

        {{-- a little sparkle --}}
        <path d="M200 30v14M193 37h14" stroke="#E3AF39" stroke-width="3"/>
        <circle cx="26" cy="104" r="3" fill="#D62839" stroke="#1B2A4E" stroke-width="1.8"/>
    </svg>

    <p class="mt-3 text-[18px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-[46ch] text-[14px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
