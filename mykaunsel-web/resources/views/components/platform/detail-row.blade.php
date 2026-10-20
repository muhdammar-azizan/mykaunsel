@props(['label'])

{{-- One "label … value" line inside a detail card. --}}
<div class="flex items-start justify-between gap-6 py-2.5 text-[13.5px]">
    <dt class="shrink-0 text-brand-ink/55">{{ $label }}</dt>
    <dd class="min-w-0 break-words text-right font-semibold">{{ $slot }}</dd>
</div>
