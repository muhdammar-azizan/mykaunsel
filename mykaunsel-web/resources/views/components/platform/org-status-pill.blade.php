@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $classes = [
        'pending' => 'bg-brand-yellow/20 text-[#8A6210]',
        'active' => 'bg-emerald-50 text-emerald-700',
        'suspended' => 'bg-brand-red/10 text-brand-red',
        'rejected' => 'bg-brand-ink/10 text-brand-ink/75',
    ][$value] ?? 'bg-brand-ink/10 text-brand-ink';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold capitalize', $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $value }}
</span>
