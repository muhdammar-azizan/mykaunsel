@props(['title', 'subtitle' => null, 'center' => false])

<div {{ $attributes->class(['text-center' => $center]) }}>
    <h1 @class(['font-extrabold leading-tight tracking-tightest', 'text-[28px]' => $center, 'text-[22px]' => ! $center])>{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-[14px] text-brand-ink/55">{{ $subtitle }}</p>
    @endif
    {{ $slot }}
</div>
