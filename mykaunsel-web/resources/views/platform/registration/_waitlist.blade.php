@php
    // The waitlist of the closed sign-ups. Organizations and Counselors only: individuals sign up in the mobile app.
    $wl = $waitlist;
    $labels = collect(config('platform-settings.registration_roles'))->only($wl['types'])->map(fn ($role) => $role['label']);
    $counts = $wl['counts'][$wl['tab']];
    $label = $labels[$wl['tab']];
    $blocked = $wl['blockers'] !== [];
    $tabs = $labels->map(fn ($text, $type) => ['key' => $type, 'label' => $text, 'count' => $wl['counts'][$type]['waiting'], 'url' => route('platform.registration', ['waitlist' => $type]).'#waitlist'])->values()->all();
@endphp

<x-platform.panel class="mt-5 scroll-mt-6" id="waitlist" title="Waitlist">
    <p class="mt-1 max-w-[70ch] text-[13px] text-brand-ink/60">
        While a sign-up is closed, people can leave their email to hear when it reopens. When you have reopened it, <strong>Notify all</strong> sends each of them <strong>one</strong> email with a link to the normal sign-up page. Nobody is signed up automatically, and reopening a sign-up never sends anything by itself.
    </p>

    @unless ($wl['enabled'])
        <p class="mt-3 rounded-xl bg-brand-yellow/25 px-4 py-3 text-[13px] text-[#6B4C0C]" role="alert">
            The waitlist is switched off: there is no <code class="rounded bg-white/70 px-1">WAITLIST_HMAC_KEY</code> in .env (make one with <code class="rounded bg-white/70 px-1">php artisan waitlist:key</code>). Nobody can join until it is set.
        </p>
    @else
        <x-platform.status-tabs :tabs="$tabs" :active="$wl['tab']" label="Waitlist by kind of sign-up" class="!mt-4" />

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[13.5px]" aria-live="polite">
                <span class="font-bold">{{ $counts['waiting'] }}</span> waiting ·
                <span class="font-bold">{{ $counts['notified'] }}</span> already emailed ·
                <span class="font-bold">{{ $counts['opted_out'] }}</span> opted out
                <span class="text-brand-ink/50">(for {{ strtolower($label) }} sign-up)</span>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('platform.registration.waitlist.export', $wl['tab']) }}" class="inline-flex min-h-[40px] items-center rounded-full border border-brand-ink/20 px-5 text-[13px] font-semibold transition hover:bg-brand-ink/5">Export CSV</a>
                <button type="button" x-data @click="$dispatch('open-modal', 'waitlist-notify')" @disabled($blocked)
                        class="inline-flex min-h-[40px] items-center rounded-full bg-brand-blue px-5 text-[13px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40">Notify all ({{ $counts['waiting'] }})</button>
            </div>
        </div>

        {{-- Why "Notify all" is off, in words. --}}
        @if ($blocked)
            <ul class="mt-2 list-disc pl-5 text-[12.5px] text-brand-ink/60" id="waitlist-notify-why">
                @foreach ($wl['blockers'] as $reason)<li>{{ $reason }}</li>@endforeach
            </ul>
        @endif

        <form method="GET" action="{{ route('platform.registration') }}#waitlist" role="search" aria-label="Search the waitlist" class="mt-4 flex flex-wrap items-center gap-2">
            <input type="hidden" name="waitlist" value="{{ $wl['tab'] }}">
            <label class="relative min-w-[220px] flex-1 sm:max-w-[340px]">
                <span class="sr-only">Search by email</span>
                <input type="search" name="q" value="{{ $wl['search'] }}" maxlength="100" placeholder="Search by email"
                       class="w-full rounded-full border border-brand-ink/10 bg-white py-2.5 pl-4 pr-4 text-[14px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
            </label>
            <button type="submit" class="rounded-full bg-brand-blue px-5 py-2.5 text-[14px] font-semibold text-white transition hover:bg-brand-deep">Search</button>
            @if ($wl['search'] !== '')
                <a href="{{ route('platform.registration', ['waitlist' => $wl['tab']]) }}#waitlist" class="text-[13px] font-semibold text-brand-blue hover:underline">Clear</a>
            @endif
        </form>

        @if ($wl['entries']->isEmpty())
            <p class="mt-4 rounded-xl bg-cream px-4 py-8 text-center text-[13.5px] text-brand-ink/60">
                {{ $wl['search'] !== '' ? 'No address matches your search.' : 'Nobody is on this waitlist yet.' }}
            </p>
        @else
            <x-platform.table :headings="['Email', 'Signed up', 'Status', 'Emailed', '']">
                @foreach ($wl['entries'] as $entry)
                    <tr class="border-t border-brand-ink/[.07]">
                        <td class="px-4 py-3 text-[13.5px] font-medium break-all">{{ $entry->email }}</td>
                        <td class="px-4 py-3 text-[13px] text-brand-ink/70 whitespace-nowrap">{{ $entry->created_at->copy()->timezone($wl['timezone'])->format('j M Y, g:i A') }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $entry->status === 'waiting' ? 'bg-brand-yellow/25 text-[#7A5410]' : 'bg-emerald-50 text-emerald-700' }}">{{ $entry->status === 'waiting' ? 'Waiting' : 'Notified' }}</span>
                        </td>
                        <td class="px-4 py-3 text-[13px] text-brand-ink/70 whitespace-nowrap">{{ $entry->notified_at ? $entry->notified_at->copy()->timezone($wl['timezone'])->format('j M Y, g:i A') : '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" x-data @click="$dispatch('open-modal', 'waitlist-remove-{{ $entry->id }}')" class="text-[13px] font-semibold text-brand-red hover:underline">Remove</button>
                        </td>
                    </tr>
                @endforeach
            </x-platform.table>

            @if ($wl['entries']->hasPages())
                <div class="mt-4">{{ $wl['entries']->links() }}</div>
            @endif
        @endif
    @endunless
</x-platform.panel>

@if ($wl['enabled'])
    <x-platform.confirm-modal name="waitlist-notify" :action="route('platform.registration.waitlist.notify', $wl['tab'])"
        :title="'Email '.$counts['waiting'].' '.($counts['waiting'] === 1 ? 'person' : 'people').'?'" submit-label="Send the emails" busy-label="Queueing…">
        <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> <strong>{{ $counts['waiting'] }}</strong> {{ $counts['waiting'] === 1 ? 'person is' : 'people are' }} waiting for {{ strtolower($label) }} sign-up. Each gets <strong>one</strong> email saying it is open, with a link to the sign-up page.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> People already emailed are not emailed again. The emails go out about {{ config('waitlist.send_per_minute') }} a minute.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Every email has a link to opt out.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> This cannot be undone. Only do it when the sign-up really is open.</li>
        </ul>
    </x-platform.confirm-modal>

    @if (! $wl['entries']?->isEmpty())
        @foreach ($wl['entries'] as $entry)
            <x-platform.confirm-modal :name="'waitlist-remove-'.$entry->id" :action="route('platform.registration.waitlist.remove', $entry)" title="Remove this address?" submit-label="Remove" busy-label="Removing…">
                @method('DELETE')
                <p class="mt-3 text-[13.5px]"><strong class="break-all">{{ $entry->email }}</strong> is taken off the waitlist and will not be emailed. Do this when the person asks. They can sign up again.</p>
            </x-platform.confirm-modal>
        @endforeach
    @endif
@endif
