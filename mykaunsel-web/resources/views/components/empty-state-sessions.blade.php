@props(['kind' => 'upcoming', 'title'])

{{-- A small empty state for a list of sessions. $kind picks the picture: 'mark' (nothing to mark: a clipboard with a tick), 'upcoming' (nothing coming: a desk clock),
     'past' (nothing yet: an empty archive box). Same style as the other empty states, only smaller. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-xl border border-[#E4E2DE] bg-white px-4 py-6 text-center']) }}>
    <svg width="112" height="84" viewBox="0 0 150 112" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="75" cy="104" rx="46" ry="4" fill="#1B2A4E" opacity=".08"/>

        @if ($kind === 'mark')
            {{-- a clipboard with a big tick: all marked --}}
            <rect x="42" y="14" width="64" height="86" rx="9" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
            <rect x="60" y="8" width="28" height="14" rx="5" fill="#E3AF39" stroke="#1B2A4E" stroke-width="2.5"/>
            <path d="M54 40h16M54 54h16M54 68h16" stroke="#1B2A4E" stroke-width="2.200" opacity=".25"/>
            <path d="M78 40h16M78 54h16M78 68h10" stroke="#1B2A4E" stroke-width="2.200" opacity=".25"/>
            <circle cx="108" cy="78" r="17" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.500"/>
            <path d="m100 78 6 6 11-12" stroke="#fff" stroke-width="3.400"/>
            <path d="M24 30v10M19 35h10" stroke="#E3AF39" stroke-width="3"/>
        @elseif ($kind === 'past')
            {{-- an empty archive box: nothing in it yet --}}
            <path d="M36 44h78v50a8 8 0 0 1-8 8H44a8 8 0 0 1-8-8z" fill="#F1F3F8" stroke="#1B2A4E" stroke-width="2.5"/>
            <rect x="30" y="28" width="90" height="18" rx="6" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.5"/>
            <rect x="60" y="56" width="30" height="10" rx="5" fill="#fff" stroke="#1B2A4E" stroke-width="2.200"/>
            <path d="M52 80h46" stroke="#1B2A4E" stroke-width="2.200" stroke-dasharray="3 6" opacity=".35"/>
            <path d="M112 14v10M107 19h10" stroke="#E3AF39" stroke-width="3"/>
            <circle cx="30" cy="70" r="3" fill="#D62839" stroke="#1B2A4E" stroke-width="1.800"/>
        @else
            {{-- a desk clock: nothing booked yet --}}
            <path d="M44 100l-6 6M106 100l6 6" stroke="#1B2A4E" stroke-width="2.500"/>
            <circle cx="75" cy="58" r="38" fill="#fff" stroke="#1B2A4E" stroke-width="2.500"/>
            <circle cx="75" cy="58" r="30" stroke="#1B2A4E" stroke-width="1.600" opacity=".22"/>
            <path d="M75 58V38M75 58l14 8" stroke="#1B2A4E" stroke-width="3"/>
            <circle cx="75" cy="58" r="3.400" fill="#D62839"/>
            <path d="M46 24 36 14M104 24l10-10" stroke="#1B2A4E" stroke-width="2.500"/>
            <path d="M28 20a12 12 0 0 1 14-9M122 20a12 12 0 0 0-14-9" stroke="#E3AF39" stroke-width="5"/>
            <path d="M124 70v10M119 75h10" stroke="#E3AF39" stroke-width="3"/>
        @endif
    </svg>

    <p class="mt-2 text-[14.5px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    {{ $slot }}
</div>
