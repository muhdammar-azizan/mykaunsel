@props(['segments', 'defaultIndex' => 0])

{{-- Interactive donut: draws in on load, hover a slice or a legend row to highlight it and show its number in the centre. --}}
@php
    $total = max(1, collect($segments)->sum('value'));
    // Give tiny values a minimum visible share so they never vanish from the ring.
    $shares = collect($segments)->map(fn ($s) => max($s['value'] / $total, 0.045));
    $norm = $shares->sum();
    $data = collect($segments)->values()->map(fn ($s, $i) => $s + ['frac' => $shares[$i] / $norm])->all();
@endphp

<div x-data="{
        r: 48,
        segs: @js($data),
        active: null,
        ready: false,
        def: {{ (int) $defaultIndex }},
        get c() { return 2 * Math.PI * this.r; },
        get cur() { return this.segs[this.active ?? this.def]; },
        start(i) { return this.segs.slice(0, i).reduce((a, s) => a + s.frac, 0); },
    }"
     x-init="setTimeout(() => ready = true, 150)">

    <div class="relative mx-auto h-[200px] w-[200px]">
        <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90" role="img" aria-label="Platform totals">
            <circle cx="60" cy="60" :r="r" fill="none" stroke="currentColor" class="text-brand-ink/[.06]" stroke-width="13"/>
            <template x-for="(s, i) in segs" :key="s.label">
                <circle cx="60" cy="60" :r="r" fill="none" stroke-linecap="round"
                        :stroke="s.color"
                        :stroke-width="active === i ? 17 : 13"
                        :stroke-dasharray="ready ? Math.max(s.frac * c - 5, 1) + ' ' + c : '0 ' + c"
                        :stroke-dashoffset="-(start(i) * c + 2.5)"
                        :opacity="active === null || active === i ? 1 : .35"
                        class="cursor-pointer transition-all duration-700 ease-out hover:duration-200"
                        @mouseenter="active = i" @mouseleave="active = null"/>
            </template>
        </svg>

        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-9 text-center">
            <p class="text-[11.5px] leading-tight text-brand-ink/55" x-text="cur.label"></p>
            <p class="mt-1 text-[30px] font-extrabold leading-none tracking-tightest" x-text="cur.value.toLocaleString()"></p>
        </div>
    </div>

    <ul class="mt-6 flex flex-col gap-1">
        <template x-for="(s, i) in segs" :key="s.label">
            <li @mouseenter="active = i" @mouseleave="active = null"
                :class="active === i ? 'bg-brand-ink/5' : ''"
                class="flex cursor-default items-center justify-between rounded-lg px-2.5 py-2 text-[13px] transition-colors">
                <span class="flex items-center gap-2.5 text-brand-ink/65">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + s.color"></span>
                    <span x-text="s.label"></span>
                </span>
                <span class="font-bold" x-text="s.value.toLocaleString()"></span>
            </li>
        </template>
    </ul>
</div>
