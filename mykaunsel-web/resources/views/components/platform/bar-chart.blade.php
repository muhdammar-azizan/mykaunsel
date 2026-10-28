@props(['items', 'name', 'color' => '#0A4F88'])

{{-- Horizontal bars as server-rendered SVG. $items: label, count (null when hidden), display, suppressed, other (optional).
     A hidden count ("<5") has NO number to draw, so it gets a short hatched stub: still readable without colour, and it never shows size.
     The value is always written at the end of the bar, so identity is never colour alone. --}}
@php
    $max = max(1, (int) collect($items)->pluck('count')->filter(fn ($c) => $c !== null)->max());
    $rowH = 44;
    $width = 640;
    $labelMax = 52;
    $barX = 0;
    $barW = 520;
    $height = max(1, count($items)) * $rowH;
    $pattern = $name.'-hatch';
@endphp

<svg viewBox="0 0 {{ $width }} {{ $height }}" class="block h-auto w-full" role="img" aria-label="{{ $name }} bar chart. The same numbers are in the table view." preserveAspectRatio="xMinYMin meet">
    <defs>
        <pattern id="{{ $pattern }}" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <rect width="6" height="6" fill="#FFFFFF"/>
            <rect width="3" height="6" fill="{{ $color }}"/>
        </pattern>
    </defs>
    @foreach ($items as $i => $item)
        @php
            $y = $i * $rowH;
            $label = \Illuminate\Support\Str::limit($item['label'], $labelMax, '…');
            $w = $item['count'] === null ? 28 : ($item['count'] === 0 ? 0 : max(4, $item['count'] / $max * $barW));
            $other = ! empty($item['other']);
        @endphp
        <g>
            <title>{{ $item['label'] }}: {{ $item['display'] }}</title>
            <text x="0" y="{{ $y + 14 }}" font-size="13" fill="#0E2A33" font-weight="{{ $other ? 400 : 600 }}" font-style="{{ $other ? 'italic' : 'normal' }}">{{ $label }}</text>
            <rect x="{{ $barX }}" y="{{ $y + 21 }}" width="{{ $barW }}" height="14" rx="4" fill="#0E2A33" fill-opacity=".06"/>
            @if ($w > 0)
                <rect x="{{ $barX }}" y="{{ $y + 21 }}" width="{{ $w }}" height="14" rx="4" fill="{{ $item['suppressed'] ? 'url(#'.$pattern.')' : $color }}" @if ($item['suppressed']) stroke="{{ $color }}" stroke-width="1" @endif/>
            @endif
            <text x="{{ $width }}" y="{{ $y + 33 }}" text-anchor="end" font-size="13" font-weight="700" fill="#0E2A33">{{ $item['display'] }}</text>
        </g>
    @endforeach
</svg>
