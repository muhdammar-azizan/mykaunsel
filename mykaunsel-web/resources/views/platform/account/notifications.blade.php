<x-platform-layout title="Account · Notifications">
    <div>
        <x-platform.page-header title="Account" subtitle="Manage your profile, security and notifications" />
        <x-platform.account-tabs active="notifications" />

        <div class="mt-6 max-w-[760px]">
            <x-platform.panel title="Email notifications">
                <p class="mt-1 text-[13px] text-brand-ink/60">
                    Sent to <span class="font-semibold text-brand-ink">{{ $user->email }}</span>. Changes save automatically.
                </p>

                <ul class="mt-4 divide-y divide-brand-ink/[.07]">
                    @foreach ($preferences as $item)
                        <li x-data="{
                                on: @js($item['enabled']),
                                state: 'idle',
                                timer: null,
                                async toggle() {
                                    const next = !this.on;
                                    this.on = next;
                                    this.state = 'saving';
                                    clearTimeout(this.timer);
                                    try {
                                        const res = await fetch(@js(route('platform.account.notifications.update')), {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            },
                                            body: JSON.stringify({ key: @js($item['key']), enabled: next }),
                                        });
                                        if (!res.ok) throw new Error('save failed');
                                        this.state = 'saved';
                                    } catch (e) {
                                        this.on = !next;
                                        this.state = 'error';
                                    }
                                    this.timer = setTimeout(() => this.state = 'idle', 2200);
                                },
                            }"
                            class="flex items-center justify-between gap-6 py-4">
                            <div class="min-w-0">
                                <p id="n-{{ $item['key'] }}" class="text-[14px] font-semibold">{{ $item['label'] }}</p>
                                <p class="mt-0.5 text-[12.5px] text-brand-ink/55">{{ $item['description'] }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <span class="w-[92px] text-right text-[12px] font-medium" aria-live="polite">
                                    <span x-show="state === 'saving'" x-cloak class="text-brand-ink/45">Saving…</span>
                                    <span x-show="state === 'saved'" x-cloak x-transition.opacity class="inline-flex items-center gap-1 text-emerald-600">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Saved
                                    </span>
                                    <span x-show="state === 'error'" x-cloak class="text-brand-red">Couldn't save</span>
                                </span>

                                <button type="button" role="switch" :aria-checked="on.toString()" aria-labelledby="n-{{ $item['key'] }}"
                                        @click="toggle()"
                                        :class="on ? 'bg-brand-blue' : 'bg-brand-ink/20'"
                                        class="relative h-6 w-11 shrink-0 rounded-full transition-colors duration-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue">
                                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'"
                                          class="absolute left-0 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform duration-200"></span>
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-platform.panel>

            <p class="mt-4 text-[12.5px] text-brand-ink/50">
                Email is the only channel for now. Preferences are saved to your account and will apply as each of these alerts is switched on across the platform.
            </p>
        </div>
    </div>

    <x-platform.toast />
</x-platform-layout>
