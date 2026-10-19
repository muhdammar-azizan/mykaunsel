@props(['title' => null])

<section {{ $attributes->class('rounded-[20px] border border-brand-ink/10 bg-white p-6') }}>
    @if ($title)
        <h2 class="text-[14px] font-bold">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
