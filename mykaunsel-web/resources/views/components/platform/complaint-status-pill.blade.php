@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $classes = [
        'open' => 'bg-brand-red/10 text-brand-red',
        'under_review' => 'bg-brand-yellow/25 text-[#7A5410]',
        'resolved' => 'bg-emerald-50 text-emerald-700',
        'dismissed' => 'bg-brand-ink/10 text-brand-ink/70',
    ][$value] ?? 'bg-brand-ink/10 text-brand-ink';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold', $classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst(str_replace('_', ' ', $value)) }}
</span>
