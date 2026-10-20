@props(['type'])

@php
    $value = $type instanceof \BackedEnum ? $type->value : (string) $type;
    $classes = [
        'university' => 'bg-brand-blue/10 text-brand-blue',
        'corporate' => 'bg-brand-yellow/20 text-[#8A6210]',
        'clinic' => 'bg-brand-red/10 text-brand-red',
    ][$value] ?? 'bg-brand-ink/10 text-brand-ink';
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold capitalize', $classes]) }}>{{ $value }}</span>
