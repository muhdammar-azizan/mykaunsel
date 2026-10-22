@props(['tabs', 'active', 'label'])

{{-- Tab bar with a live count on each tab. $tabs: list of ['key', 'label', 'count', 'url']. --}}
<nav {{ $attributes->merge(['class' => 'mt-6 flex gap-1 overflow-x-auto border-b border-brand-ink/10']) }} aria-label="{{ $label }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}" @if ($active === $tab['key']) aria-current="page" @endif
           class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-[14px] font-semibold transition {{ $active === $tab['key'] ? 'border-brand-blue text-brand-blue' : 'border-transparent text-brand-ink/60 hover:text-brand-ink' }}">
            {{ $tab['label'] }}
            <span class="min-w-[22px] rounded-full px-1.5 py-0.5 text-center text-[11px] font-bold {{ $active === $tab['key'] ? 'bg-brand-blue text-white' : 'bg-brand-ink/10 text-brand-ink/70' }}">{{ $tab['count'] }}</span>
        </a>
    @endforeach
</nav>
