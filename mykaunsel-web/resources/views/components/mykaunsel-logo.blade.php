@props(['href' => '/', 'variant' => 'light'])

{{-- The official MyKaunsel logo. On a dark background (variant "dark") it sits on a cream chip so its colours stay as designed. --}}
<a href="{{ $href }}" aria-label="MyKaunsel" {{ $attributes->merge(['class' => 'flex shrink-0 items-center'.($variant === 'dark' ? ' self-start rounded-xl bg-cream px-3.5 py-2' : '')]) }}>
    <img src="/images/mykaunsel-logo.png" alt="MyKaunsel" class="{{ $variant === 'dark' ? 'h-8' : 'h-10' }} w-auto">
</a>
