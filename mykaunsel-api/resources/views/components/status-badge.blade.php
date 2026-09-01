@props(['status'])

@php
$value = $status instanceof \BackedEnum ? $status->value : (string) $status;

$colors = [
    'active' => 'bg-green-100 text-green-800',
    'approved' => 'bg-green-100 text-green-800',
    'confirmed' => 'bg-green-100 text-green-800',
    'completed' => 'bg-green-100 text-green-800',
    'attended' => 'bg-green-100 text-green-800',
    'pending' => 'bg-yellow-100 text-yellow-800',
    'notice_period' => 'bg-yellow-100 text-yellow-800',
    'under_review' => 'bg-yellow-100 text-yellow-800',
    'suspended' => 'bg-red-100 text-red-800',
    'rejected' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-red-100 text-red-800',
    'no_show' => 'bg-red-100 text-red-800',
    'dismissed' => 'bg-gray-100 text-gray-800',
    'offboarded' => 'bg-gray-100 text-gray-800',
    'alumni' => 'bg-gray-100 text-gray-800',
    'expired' => 'bg-gray-100 text-gray-800',
];

$classes = $colors[$value] ?? 'bg-gray-100 text-gray-800';
$label = ucwords(str_replace('_', ' ', $value));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $label }}
</span>
