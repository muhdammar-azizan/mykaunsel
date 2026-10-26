@props(['type'])

{{--
    "Tell me when sign-up reopens": the public waitlist form on a closed sign-up page (docs/WAITLIST.md). The consent line says
    what the address is used for. The hidden field is a honeypot: people never see it, bots fill it in. The page that shows this
    is sent with no-store, so the CSRF token in the form is never an old cached one.
--}}
@php
    $word = config("platform-settings.registration_roles.{$type}.word", 'account');
    $bag = $errors->getBag('waitlist');
@endphp

<form method="POST" action="{{ route('waitlist.store', $type) }}" class="relative mt-6 text-left">
    @csrf
    <label for="waitlist-email-{{ $type }}" class="block text-[13px] font-medium text-navy/70">{{ __('Email me when :word sign-up reopens', ['word' => $word]) }}</label>
    <div class="mt-2 flex gap-2">
        <input id="waitlist-email-{{ $type }}" type="email" name="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" inputmode="email"
               aria-describedby="waitlist-consent-{{ $type }}" @if ($bag->has('email')) aria-invalid="true" @endif
               class="min-w-0 flex-1 rounded-xl border border-navy/15 bg-white px-3.5 py-2.5 text-[14px] focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/20">
        <button type="submit" class="shrink-0 rounded-xl px-4 py-2.5 text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Notify me') }}</button>
    </div>
    @if ($bag->has('email'))
        <p class="mt-1.5 text-[12.5px] font-medium text-[#C4574A]" role="alert">{{ $bag->first('email') }}</p>
    @endif

    {{-- Honeypot: off screen, skipped by the keyboard and by screen readers. --}}
    <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">
        <label>{{ __('Leave this field empty') }} <input type="text" name="company_website" tabindex="-1" autocomplete="off" value=""></label>
    </div>

    <p id="waitlist-consent-{{ $type }}" class="mt-2 text-[12px] leading-relaxed text-navy/55">
        {{ __('We will email you once, only when :word sign-up reopens. We do not share your email, and you can opt out in that email.', ['word' => $word]) }}
        <a href="{{ config('waitlist.privacy_url') }}" class="underline">{{ __('Privacy Policy') }}</a>
    </p>
</form>
