@php
    $input = 'mt-1.5 block w-full rounded-xl border border-brand-ink/15 bg-white px-4 py-2.5 text-[14px] text-brand-ink focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20';
    $label = 'block text-[12.5px] font-semibold text-brand-ink/70';
    $primary = 'inline-flex min-h-[42px] items-center justify-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-40';
    $danger = 'inline-flex min-h-[42px] items-center justify-center rounded-full bg-brand-red px-5 text-[13.5px] font-semibold text-white transition hover:opacity-90';
    $ghost = 'inline-flex min-h-[42px] items-center justify-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5';
    $codes = $twoFactor['recovery_codes'];
    $lowCodes = $twoFactor['enabled'] && $twoFactor['remaining_codes'] <= 2;
@endphp

<x-platform.panel title="Two-factor authentication" id="two-factor"
    x-data="{
        modal: @js(session('two_factor_modal')),
        actions: {
            enable: { url: @js(route('platform.account.two-factor.begin')), title: 'Turn on two-factor authentication', text: 'Enter your password to continue. You will then scan a QR code with an authenticator app.', button: 'Continue', danger: false },
            regenerate: { url: @js(route('platform.account.two-factor.recovery')), title: 'Generate new recovery codes?', text: 'Your old recovery codes will stop working. Enter your password to confirm.', button: 'Generate new codes', danger: false },
            disable: { url: @js(route('platform.account.two-factor.disable')), title: 'Turn off two-factor authentication?', text: 'Your account will only be protected by your password. Enter your password to confirm.', button: 'Turn off', danger: true },
        },
        showPwd: false,
        copied: false,
        codes: @js($codes ?? []),
        copy() { navigator.clipboard.writeText(this.codes.join('\n')).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); }); },
        download() {
            const blob = new Blob(['MyKaunsel recovery codes\n\n' + this.codes.join('\n') + '\n\nEach code works once. Keep this file somewhere safe.\n'], { type: 'text/plain' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'mykaunsel-recovery-codes.txt';
            a.click();
            URL.revokeObjectURL(a.href);
        },
    }">

    {{-- STATUS --}}
    <div class="mt-3 flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-[13px] text-brand-ink/60">Add a second step at sign-in using an authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy).</p>
            @if ($twoFactor['enabled'])
                <p class="mt-2 text-[12.5px] text-brand-ink/55">On since {{ $twoFactor['enabled_at'] }} ·
                    <span class="{{ $lowCodes ? 'font-semibold text-brand-red' : '' }}">{{ $twoFactor['remaining_codes'] }} recovery {{ str('code')->plural($twoFactor['remaining_codes']) }} left</span>
                </p>
            @endif
        </div>
        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $twoFactor['enabled'] ? 'bg-emerald-50 text-emerald-700' : 'bg-brand-ink/10 text-brand-ink/60' }}">
            {{ $twoFactor['enabled'] ? 'On' : ($twoFactor['pending'] ? 'Setting up' : 'Off') }}
        </span>
    </div>

    {{-- RECOVERY CODES (shown once, right after enabling / regenerating) --}}
    @if ($codes)
        <div class="mt-5 rounded-xl border border-brand-yellow/60 bg-brand-yellow/10 p-4">
            <p class="text-[13.5px] font-bold">Save your recovery codes</p>
            <p class="mt-1 text-[12.5px] text-brand-ink/65">If you lose your phone, each code lets you sign in once. <span class="font-semibold">You will not see them again</span> — store them somewhere safe.</p>
            <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-[13.5px]">
                @foreach ($codes as $code)
                    <li class="rounded-lg bg-white px-3 py-2 text-center tracking-wide">{{ $code }}</li>
                @endforeach
            </ul>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" @click="copy()" class="{{ $ghost }}"><span x-text="copied ? 'Copied ✓' : 'Copy codes'">Copy codes</span></button>
                <button type="button" @click="download()" class="{{ $ghost }}">Download .txt</button>
            </div>
        </div>
    @endif

    {{-- SETUP IN PROGRESS --}}
    @if ($twoFactor['pending'])
        <div class="mt-5 rounded-xl border border-brand-ink/10 p-4">
            <ol class="space-y-5 text-[13px]">
                <li>
                    <p class="font-semibold">1. Scan this QR code with your authenticator app</p>
                    <div class="mt-3 inline-block rounded-xl border border-brand-ink/10 bg-white p-2 [&>svg]:block [&>svg]:h-[176px] [&>svg]:w-[176px]">{!! $twoFactor['qr'] !!}</div>
                </li>
                <li>
                    <p class="font-semibold">Can't scan? Enter this key manually</p>
                    <p class="mt-1.5 flex flex-wrap items-center gap-2">
                        <code class="rounded-lg bg-brand-ink/5 px-3 py-1.5 font-mono text-[13px] tracking-wider">{{ $twoFactor['manual_key'] }}</code>
                        <button type="button" class="text-[12.5px] font-semibold text-brand-blue" @click="navigator.clipboard.writeText(@js(str_replace(' ', '', $twoFactor['manual_key'])))">Copy</button>
                    </p>
                </li>
                <li>
                    <form method="POST" action="{{ route('platform.account.two-factor.confirm') }}">
                        @csrf
                        <label for="tf_code" class="font-semibold">2. Enter the 6-digit code from the app</label>
                        <input id="tf_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required placeholder="123456"
                               class="{{ $input }} max-w-[220px] text-center text-[18px] tracking-[.3em]">
                        @error('code', 'twoFactorCode')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="{{ $primary }}">Confirm and turn on</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('platform.account.two-factor.cancel') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-[12.5px] font-semibold text-brand-ink/55 hover:text-brand-ink">Cancel setup</button>
                    </form>
                </li>
            </ol>
        </div>
    @endif

    {{-- ACTIONS --}}
    <div class="mt-5 flex flex-wrap gap-2">
        @if ($twoFactor['enabled'])
            <button type="button" class="{{ $ghost }}" @click="modal = 'regenerate'; showPwd = false">Regenerate recovery codes</button>
            <button type="button" class="{{ $ghost }} !border-brand-red/40 text-brand-red hover:!bg-brand-red/10" @click="modal = 'disable'; showPwd = false">Turn off</button>
        @elseif (! $twoFactor['pending'])
            <button type="button" class="{{ $primary }}" @click="modal = 'enable'; showPwd = false">Turn on two-factor authentication</button>
        @endif
    </div>

    {{-- PASSWORD CONFIRMATION MODAL (shared by enable / regenerate / disable) --}}
    <div x-show="modal" x-cloak class="fixed inset-0 z-[95] flex items-center justify-center px-4" role="dialog" aria-modal="true" @keydown.escape.window="modal = null">
        <div x-show="modal" x-transition.opacity @click="modal = null" class="absolute inset-0 bg-brand-deep/50 backdrop-blur-sm"></div>
        <form method="POST" :action="modal ? actions[modal].url : '#'" x-show="modal"
              x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="scale-95 opacity-0" x-transition:enter-end="scale-100 opacity-100"
              class="relative w-full max-w-[420px] rounded-2xl bg-white p-6 shadow-2xl">
            @csrf
            <h3 class="text-[17px] font-bold" x-text="modal ? actions[modal].title : ''"></h3>
            <p class="mt-1 text-[13px] text-brand-ink/60" x-text="modal ? actions[modal].text : ''"></p>

            <label for="tf_password" class="{{ $label }} mt-4">Current password</label>
            <div class="relative">
                <input id="tf_password" name="current_password" :type="showPwd ? 'text' : 'password'" required autocomplete="current-password" class="{{ $input }} pr-16"
                       x-effect="if (modal) $nextTick(() => $el.focus())">
                <button type="button" @click="showPwd = !showPwd" class="absolute right-3 top-1/2 mt-[3px] -translate-y-1/2 text-[12px] font-semibold text-brand-blue" x-text="showPwd ? 'Hide' : 'Show'">Show</button>
            </div>
            @error('current_password', 'twoFactor')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red">{{ $message }}</p>@enderror

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="modal = null" class="{{ $ghost }}">Cancel</button>
                <button type="submit" :class="modal && actions[modal].danger ? '{{ $danger }}' : '{{ $primary }}'" x-text="modal ? actions[modal].button : ''"></button>
            </div>
        </form>
    </div>
</x-platform.panel>
