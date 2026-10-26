@props(['document' => null, 'label'])

{{-- Small status chip for one document: Not uploaded / Uploaded / OK / Needs replacement / Removed. --}}
@php
    if ($document === null || (! $document->hasFile() && ! $document->isPurged())) {
        [$state, $tone] = ['Not uploaded', 'bg-brand-ink/5 text-brand-ink/55 ring-brand-ink/10'];
    } elseif ($document->isPurged()) {
        [$state, $tone] = ['Removed', 'bg-brand-ink/5 text-brand-ink/55 ring-brand-ink/10'];
    } elseif ($document->needsReplacement()) {
        [$state, $tone] = ['Needs replacement', 'bg-brand-red/10 text-brand-red ring-brand-red/25'];
    } elseif ($document->status === \App\Models\CounselorDocument::STATUS_OK) {
        [$state, $tone] = ['OK', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'];
    } else {
        [$state, $tone] = ['Uploaded', 'bg-brand-blue/10 text-brand-blue ring-brand-blue/20'];
    }
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11.5px] font-semibold ring-1 ring-inset', $tone]) }}>
    <span class="font-medium opacity-75">{{ $label }}:</span> {{ $state }}
</span>
