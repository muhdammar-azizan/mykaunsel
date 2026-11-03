@props(['slot_name', 'ratio' => '16 / 9', 'kind' => 'image', 'label' => null, 'size' => null, 'alt' => '', 'rounded' => 'rounded-xl'])

{{-- A place for a picture or a video. Drop a file named after the slot into public/landing (for example hero.mp4 or hero.jpg: mp4, webm, webp, jpg, png or svg) and it
     appears here by itself. Until then a quiet placeholder shows what goes here, the shape and the file name. See public/landing/README.md. --}}
@php($file = \App\Support\LandingLinks::media($slot_name))

<div {{ $attributes->merge(['class' => "relative overflow-hidden {$rounded} bg-[#EEF2FA]"]) }} style="aspect-ratio: {{ $ratio }}">
    @if ($file && $file['kind'] === 'video')
        <video src="{{ $file['url'] }}" class="absolute inset-0 h-full w-full object-cover" autoplay muted loop playsinline aria-label="{{ $alt }}"></video>
    @elseif ($file)
        <img src="{{ $file['url'] }}" alt="{{ $alt }}" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
    @else
        <div class="absolute inset-0 grid place-items-center border border-dashed border-brand-ink/20 p-4 text-center" data-media-slot="{{ $slot_name }}">
            <div>
                <svg class="mx-auto text-brand-ink/35" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    @if ($kind === 'video')<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9.500 5 2.500-5 2.500z"/>@else<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.800"/><path d="m21 16-5-5-8 8"/>@endif
                </svg>
                <p class="mt-2 text-[12px] font-semibold uppercase tracking-[0.12em] text-brand-ink/50">{{ $label ?? ($kind === 'video' ? 'Video' : 'Gambar') }}</p>
                <p class="mt-1 text-[11.5px] text-brand-ink/40">{{ $size ?? $ratio }} · public/landing/{{ $slot_name }}.{{ $kind === 'video' ? 'mp4' : 'jpg' }}</p>
            </div>
        </div>
    @endif
</div>
