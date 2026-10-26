@php
    $allOpen = ! in_array(false, $status, true);
    $allClosed = ! in_array(true, $status, true);
    $textareaClass = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $pill = fn (bool $open) => $open ? 'bg-emerald-50 text-emerald-700' : 'bg-brand-red/10 text-brand-red';
@endphp

<x-platform-layout title="Registration Control">
    {{-- A clear ring when a button, link or field is reached with the keyboard (these switches matter). --}}
    <style>
        .reg-focus :is(button, a, input, textarea, select):focus-visible { outline: 2px solid #1469B0; outline-offset: 2px; }
    </style>
    <div class="reg-focus">
    <x-platform.page-header title="Registration Control" subtitle="Close sign-ups, switch maintenance mode on, and edit what people are told. Every change is saved in the audit log." />

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    @if ($errors->getBag('maintenance')->has('reopen_at'))
        <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">
            The reopen time was not saved. {{ $errors->getBag('maintenance')->first('reopen_at') }}
        </div>
    @endif

    {{-- Maintenance --}}
    <x-platform.panel class="mt-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <h2 class="text-[14px] font-bold">Maintenance mode</h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $maintenance ? 'bg-brand-red/10 text-brand-red' : 'bg-emerald-50 text-emerald-700' }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $maintenance ? 'On' : 'Off' }}</span>
                </div>
                <p class="mt-1 max-w-[62ch] text-[13px] text-brand-ink/60">When on, <strong>nobody can use MyKaunsel except signed-in Platform Admins</strong>. Everyone else sees the maintenance message. The sign-in page stays open, so you can always get back in to switch it off.</p>
            </div>
            @if ($maintenance)
                <form method="POST" action="{{ route('platform.registration.maintenance') }}" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <input type="hidden" name="state" value="off">
                    <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">Turn maintenance off</button>
                </form>
            @else
                <button type="button" x-data @click="$dispatch('open-modal', 'maintenance-on')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-6 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Turn maintenance on</button>
            @endif
        </div>

        {{-- While it is on: when it switches itself off (the scheduler checks every minute). --}}
        @if ($maintenance)
            <div class="mt-4 rounded-xl bg-cream px-4 py-3.5" id="reopen">
                @if ($reopenAt)
                    <p class="text-[13.5px]">
                        <span class="font-bold">Switches off by itself at {{ $reopenAt->format('j M Y, g:i A') }}</span>
                        <span class="text-brand-ink/60">({{ $timezoneLabel }}) — {{ $reopenOverdue ? 'that time has passed' : $reopenAt->diffForHumans() }}.</span>
                    </p>
                    @if ($reopenOverdue)
                        <p class="mt-2 rounded-lg bg-brand-yellow/25 px-3 py-2 text-[13px] text-[#6B4C0C]" role="alert">
                            The time has passed but maintenance is still on. The scheduler may not be running: the server must run <code class="rounded bg-white/70 px-1">php artisan schedule:run</code> every minute. You can turn maintenance off now.
                        </p>
                    @endif
                @else
                    <p class="text-[13.5px]"><span class="font-bold">No reopen time set.</span> <span class="text-brand-ink/60">Maintenance stays on until you turn it off.</span></p>
                @endif

                <form method="POST" action="{{ route('platform.registration.maintenance.reopen') }}" class="mt-3 flex flex-wrap items-end gap-2" x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="reopen-at" class="block text-[12.5px] font-semibold text-brand-ink/70">{{ $reopenAt ? 'Change the reopen time' : 'Switch off by itself at' }} <span class="font-normal text-brand-ink/50">({{ $timezoneLabel }})</span></label>
                        <input id="reopen-at" type="datetime-local" name="reopen_at" min="{{ $nowLocal }}" value="{{ old('reopen_at', $reopenAt?->format('Y-m-d\TH:i')) }}" required
                               class="mt-1.5 rounded-xl border border-brand-ink/15 bg-white px-3 py-2 text-[13.5px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                    </div>
                    <button type="submit" :disabled="busy" class="inline-flex min-h-[40px] items-center rounded-full border border-brand-ink/20 px-5 text-[13px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60">Save time</button>
                </form>

                @if ($reopenAt)
                    <form method="POST" action="{{ route('platform.registration.maintenance.reopen') }}" class="mt-2" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="reopen_at" value="">
                        <button type="submit" :disabled="busy" class="text-[13px] font-semibold text-brand-blue hover:underline disabled:opacity-60">Remove the reopen time</button>
                    </form>
                @endif
            </div>
        @endif
    </x-platform.panel>

    {{-- Sign-ups --}}
    <x-platform.panel class="mt-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-[14px] font-bold">Sign-ups</h2>
                <p class="mt-1 max-w-[62ch] text-[13px] text-brand-ink/60">Closing a sign-up stops <strong>new</strong> accounts of that kind. People who already have an account, or who already started signing up, are not affected.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @unless ($allClosed)
                    <button type="button" x-data @click="$dispatch('open-modal', 'close-all')" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-red/40 px-5 text-[13.5px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Close all sign-ups</button>
                @endunless
                @unless ($allOpen)
                    <form method="POST" action="{{ route('platform.registration.all') }}" x-data="{ busy: false }" @submit="busy = true">
                        @csrf
                        <input type="hidden" name="state" value="open">
                        <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">Open all sign-ups</button>
                    </form>
                @endunless
            </div>
        </div>

        <ul class="mt-4 divide-y divide-brand-ink/[.07]">
            @foreach ($roles as $role => $info)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <p class="text-[14px] font-semibold">{{ $info['label'] }}</p>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $pill($status[$role]) }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $status[$role] ? 'Open' : 'Closed' }}</span>
                        </div>
                        <p class="mt-0.5 max-w-[60ch] text-[12.5px] text-brand-ink/55">{{ $info['hint'] }}</p>
                        @if ($waitlist['enabled'] && isset($waitlist['counts'][$role]))
                            <a href="{{ route('platform.registration', ['waitlist' => $role]) }}#waitlist" class="mt-1 inline-block text-[12.5px] font-semibold text-brand-blue hover:underline">{{ $waitlist['counts'][$role]['waiting'] }} waiting on the waitlist</a>
                        @endif
                    </div>
                    @if ($status[$role])
                        <button type="button" x-data @click="$dispatch('open-modal', 'close-{{ $role }}')" class="inline-flex min-h-[38px] items-center rounded-full border border-brand-red/40 px-5 text-[13px] font-semibold text-brand-red transition hover:bg-brand-red hover:text-white">Close</button>
                    @else
                        <form method="POST" action="{{ route('platform.registration.toggle', $role) }}" x-data="{ busy: false }" @submit="busy = true">
                            @csrf
                            <input type="hidden" name="state" value="open">
                            <button type="submit" :disabled="busy" class="inline-flex min-h-[38px] items-center rounded-full border border-brand-ink/20 px-5 text-[13px] font-semibold transition hover:bg-brand-ink/5 disabled:opacity-60">Open</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-platform.panel>

    {{-- Waitlist (Organizations and Counselors only) --}}
    @include('platform.registration._waitlist')

    {{-- Wording --}}
    <x-platform.panel class="mt-5" title="What people are told">
        <p class="mt-1 text-[13px] text-brand-ink/60">Plain text. In the sign-up message, <code class="rounded bg-brand-ink/5 px-1">{role}</code> becomes <em>organization</em>, <em>counselor</em> or <em>individual</em> (<em>account</em> when everything is closed).</p>

        <form method="POST" action="{{ route('platform.registration.messages') }}" x-data="{ busy: false, reg: @js(old('registration_closed', $registrationMessage)), mnt: @js(old('maintenance', $maintenanceMessage)), regDefault: @js($defaults['registration_closed']), mntDefault: @js($defaults['maintenance']), regSaved: @js($registrationMessage), mntSaved: @js($maintenanceMessage), get dirty() { return this.reg !== this.regSaved || this.mnt !== this.mntSaved } }" @submit="busy = true" @beforeunload.window="if (dirty && ! busy) { $event.preventDefault(); $event.returnValue = '' }" class="mt-4">
            @csrf
            @method('PUT')

            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                    <label for="msg-registration" class="block text-[12.5px] font-semibold text-brand-ink/70">When a sign-up is closed</label>
                    <textarea id="msg-registration" name="registration_closed" x-model="reg" rows="4" maxlength="{{ $max }}" required class="{{ $textareaClass }}"></textarea>
                    @error('registration_closed', 'messages')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                    <div class="mt-1 flex items-center justify-between text-[11.5px] text-brand-ink/45">
                        <button type="button" @click="reg = regDefault" class="font-semibold text-brand-blue hover:underline">Use the original wording</button>
                        <span><span x-text="reg.length">0</span>/{{ $max }}</span>
                    </div>
                    <p class="mt-3 text-[12px] font-semibold uppercase tracking-wide text-brand-ink/50">How it reads</p>
                    <ul class="mt-1.5 space-y-1.5 text-[13px] text-brand-ink/75">
                        @foreach ($roles as $role => $info)
                            <li class="rounded-lg bg-cream px-3 py-2"><span class="font-semibold">{{ $info['label'] }}:</span> <span x-text="reg.replaceAll('{role}', @js($info['word']))"></span></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <label for="msg-maintenance" class="block text-[12.5px] font-semibold text-brand-ink/70">During maintenance</label>
                    <textarea id="msg-maintenance" name="maintenance" x-model="mnt" rows="4" maxlength="{{ $max }}" required class="{{ $textareaClass }}"></textarea>
                    @error('maintenance', 'messages')<p class="mt-1.5 text-[12.5px] font-semibold text-brand-red">{{ $message }}</p>@enderror
                    <div class="mt-1 flex items-center justify-between text-[11.5px] text-brand-ink/45">
                        <button type="button" @click="mnt = mntDefault" class="font-semibold text-brand-blue hover:underline">Use the original wording</button>
                        <span><span x-text="mnt.length">0</span>/{{ $max }}</span>
                    </div>
                    <p class="mt-3 text-[12px] font-semibold uppercase tracking-wide text-brand-ink/50">How it reads</p>
                    <p class="mt-1.5 whitespace-pre-line break-words rounded-lg bg-cream px-3 py-2 text-[13px] text-brand-ink/75" x-text="mnt"></p>
                </div>
            </div>

            <div class="mt-5 flex items-center justify-end gap-4">
                {{-- Said in words, not only by a colour; leaving the page with unsaved wording asks first. --}}
                <p x-show="dirty && ! busy" x-cloak class="text-[12.5px] font-semibold text-[#7A5410]" role="status"><span aria-hidden="true">●</span> Unsaved changes</p>
                <button type="submit" :disabled="busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:opacity-60">Save messages</button>
            </div>
        </form>
    </x-platform.panel>

    <p class="mt-4 text-[13px] text-brand-ink/60">
        <a href="{{ route('platform.audit-log', ['category' => 'settings']) }}" class="font-semibold text-brand-blue hover:underline">See the history →</a>
    </p>

    {{-- Confirmations --}}
    <x-platform.confirm-modal name="maintenance-on" :action="route('platform.registration.maintenance')" title="Turn maintenance mode on?" submit-label="Turn maintenance on" busy-label="Turning on…">
        <input type="hidden" name="state" value="on">
        <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
            <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> Right away, clients, counselors and organization admins can no longer use MyKaunsel. They see your maintenance message.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> You and other signed-in Platform Admins keep working. The sign-in page stays open.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Nothing is deleted. Turn it off here when you are done, or choose a time below and it switches itself off.</li>
        </ul>

        {{-- Optional: the time it ends by itself. Read as the admin's own time zone; the chips count from the clock shown. --}}
        <div class="mt-4 rounded-xl bg-cream px-4 py-3.5" x-data="{ at: '', now: @js($nowLocal), plus(minutes) { const d = new Date(this.now + ':00Z'); d.setUTCMinutes(d.getUTCMinutes() + minutes); this.at = d.toISOString().slice(0, 16) } }">
            <label for="reopen-at-new" class="block text-[12.5px] font-semibold text-brand-ink/70">Switch off by itself at <span class="font-normal text-brand-ink/50">(optional · {{ $timezoneLabel }})</span></label>
            <input id="reopen-at-new" type="datetime-local" name="reopen_at" x-model="at" min="{{ $nowLocal }}" class="mt-1.5 rounded-xl border border-brand-ink/15 bg-white px-3 py-2 text-[13.5px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
            <div class="mt-2 flex flex-wrap items-center gap-2" aria-label="Quick times">
                @foreach ([30 => 'In 30 minutes', 60 => 'In 1 hour', 120 => 'In 2 hours', 240 => 'In 4 hours'] as $minutes => $text)
                    <button type="button" @click="plus({{ $minutes }})" class="rounded-full border border-brand-ink/15 px-3 py-1 text-[12px] font-semibold text-brand-ink/70 transition hover:border-brand-blue hover:text-brand-blue">{{ $text }}</button>
                @endforeach
                <button type="button" x-show="at" x-cloak @click="at = ''" class="text-[12px] font-semibold text-brand-blue hover:underline">No time</button>
            </div>
            <p class="mt-2 text-[12px] text-brand-ink/55">Leave it empty to keep maintenance on until you turn it off. At most {{ $maxDays }} days ahead. Visitors are told when to come back.</p>
        </div>
    </x-platform.confirm-modal>

    <x-platform.confirm-modal name="close-all" :action="route('platform.registration.all')" title="Close every sign-up?" submit-label="Close all sign-ups" busy-label="Closing…">
        <input type="hidden" name="state" value="closed">
        <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
            <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> Organizations, counselors and individuals can no longer create a new account. They see your sign-up message.</li>
            <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> People who already have an account, or already started signing up, are not affected.</li>
        </ul>
    </x-platform.confirm-modal>

    @foreach ($roles as $role => $info)
        @if ($status[$role])
            <x-platform.confirm-modal :name="'close-'.$role" :action="route('platform.registration.toggle', $role)" :title="'Close '.strtolower($info['label']).' sign-up?'" submit-label="Close sign-up" busy-label="Closing…">
                <input type="hidden" name="state" value="closed">
                <ul class="mt-4 space-y-2.5 text-[13.5px] leading-snug">
                    <li class="flex gap-2.5"><span class="mt-0.5 text-brand-red">!</span> New {{ strtolower($info['label']) }} can no longer sign up. They will see: “{{ $previews[$role] }}”</li>
                    <li class="flex gap-2.5"><span class="mt-0.5 text-emerald-600">✓</span> Existing accounts and sign-ups already in progress are not affected.</li>
                </ul>
            </x-platform.confirm-modal>
        @endif
    @endforeach

    <x-platform.toast />
    </div>
</x-platform-layout>
