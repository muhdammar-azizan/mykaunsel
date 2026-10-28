@props(['points'])

{{-- Cumulative growth as a server-rendered SVG line chart with two series. The series differ by line style AND marker shape AND the
     legend/table, never by colour alone: organizations = solid line + circles, counselors = dashed line + squares. --}}
@php
    $w = 640; $h = 280;
    $left = 44; $right = 16; $top = 16; $bottom = 40;
    $plotW = $w - $left - $right;
    $plotH = $h - $top - $bottom;
    $n = count($points);

    $peak = max(1, collect($points)->flatMap(fn ($p) => [$p['organizations'], $p['counselors']])->max());
    // A round step (1, 2, 5, 10, 20, 50 …) that gives about four gridlines.
    $target = $peak / 4;
    $magnitude = 10 ** floor(log10(max(1, $target)));
    $norm = $target / $magnitude;
    $step = max(1, (int) (($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10))) * $magnitude));
    $top_value = (int) (ceil($peak / $step) * $step);
    $ticks = range(0, $top_value, $step);

    $x = fn (int $i) => $left + ($n <= 1 ? $plotW / 2 : $i / ($n - 1) * $plotW);
    $y = fn (int $v) => $top + $plotH - ($top_value === 0 ? 0 : $v / $top_value * $plotH);
    $every = max(1, (int) ceil($n / 8));

    $series = [
        ['key' => 'organizations', 'label' => 'Organizations', 'color' => '#0A4F88', 'dash' => '', 'shape' => 'circle'],
        ['key' => 'counselors', 'label' => 'Counselors', 'color' => '#B3202F', 'dash' => '7 5', 'shape' => 'square'],
    ];
@endphp

<ul class="mb-2 flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-brand-ink/80" aria-label="Legend">
    @foreach ($series as $s)
        <li class="flex items-center gap-2">
            <svg width="30" height="12" viewBox="0 0 30 12" aria-hidden="true">
                <line x1="0" y1="6" x2="30" y2="6" stroke="{{ $s['color'] }}" stroke-width="2" @if ($s['dash']) stroke-dasharray="{{ $s['dash'] }}" @endif/>
                @if ($s['shape'] === 'circle')
                    <circle cx="15" cy="6" r="4.5" fill="{{ $s['color'] }}" stroke="#FFFFFF" stroke-width="2"/>
                @else
                    <rect x="10.5" y="1.5" width="9" height="9" fill="{{ $s['color'] }}" stroke="#FFFFFF" stroke-width="2"/>
                @endif
            </svg>
            {{ $s['label'] }}
        </li>
    @endforeach
</ul>

<svg viewBox="0 0 {{ $w }} {{ $h }}" class="block h-auto w-full" role="img" aria-label="Line chart of approved organizations and counselors over time. The same numbers are in the table view.">
    @foreach ($ticks as $tick)
        <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="#0E2A33" stroke-opacity="{{ $tick === 0 ? .35 : .1 }}" stroke-width="1"/>
        <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="#0E2A33" fill-opacity=".7">{{ number_format($tick) }}</text>
    @endforeach

    @foreach ($points as $i => $p)
        @if ($i % $every === 0)
            <text x="{{ $x($i) }}" y="{{ $h - 14 }}" text-anchor="middle" font-size="11" fill="#0E2A33" fill-opacity=".7">{{ \Illuminate\Support\Str::after($p['label'], 'Week of ') }}</text>
        @endif
    @endforeach

    @foreach ($series as $s)
        @if ($n > 1)
            <polyline fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" @if ($s['dash']) stroke-dasharray="{{ $s['dash'] }}" @endif
                      points="{{ collect($points)->map(fn ($p, $i) => round($x($i), 1).','.round($y($p[$s['key']]), 1))->implode(' ') }}"/>
        @endif
        {{-- Markers only when there are few points, otherwise they crowd the line. --}}
        @if ($n <= 16)
            @foreach ($points as $i => $p)
                @if ($s['shape'] === 'circle')
                    <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($p[$s['key']]), 1) }}" r="4.5" fill="{{ $s['color'] }}" stroke="#FFFFFF" stroke-width="2"><title>{{ $p['label'] }}: {{ number_format($p[$s['key']]) }} {{ strtolower($s['label']) }}</title></circle>
                @else
                    <rect x="{{ round($x($i) - 4.5, 1) }}" y="{{ round($y($p[$s['key']]) - 4.5, 1) }}" width="9" height="9" fill="{{ $s['color'] }}" stroke="#FFFFFF" stroke-width="2"><title>{{ $p['label'] }}: {{ number_format($p[$s['key']]) }} {{ strtolower($s['label']) }}</title></rect>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- The latest value of each line, written next to its end. --}}
    @foreach ($series as $s)
        @php $last = end($points); @endphp
        <text x="{{ min($w - 2, $x($n - 1) + 8) }}" y="{{ $y($last[$s['key']]) + ($s['key'] === 'counselors' && $last['counselors'] === $last['organizations'] ? 16 : -8) }}" text-anchor="{{ $n > 1 ? 'end' : 'start' }}" font-size="12" font-weight="700" fill="#0E2A33">{{ number_format($last[$s['key']]) }}</text>
    @endforeach
</svg>
