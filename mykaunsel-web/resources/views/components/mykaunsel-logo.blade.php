@props(['href' => '/', 'variant' => 'light'])

{{-- The official MyKaunsel logo. On a dark background or a picture (variant "dark") it is the white version: white wordmark, the heart and the underline in lighter colours. --}}
<a href="{{ $href }}" aria-label="MyKaunsel" {{ $attributes->merge(['class' => 'flex shrink-0 items-center'.($variant === 'dark' ? ' self-start' : '')]) }}>
    <img src="{{ asset($variant === 'dark' ? 'images/mykaunsel-logo-white.png' : 'images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="{{ $variant === 'dark' ? 'h-9' : 'h-10' }} w-auto">
</a>
