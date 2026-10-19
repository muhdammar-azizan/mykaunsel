@push('styles')
    <style>
        .tf-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .tf-lapis { animation: none; opacity: 1; transform: none; } }
    </style>
@endpush

<x-auth-simple-layout title="Two-factor authentication — MyKaunsel" content-max-width="380px">
    <div class="tf-lapis" x-data="{ recovery: @js($errors->has('recovery_code')) }">
        <span class="grid h-[52px] w-[52px] place-items-center rounded-full" style="background:rgba(10,79,136,.1);color:#0A4F88">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 13c0 5-3.5 7.5-7.5 8.5a1.2 1.2 0 0 1-1 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.79 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
        </span>

        <h1 class="mt-6 text-[26px] font-semibold leading-[1.15] tracking-tightest md:text-[30px]">Two-factor authentication</h1>
        <p class="mt-2.5 text-[14px] leading-relaxed text-navy/55" x-show="!recovery">Enter the 6-digit code from your authenticator app.</p>
        <p class="mt-2.5 text-[14px] leading-relaxed text-navy/55" x-show="recovery" x-cloak>Enter one of your recovery codes. Each code works only once.</p>

        <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="mt-7" novalidate>
            @csrf

            <label class="block" x-show="!recovery">
                <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">Authentication code</span>
                <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" autofocus
                       class="medan text-center text-[20px] tracking-[.35em]" @if ($errors->has('code')) aria-invalid="true" @endif>
                @error('code')<p class="mt-2 text-[12.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
            </label>

            <label class="block" x-show="recovery" x-cloak>
                <span class="mb-1.5 block text-[13.5px] font-medium text-navy/75">Recovery code</span>
                <input type="text" name="recovery_code" autocomplete="off" placeholder="xxxxx-xxxxx" :disabled="!recovery"
                       class="medan text-center font-mono" @if ($errors->has('recovery_code')) aria-invalid="true" @endif>
                @error('recovery_code')<p class="mt-2 text-[12.5px]" style="color:#C4574A">{{ $message }}</p>@enderror
            </label>

            <button type="submit" class="btn-utama mt-6 grid h-[44px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">Verify</button>
        </form>

        <button type="button" class="mt-5 text-[13px] font-medium" style="color:#0F6B7D" @click="recovery = !recovery"
                x-text="recovery ? 'Use authenticator code instead' : 'Use a recovery code instead'"></button>

        <a href="{{ route('login') }}" class="mt-3 block text-[13px] text-navy/55 hover:text-navy">← Back to sign in</a>
    </div>
</x-auth-simple-layout>
