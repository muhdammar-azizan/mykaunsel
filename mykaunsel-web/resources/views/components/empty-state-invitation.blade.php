@props(['title', 'text' => null])

{{-- A small empty state for "no invitations yet": an open envelope with a letter and a paper plane about to leave. Same style as the other empty states, only smaller
     (it sits inside a card that has other things in it). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 py-5 text-center']) }}>
    <svg width="128" height="94" viewBox="0 0 150 110" fill="none" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="68" cy="104" rx="46" ry="4" fill="#1B2A4E" opacity=".08"/>

        {{-- open flap, then the letter coming out, then the front of the envelope --}}
        <path d="M24 56 68 24l44 32z" fill="#DDE5F3" stroke="#1B2A4E" stroke-width="2.5"/>
        <rect x="38" y="30" width="60" height="50" rx="4" fill="#fff" stroke="#1B2A4E" stroke-width="2.5"/>
        <rect x="46" y="38" width="22" height="7" rx="3.5" fill="#0A4F88"/>
        <path d="M46 54h44M46 62h44M46 70h28" stroke="#1B2A4E" stroke-width="2" opacity=".3"/>
        <path d="M24 56 68 86l44-30v40a8 8 0 0 1-8 8H32a8 8 0 0 1-8-8z" fill="#F1F3F8" stroke="#1B2A4E" stroke-width="2.5"/>
        <path d="M24 96 56 74M112 96 80 74" stroke="#1B2A4E" stroke-width="2" opacity=".25"/>
        <circle cx="68" cy="70" r="0" fill="#D62839"/>

        {{-- a little heart seal --}}
        <path d="M68 99c-6-4-10-7-10-11 0-3 2-5 5-5 2 0 4 1 5 3 1-2 3-3 5-3 3 0 5 2 5 5 0 4-4 7-10 11z" transform="translate(0 -4)" fill="#D62839" stroke="#1B2A4E" stroke-width="2"/>

        {{-- the paper plane and its trail --}}
        <path d="M108 38c8-4 14-12 14-20" stroke="#1B2A4E" stroke-width="2" stroke-dasharray="2 5" opacity=".5"/>
        <path d="m118 18 26-12-8 28-9-9-9 4z" fill="#0A4F88" stroke="#1B2A4E" stroke-width="2.4"/>
        <path d="m127 25 17-19" stroke="#fff" stroke-width="1.6" opacity=".8"/>
        <circle cx="138" cy="48" r="3" fill="#E3AF39" stroke="#1B2A4E" stroke-width="1.8"/>
    </svg>

    <p class="mt-2 text-[14.5px] font-bold tracking-tight text-brand-ink">{{ $title }}</p>
    @if ($text)
        <p class="mt-0.5 max-w-[40ch] text-[13px] leading-relaxed text-brand-ink/60">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
