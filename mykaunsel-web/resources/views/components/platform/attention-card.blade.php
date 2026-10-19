@props(['label', 'count', 'route', 'image', 'hint' => null, 'cta' => 'Review', 'color' => 'blue'])

{{-- Inspo-style card: text top-left, pill button, photo bleeding in from the right and fading into the card. --}}
@php
    $tint = [
        'blue' => ['bg' => 'from-white via-white to-brand-blue/15', 'num' => 'text-brand-blue', 'dot' => 'bg-brand-blue'],
        'yellow' => ['bg' => 'from-white via-white to-brand-yellow/25', 'num' => 'text-[#B98417]', 'dot' => 'bg-brand-yellow'],
        'red' => ['bg' => 'from-white via-white to-brand-red/15', 'num' => 'text-brand-red', 'dot' => 'bg-brand-red'],
        'ink' => ['bg' => 'from-white via-white to-brand-ink/15', 'num' => 'text-brand-ink', 'dot' => 'bg-brand-ink'],
    ][$color];
@endphp

<a href="{{ route($route) }}"
   class="group relative flex min-h-[260px] overflow-hidden rounded-[28px] bg-gradient-to-br {{ $tint['bg'] }} p-6 shadow-[0_2px_10px_-2px_rgba(14,42,51,.08)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_18px_36px_-16px_rgba(14,42,51,.3)]">

    <img src="{{ asset($image) }}" alt="" loading="lazy"
         class="pointer-events-none absolute inset-y-0 right-0 h-full w-[62%] object-cover opacity-90 transition duration-500 [mask-image:linear-gradient(to_right,transparent,#000_55%)] group-hover:scale-105">

    <div class="relative z-10 flex w-[62%] flex-col">
        <span class="flex items-center gap-2 text-[13px] font-semibold text-brand-ink/70">
            <span class="h-2 w-2 rounded-full {{ $tint['dot'] }}"></span>{{ $label }}
        </span>
        <span class="mt-2 text-[44px] font-extrabold leading-none tracking-tightest {{ $tint['num'] }}">{{ $count }}</span>
        @if ($hint)
            <p class="mt-3 text-[13px] leading-snug text-brand-ink/60">{{ $hint }}</p>
        @endif
        <span class="mt-auto inline-flex w-fit items-center gap-1 rounded-full border border-brand-ink/15 bg-white px-4 py-2 text-[13px] font-semibold shadow-sm transition group-hover:border-brand-ink group-hover:bg-brand-ink group-hover:text-white">
            {{ $cta }}
            <span class="max-w-0 overflow-hidden opacity-0 transition-all duration-200 group-hover:max-w-[16px] group-hover:opacity-100" aria-hidden="true">→</span>
        </span>
    </div>
</a>
