@props(['size' => 32, 'href' => '/', 'variant' => 'light'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex shrink-0 items-center gap-2.5']) }}>
    @if ($variant === 'dark')
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" class="shrink-0" aria-hidden="true">
            <path d="M5.5 16a10 10 0 0 1 10-10h17a10 10 0 0 1 10 10v10a10 10 0 0 1-10 10H16.5l-8 7v-7.7" fill="none" stroke="#FAF8F5" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15 20a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v1a5 5 0 0 1-5 5h-8a5 5 0 0 1-5-5z" fill="#D98F4A"/>
        </svg>
        <span class="text-[19px] font-semibold tracking-tightest text-cream">MyKaunsel</span>
    @else
        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" class="shrink-0" aria-hidden="true">
            <path d="M5.5 16a10 10 0 0 1 10-10h17a10 10 0 0 1 10 10v10a10 10 0 0 1-10 10H16.5l-8 7v-7.7" fill="none" stroke="#0F6B7D" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15 20a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v1a5 5 0 0 1-5 5h-8a5 5 0 0 1-5-5z" fill="none" stroke="#FAF8F5" stroke-width="5" stroke-linejoin="round"/>
            <path d="M15 20a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v1a5 5 0 0 1-5 5h-8a5 5 0 0 1-5-5z" fill="#D98F4A"/>
        </svg>
        <span class="text-[19px] font-semibold tracking-tightest text-navy"><span class="text-teal">My</span>Kaunsel</span>
    @endif
</a>
