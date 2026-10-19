@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-[14px] text-brand-ink placeholder:text-brand-ink/35 focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
    $primary = 'inline-flex min-h-[42px] items-center justify-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40';
    $ghost = 'inline-flex min-h-[42px] items-center justify-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5';
@endphp

<x-platform-layout title="Account">
    <div x-data="{ profileDirty: false, avatarDirty: false, get dirty() { return this.profileDirty || this.avatarDirty; } }"
         @beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">

        <x-platform.page-header title="Account" subtitle="Manage your profile, security and notifications" />
        <x-platform.account-tabs active="profile" />

        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">

            {{-- LEFT: photo + read-only details --}}
            <div class="flex flex-col gap-5">
                <x-platform.panel title="Profile photo">
                    <form method="POST" action="{{ route('platform.account.avatar.store') }}" enctype="multipart/form-data"
                          x-data="{ preview: null }" @submit="avatarDirty = false" class="mt-4">
                        @csrf
                        <div class="flex items-center gap-5">
                            <div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-full bg-brand-yellow text-[28px] font-bold text-brand-ink shadow-md ring-4 ring-white">
                                <template x-if="preview"><img :src="preview" alt="New photo preview" class="h-full w-full object-cover"></template>
                                <template x-if="!preview">
                                    @if ($user->avatar_url)
                                        <img src="{{ $user->avatar_url }}" alt="Your photo" class="h-full w-full object-cover">
                                    @else
                                        <span>{{ $user->initials }}</span>
                                    @endif
                                </template>
                            </div>

                            <div class="min-w-0">
                                <label class="{{ $ghost }} cursor-pointer">
                                    Choose photo
                                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                           @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); avatarDirty = true; }">
                                </label>
                                <p class="mt-2 text-[12px] text-brand-ink/50">JPG, PNG or WEBP, up to 2MB. Shown as a square.</p>
                            </div>
                        </div>

                        @error('avatar')<p class="mt-3 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror

                        <div x-show="preview" x-cloak class="mt-4 flex gap-2">
                            <button type="submit" class="{{ $primary }}">Upload photo</button>
                            <button type="button" class="{{ $ghost }}" @click="preview = null; avatarDirty = false; $root.querySelector('input[type=file]').value = ''">Cancel</button>
                        </div>
                    </form>

                    @if ($user->avatar_url)
                        <form method="POST" action="{{ route('platform.account.avatar.destroy') }}" class="mt-3" onsubmit="return confirm('Remove your profile photo?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-[13px] font-semibold text-brand-red hover:underline">Remove photo</button>
                        </form>
                    @endif
                </x-platform.panel>

                <x-platform.panel title="Account details">
                    <dl class="mt-4 divide-y divide-brand-ink/[.07] text-[13.5px]">
                        <div class="flex items-center justify-between py-3"><dt class="text-brand-ink/55">Role</dt><dd class="font-semibold">{{ $roleLabel }}</dd></div>
                        <div class="flex items-center justify-between py-3"><dt class="text-brand-ink/55">Member since</dt><dd class="font-semibold">{{ $user->created_at->timezone($user->display_timezone)->format('j M Y') }}</dd></div>
                        <div class="flex items-start justify-between gap-4 py-3">
                            <dt class="text-brand-ink/55">Last login</dt>
                            <dd class="text-right font-semibold">
                                @if ($user->last_login_at)
                                    {{ $user->last_login_at->timezone($user->display_timezone)->format('j M Y, g:i A') }}
                                    <span class="block text-[12px] font-normal text-brand-ink/50">{{ $user->last_login_ip }}</span>
                                @else
                                    <span class="font-normal text-brand-ink/50">Not recorded yet</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-platform.panel>
            </div>

            {{-- RIGHT: editable details + email --}}
            <div class="flex flex-col gap-5">
                <x-platform.panel title="Personal information">
                    <form method="POST" action="{{ route('platform.account.update') }}" class="mt-4 space-y-4"
                          x-data="{ name: @js(old('name', $user->name)), tz: @js(old('timezone', $user->display_timezone)),
                                    orig: { name: @js($user->name), tz: @js($user->display_timezone) },
                                    get changed() { return this.name !== this.orig.name || this.tz !== this.orig.tz; } }"
                          x-effect="profileDirty = changed" @submit="profileDirty = false">
                        @csrf @method('PATCH')

                        <div>
                            <label for="name" class="{{ $label }}">Full name</label>
                            <input id="name" name="name" type="text" x-model="name" required maxlength="100" autocomplete="name" class="{{ $input }}">
                            @error('name')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="timezone" class="{{ $label }}">Timezone</label>
                            <select id="timezone" name="timezone" x-model="tz" class="{{ $input }}">
                                @foreach ($timezones as $zone)
                                    <option value="{{ $zone }}">{{ str_replace('_', ' ', $zone) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-[12px] text-brand-ink/50">Dates and times on your account are shown in this timezone.</p>
                            @error('timezone')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                        </div>

                        <div class="flex items-center gap-3 pt-1">
                            <button type="submit" class="{{ $primary }}" :disabled="!changed">Save changes</button>
                            <span x-show="changed" x-cloak class="text-[12.5px] text-brand-ink/50">You have unsaved changes.</span>
                        </div>
                    </form>
                </x-platform.panel>

                <x-platform.panel title="Email address">
                    <div class="mt-4 flex flex-wrap items-center gap-2.5">
                        <span class="text-[14px] font-semibold">{{ $user->email }}</span>
                        @if ($user->email_verified_at)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Verified
                            </span>
                        @endif
                    </div>

                    @if ($user->pending_email)
                        <div class="mt-4 flex items-start justify-between gap-4 rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 p-4 text-[13px]">
                            <p><span class="font-semibold">Waiting for confirmation:</span> we sent a link to <span class="font-semibold">{{ $user->pending_email }}</span>. Your current email keeps working until you open it.</p>
                            <form method="POST" action="{{ route('platform.account.email.destroy') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="shrink-0 font-semibold text-brand-red hover:underline">Cancel</button>
                            </form>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('platform.account.email.store') }}" class="mt-5 space-y-4 border-t border-brand-ink/10 pt-5">
                        @csrf
                        <p class="text-[13px] text-brand-ink/60">Change email — we will send a confirmation link to the new address. Your password is required.</p>

                        <div>
                            <label for="email" class="{{ $label }}">New email address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off" class="{{ $input }}">
                            @error('email', 'emailChange')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                        </div>

                        <div x-data="{ show: false }">
                            <label for="current_password" class="{{ $label }}">Current password</label>
                            <div class="relative">
                                <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="{{ $input }} pr-16">
                                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 mt-[3px] -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="show ? 'Hide' : 'Show'">Show</button>
                            </div>
                            @error('current_password', 'emailChange')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                        </div>

                        <button type="submit" class="{{ $ghost }}">Send verification link</button>
                    </form>
                </x-platform.panel>
            </div>
        </div>
    </div>

    <x-platform.toast />
</x-platform-layout>
