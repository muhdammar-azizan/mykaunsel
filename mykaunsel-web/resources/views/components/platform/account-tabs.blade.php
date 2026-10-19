@props(['active'])

{{-- Account tabs. Needs a parent scope with a `dirty` flag (see platform/account/profile) to warn before leaving unsaved changes. --}}
@php
    $tabs = [
        'profile' => ['label' => 'Profile', 'route' => 'platform.account'],
        'security' => ['label' => 'Security', 'route' => null],
        'notifications' => ['label' => 'Notifications', 'route' => 'platform.account.notifications'],
    ];
@endphp

<nav class="mt-6 flex gap-1 border-b border-brand-ink/10" aria-label="Account sections">
    @foreach ($tabs as $key => $tab)
        @if ($tab['route'])
            <a href="{{ route($tab['route']) }}"
               @click="if (typeof dirty !== 'undefined' && dirty && !confirm('You have unsaved changes. Leave without saving?')) $event.preventDefault()"
               @if ($active === $key) aria-current="page" @endif
               class="-mb-px border-b-2 px-4 py-2.5 text-[14px] font-semibold transition {{ $active === $key ? 'border-brand-blue text-brand-blue' : 'border-transparent text-brand-ink/60 hover:text-brand-ink' }}">
                {{ $tab['label'] }}
            </a>
        @else
            <span class="-mb-px flex cursor-not-allowed items-center gap-2 border-b-2 border-transparent px-4 py-2.5 text-[14px] font-semibold text-brand-ink/40" title="Coming soon">
                {{ $tab['label'] }}
                <span class="rounded-full bg-brand-ink/10 px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wide">Soon</span>
            </span>
        @endif
    @endforeach
</nav>
