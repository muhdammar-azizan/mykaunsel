@props(['date', 'long' => false])

{{-- PA "valid until" with its warning colour: amber when it runs out within 30 days, red once expired (see PaValidity). --}}
@php
    $expired = \App\Support\PaValidity::isExpired($date);
    $soon = \App\Support\PaValidity::expiresWithin($date, \App\Models\CounselorProfile::PA_EXPIRING_DAYS);
    $left = \App\Support\PaValidity::daysLeft($date);
    $classes = $expired ? 'bg-brand-red/10 text-brand-red' : ($soon ? 'bg-brand-yellow/25 text-[#7A5410]' : 'bg-brand-ink/[.05] text-brand-ink/75');
@endphp

@if ($date)
    <span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-[12px] font-semibold', $classes]) }}
          title="{{ $expired ? 'Expired' : ($soon ? "Expires in {$left} ".\Illuminate\Support\Str::plural('day', $left) : 'Valid') }}">
        @if ($expired)
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
        @elseif ($soon)
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        @endif
        {{ $date->format('j M Y') }}
        @if ($long && $expired)<span class="font-bold">· Expired</span>@elseif ($long && $soon)<span class="font-bold">· {{ $left }} {{ \Illuminate\Support\Str::plural('day', $left) }} left</span>@endif
    </span>
@else
    <span class="text-brand-ink/40">—</span>
@endif
