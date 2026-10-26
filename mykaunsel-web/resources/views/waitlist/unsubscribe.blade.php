<x-auth-centered-layout title="Stop these emails — MyKaunsel" content-max-width="520px">
    <div class="rounded-2xl border border-navy/10 bg-white/80 p-8 text-center shadow-sm">
        <h1 class="text-[22px] font-semibold tracking-tight">{{ __('Stop these emails?') }}</h1>
        <p class="mt-3 text-[14.5px] leading-relaxed text-navy/65">
            {{ __('You will not get an email when :word sign-up on MyKaunsel reopens. We keep only a scrambled code of your address, so we know not to add you again, and delete the address itself.', ['word' => $word]) }}
        </p>
        <form method="POST" action="{{ $action }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded-xl px-6 py-3 text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Yes, stop these emails') }}</button>
        </form>
        <p class="mt-4 text-[13px] text-navy/50">{{ __('Changed your mind? Just close this page.') }}</p>
    </div>
</x-auth-centered-layout>
