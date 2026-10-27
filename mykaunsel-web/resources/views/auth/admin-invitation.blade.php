<x-auth-simple-layout title="Platform Admin invitation — MyKaunsel" content-max-width="440px">
    <h1 class="text-[26px] font-semibold leading-[1.15] tracking-tightest md:text-[30px]">{{ __('Accept your invitation') }}</h1>
    <p class="mt-2.5 text-[14px] leading-relaxed text-navy/60">
        {{ $sender ? __(':name invited you', ['name' => $sender]) : __('You were invited') }} {{ __('to be a Platform Admin on MyKaunsel. Platform Admins can read complaints and counselor documents.') }}
    </p>

    <dl class="mt-5 rounded-xl border border-navy/10 bg-white px-4 py-3 text-[13.5px]">
        <div class="flex justify-between gap-4 py-1"><dt class="text-navy/55">{{ __('Name') }}</dt><dd class="font-semibold">{{ $invitation->name }}</dd></div>
        <div class="flex justify-between gap-4 py-1"><dt class="text-navy/55">{{ __('Email') }}</dt><dd class="break-all font-semibold">{{ $invitation->email }}</dd></div>
    </dl>

    @if ($mustSignIn)
        <div class="mt-6 rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 px-4 py-3 text-[13.5px] leading-snug">
            {{ __('This account uses two-factor authentication. Sign in first, then open the link from the email again.') }}
        </div>
        <a href="{{ route('login') }}" class="btn-utama mt-5 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Sign in') }}</a>
    @else
        <form method="POST" action="{{ $action }}" class="mt-6" x-data="{ ready: {{ $mode === 'verified' ? 'true' : 'false' }}, busy: false }" @submit="busy = true">
            @csrf

            @if ($mode === 'verified')
                <p class="text-[13.5px] leading-snug text-navy/70">{{ __('You already have a MyKaunsel account with this email. Type its password to confirm it is you.') }}</p>
                <label for="password" class="mt-4 block text-[12.5px] font-semibold text-navy/70">{{ __('Your password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" @disabled($locked)
                       class="mt-1.5 block w-full rounded-xl border border-navy/15 bg-white px-4 py-2.5 text-[14px] focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20">
                @error('password')<p class="mt-1.5 text-[12.5px] font-medium text-brand-red" role="alert">{{ $message }}</p>@enderror
                <p class="mt-2 text-[12.5px] text-navy/55">{{ __('Never set a password (for example you signed up another way)? Use "Forgot password" on the sign-in page first, then open this link again.') }}</p>
            @else
                @if ($mode === 'unverified')
                    <div class="mb-4 rounded-xl border border-brand-yellow/50 bg-brand-yellow/10 px-4 py-3 text-[13px] leading-snug">
                        {{ __('This email has an account that was never verified. To keep it safe you must choose a NEW password now: the old password stops working and any device signed in with it is signed out.') }}
                    </div>
                @else
                    <p class="mb-4 text-[13.5px] leading-snug text-navy/70">{{ __('Choose a password to create your account.') }}</p>
                @endif
                <x-password-fields x-model="ready" />
            @endif

            <button type="submit" class="btn-utama mt-6 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF"
                    :disabled="!ready || busy" @disabled($locked)>
                <span x-show="!busy">{{ __('Accept and become a Platform Admin') }}</span><span x-show="busy" x-cloak>{{ __('Please wait…') }}</span>
            </button>
            @if ($locked)
                <p class="mt-3 text-center text-[12.5px] font-medium text-brand-red" role="alert">{{ __('Too many wrong passwords. Try again in a few minutes.') }}</p>
            @endif
        </form>
    @endif

    <p class="mt-6 text-center text-[12.5px] text-navy/50">{{ __('If you were not expecting this, close this page. Nothing changes unless you accept.') }}</p>
</x-auth-simple-layout>
