@props(['type' => 'button', 'variant' => 'primary'])

@php
$variants = [
    'primary' => 'bg-indigo-600 text-white hover:bg-indigo-500 focus:ring-indigo-500',
    'secondary' => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-indigo-500',
    'danger' => 'bg-red-600 text-white hover:bg-red-500 focus:ring-red-500',
];
$variantClasses = $variants[$variant] ?? $variants['primary'];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center px-4 py-2 rounded-md text-sm font-semibold shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed {$variantClasses}"]) }}
>
    {{ $slot }}
</button>
