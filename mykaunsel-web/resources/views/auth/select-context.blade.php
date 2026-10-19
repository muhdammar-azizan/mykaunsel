@push('styles')
    <style>
        .cc-teks { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .cc-kad { opacity: 0; animation: masuk .5s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda, 0s);
            transition: border-color .25s ease-out, background-color .25s ease-out, transform .25s ease-out, box-shadow .25s ease-out; }
        .cc-kad:hover { border: 1.5px solid var(--aksen); padding: 22.5px;
            background: color-mix(in srgb, var(--aksen) 3%, #FAF8F5); transform: translateY(-3px);
            box-shadow: 0 18px 40px -20px rgba(14,42,51,.25); }
        .cc-anak { transition: color .25s ease-out, transform .25s ease-out; }
        .cc-kad:hover .cc-anak { color: var(--aksen); transform: translateX(4px); }
        @media (prefers-reduced-motion: reduce) {
            .cc-teks, .cc-kad { animation: none; opacity: 1; transform: none; }
            .cc-kad:hover { transform: none; }
        }
    </style>
@endpush

<x-auth-centered-layout title="Choose Account — MyKaunsel" content-max-width="1040px">
    <x-slot name="topRight">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <p class="flex items-center gap-1.5 pt-1 text-[13px] text-navy/50">
                {{ __('Not you?') }}
                <button type="submit" class="font-medium text-teal">{{ __('Log out') }}</button>
            </p>
        </form>
    </x-slot>

    @php
        $typeMeta = [
            'university' => ['label' => 'University', 'accent' => '#D98F4A', 'icon' => 'building'],
            'corporate' => ['label' => 'Corporate', 'accent' => '#4A7DFF', 'icon' => 'building'],
            'clinic' => ['label' => 'Clinic', 'accent' => '#0F6B7D', 'icon' => 'clinic'],
            'platform' => ['label' => 'Independent practice', 'accent' => '#4A7DFF', 'icon' => 'person'],
        ];
        $roleLabels = [
            'student' => 'Student',
            'staff' => 'Staff',
            'employee' => 'Employee',
            'counselor' => 'Registered Counselor',
            'org_admin' => 'Organization Administrator',
            'platform_admin' => 'Platform Administrator',
        ];
    @endphp

    <p class="cc-teks text-center text-[15px] text-navy/60">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</p>
    <h1 class="cc-teks mt-3 text-center text-[28px] font-semibold leading-[1.15] tracking-tightest md:text-[34px]" style="animation-delay:.06s">{{ __('Continue as') }}</h1>
    <p class="cc-teks mx-auto mt-4 max-w-[52ch] text-center text-[14.5px] leading-relaxed text-navy/55" style="animation-delay:.1s">{{ __('You have more than one role on MyKaunsel. Choose which one to use for this session.') }}</p>

    <div class="mt-12 flex flex-col items-center justify-center gap-5 md:flex-row md:flex-wrap md:items-stretch">
        @foreach ($memberships as $index => $membership)
            @php
                $meta = $typeMeta[$membership->organization->org_type->value] ?? ['label' => ucfirst($membership->organization->org_type->value), 'accent' => '#0F6B7D', 'icon' => 'building'];
                $roleLabel = $meta['icon'] === 'person' && $membership->role->value === 'counselor'
                    ? 'Verified Practitioner'
                    : ($roleLabels[$membership->role->value] ?? ucfirst($membership->role->value));
            @endphp
            <form method="POST" action="{{ route('context.store') }}" class="w-full md:w-[320px]">
                @csrf
                <input type="hidden" name="org_id" value="{{ $membership->org_id }}">
                <input type="hidden" name="role" value="{{ $membership->role->value }}">
                <button type="submit" class="cc-kad group relative flex w-full flex-col rounded-[14px] border border-navy/12 bg-cream p-6 text-left" style="--aksen:{{ $meta['accent'] }}; --tunda:{{ $index * 0.08 }}s">
                    <span class="grid h-11 w-11 place-items-center rounded-xl" style="background:color-mix(in srgb, var(--aksen) 10%, transparent); color:var(--aksen)">
                        @if ($meta['icon'] === 'person')
                            <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4.2" fill="currentColor" opacity=".45"/><path d="M4 21c0-4.42 3.58-8 8-8s8 3.58 8 8z" fill="currentColor" opacity=".8"/></svg>
                        @elseif ($meta['icon'] === 'clinic')
                            <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6" width="17" height="15" rx="2" fill="currentColor" opacity=".4"/><path d="M12 10v7M8.5 13.5h7" stroke="#FAF8F5" stroke-width="2.2" stroke-linecap="round"/></svg>
                        @else
                            <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="9" width="8" height="12" rx="1.5" fill="currentColor" opacity=".35"/><rect x="9" y="4" width="12" height="17" rx="1.5" fill="currentColor" opacity=".75"/><rect x="13" y="8" width="4" height="4" rx=".8" fill="#FAF8F5"/><rect x="13" y="14" width="4" height="4" rx=".8" fill="#FAF8F5"/></svg>
                        @endif
                    </span>
                    <span class="mt-5 block text-[10.5px] font-medium uppercase tracking-[0.16em]" style="color:{{ $meta['accent'] }}">{{ $meta['label'] }}</span>
                    <span class="mt-2 block text-[17px] font-semibold tracking-tight text-navy">{{ $membership->organization->name }}</span>
                    <span class="mt-1.5 block text-[13.5px] text-navy/60">{{ $roleLabel }}</span>
                    <span class="mt-4 block text-[12.5px] text-navy/45">{{ __('Joined :date', ['date' => $membership->joined_at->format('F Y')]) }}</span>
                    <span class="cc-anak absolute bottom-6 right-6 text-[15px] leading-none text-navy/30" aria-hidden="true">→</span>
                </button>
            </form>
        @endforeach
    </div>
</x-auth-centered-layout>
