@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 pr-16 text-[14px] text-brand-ink focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
    $primary = 'inline-flex min-h-[42px] items-center justify-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40';
    $ghost = 'inline-flex min-h-[42px] items-center justify-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5';
    $otherSessions = $sessions->where('current', false)->count();
    $dot = [
        'auth.login' => 'bg-brand-blue', 'auth.login_failed' => 'bg-brand-red', 'account.password_changed' => 'bg-brand-yellow',
        'account.other_sessions_revoked' => 'bg-brand-yellow', 'account.email_change_requested' => 'bg-brand-ink/40', 'account.email_changed' => 'bg-brand-yellow',
    ];
@endphp

<x-platform-layout title="Account · Security">
    <div>
        <x-platform.page-header title="Account" subtitle="Manage your profile, security and notifications" />
        <x-platform.account-tabs active="security" />

        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">

            {{-- LEFT: change password --}}
            <x-platform.panel title="Change password">
                <p class="mt-1 text-[13px] text-brand-ink/60">Choose a strong password you don't use anywhere else. Changing it signs out your other devices.</p>

                <form method="POST" action="{{ route('platform.account.security.password') }}" class="mt-5"
                      x-data="{ ready: false, show: false }">
                    @csrf @method('PUT')

                    <div>
                        <label for="current_password" class="{{ $label }}">Current password</label>
                        <div class="relative">
                            <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="{{ $input }}">
                            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 mt-[3px] -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="show ? 'Hide' : 'Show'">Show</button>
                        </div>
                        @error('current_password', 'updatePassword')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                    </div>

                    <div class="mt-5">
                        <x-password-fields bag="updatePassword" x-model="ready" />
                    </div>

                    <button type="submit" class="{{ $primary }} mt-6" :disabled="!ready">Update password</button>
                </form>
            </x-platform.panel>

            {{-- RIGHT: sessions, activity, 2FA placeholder --}}
            <div class="flex flex-col gap-5">

                <x-platform.panel title="Active sessions">
                    @if (! $driverIsDatabase)
                        <p class="mt-3 rounded-xl bg-brand-yellow/15 p-4 text-[13px] text-brand-ink/75">
                            Session tracking needs <code class="font-semibold">SESSION_DRIVER=database</code> in your <code>.env</code>.
                        </p>
                    @else
                        <ul class="mt-4 divide-y divide-brand-ink/[.07]">
                            @forelse ($sessions as $session)
                                <li class="flex items-start gap-3.5 py-3.5">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-blue/10 text-brand-blue">
                                        @if ($session['mobile'])
                                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg>
                                        @else
                                            <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-2 text-[14px] font-semibold">
                                            {{ $session['label'] }}
                                            @if ($session['current'])
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide text-emerald-700">This device</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-[12.5px] text-brand-ink/55" title="{{ $session['last_active_exact'] }}">
                                            {{ $session['ip'] ?: 'Unknown IP' }} · {{ $session['current'] ? 'Active now' : 'Last active '.$session['last_active'] }}
                                        </p>
                                    </div>
                                </li>
                            @empty
                                <li class="py-6 text-center text-[13px] text-brand-ink/50">No sessions found.</li>
                            @endforelse
                        </ul>

                        <div class="mt-2" x-data="{ open: @js($errors->getBag('revokeSessions')->any()) }">
                            <button type="button" @click="open = true" class="{{ $ghost }}" @disabled($otherSessions === 0)>Log out other sessions</button>
                            @if ($otherSessions === 0)<span class="ml-2 text-[12.5px] text-brand-ink/50">No other sessions.</span>@endif

                            <div x-show="open" x-cloak class="fixed inset-0 z-[95] flex items-center justify-center px-4" role="dialog" aria-modal="true" aria-label="Confirm with password"
                                 @keydown.escape.window="open = false">
                                <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-brand-deep/50 backdrop-blur-sm"></div>
                                <form method="POST" action="{{ route('platform.account.security.sessions.revoke') }}" x-show="open"
                                      x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
                                      class="relative w-full max-w-[420px] rounded-2xl bg-white p-6 shadow-2xl"
                                      x-data="{ show: false }">
                                    @csrf
                                    <h3 class="text-[17px] font-bold">Log out other sessions?</h3>
                                    <p class="mt-1 text-[13px] text-brand-ink/60">Every device except this one will be signed out. Enter your password to confirm.</p>

                                    <label for="revoke_password" class="{{ $label }} mt-4">Current password</label>
                                    <div class="relative">
                                        <input id="revoke_password" name="current_password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="{{ $input }}"
                                               x-init="$watch('open', v => v && $nextTick(() => $el.focus()))">
                                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 mt-[3px] -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="show ? 'Hide' : 'Show'">Show</button>
                                    </div>
                                    @error('current_password', 'revokeSessions')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror

                                    <div class="mt-5 flex justify-end gap-2">
                                        <button type="button" @click="open = false" class="{{ $ghost }}">Cancel</button>
                                        <button type="submit" class="{{ $primary }}">Log out other sessions</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </x-platform.panel>

                @include('platform.account.partials.two-factor')

                <x-platform.panel title="Recent security activity" id="activity">
                    <ul class="mt-4 space-y-0.5">
                        @forelse ($activity as $event)
                            <li class="flex items-start gap-3 rounded-lg px-1 py-2.5">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dot[$event['action']] ?? 'bg-brand-ink/30' }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[13.5px] font-medium">{{ $event['label'] }}</p>
                                    <p class="text-[12px] text-brand-ink/50" title="{{ $event['at'] }}">{{ $event['at'] }}@if ($event['ip']) · {{ $event['ip'] }}@endif</p>
                                </div>
                                <span class="shrink-0 text-[12px] text-brand-ink/40">{{ $event['ago'] }}</span>
                            </li>
                        @empty
                            <li class="py-6 text-center text-[13px] text-brand-ink/50">No security activity recorded yet.</li>
                        @endforelse
                    </ul>
                </x-platform.panel>
            </div>
        </div>
    </div>

    <x-platform.toast />
</x-platform-layout>
