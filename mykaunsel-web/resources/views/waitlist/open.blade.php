<x-auth-centered-layout title="Sign-up is open — MyKaunsel" content-max-width="520px">
    <div class="rounded-2xl border border-navy/10 bg-white/80 p-8 text-center shadow-sm">
        <h1 class="text-[22px] font-semibold tracking-tight">{{ __('Good news: sign-up is open') }}</h1>
        <p class="mt-3 text-[14.5px] leading-relaxed text-navy/65">{{ __(':Word sign-up has just opened, so there is no need to wait. Your email was not saved.', ['word' => $word]) }}</p>
        <a href="{{ $signUp }}" class="mt-6 inline-block rounded-xl px-6 py-3 text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Go to sign-up') }}</a>
    </div>
</x-auth-centered-layout>
