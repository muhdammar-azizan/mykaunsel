<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Application Status — MyKaunsel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .wa-lapis { animation: masuk .4s cubic-bezier(.32,.72,0,1) both; }
        @keyframes masuk { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
        .wa-item { opacity: 0; transform: translateY(6px); animation: masuk .4s cubic-bezier(.32,.72,0,1) both; animation-delay: var(--tunda,0s); }
        .wa-ikon-denyut { animation: waDenyut 3s ease-in-out infinite; }
        @keyframes waDenyut { 0%,70%,100% { transform: scale(1); } 82% { transform: scale(1.08); } }
        .wa-spring { animation: sampul .45s cubic-bezier(.34,1.56,.64,1) both; }
        @keyframes sampul { from { opacity: 0; transform: scale(.7); } to { opacity: 1; transform: none; } }
        #waBar { transition: width 2s linear; }
        @media (prefers-reduced-motion: reduce) {
            .wa-lapis, .wa-item, .wa-spring { animation: none !important; opacity: 1 !important; transform: none !important; }
            .wa-ikon-denyut { animation: none; }
        }
    </style>
</head>
<body class="font-sans text-navy antialiased">

    <div class="relative flex min-h-screen flex-col overflow-hidden bg-cream">
        <img src="/images/placeholder.jpg" alt="" class="absolute inset-0 h-full w-full object-cover">
        <span class="pointer-events-none absolute inset-0" style="background:rgba(250,248,245,.62)"></span>

        <div class="relative z-10 flex items-start justify-between p-8">
            <x-mykaunsel-logo href="/" />
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-[13px] text-navy/50">{{ __('Log out') }}</button>
            </form>
        </div>

        <div class="relative z-10 flex flex-1 flex-col items-center justify-center px-5 pb-10">
            <div class="w-full max-w-[480px] rounded-2xl bg-cream p-8 text-center" style="box-shadow:0 30px 70px -22px rgba(14,42,51,.3)">

                @if ($status->value === 'pending')
                    <div id="waPending" class="wa-lapis w-full">
                        <span class="wa-ikon-denyut mx-auto grid h-16 w-16 place-items-center rounded-full" style="background:rgba(217,143,74,.10);color:#D98F4A">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 7v5l3.5 2"/></svg>
                        </span>
                        <h1 class="mt-6 text-[28px] font-semibold leading-[1.15] tracking-tightest md:text-[32px]">{{ __('Your organization is under review') }}</h1>
                        <p class="mx-auto mt-3 max-w-[38ch] text-[14.5px] leading-relaxed text-navy/60">
                            {{ __("We're reviewing your application for") }} <strong class="font-semibold text-navy">{{ $organization->name }}</strong>. {{ __('This usually takes 1-2 business days.') }}
                        </p>

                        <ul class="mt-7 flex flex-col gap-3 rounded-[14px] p-5 text-left" style="background:rgba(14,42,51,.04)">
                            <li class="wa-item flex items-center gap-3 text-[13.5px] text-navy/75" style="--tunda:0s">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0F6B7D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                {{ __('Organization details submitted') }}
                            </li>
                            @if ($organization->org_type->value !== 'clinic')
                                <li class="wa-item flex items-center gap-3 text-[13.5px] text-navy/75" style="--tunda:0.1s">
                                    @if ($domainsVerified)
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#0F6B7D" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7"/></svg>
                                        {{ __('Domain verified') }}
                                    @else
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#D98F4A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 7v5l3.5 2"/></svg>
                                        {{ __('Domain verification skipped') }}
                                    @endif
                                </li>
                            @endif
                            <li class="wa-item flex items-center gap-3 text-[13.5px] font-medium text-navy" style="--tunda:0.2s">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#D98F4A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 7v5l3.5 2"/></svg>
                                {{ __('Manual review by MyKaunsel team') }}
                            </li>
                        </ul>

                        @if ($organization->org_type->value !== 'clinic' && ! $hasLocation)
                            <p class="wa-item mt-4 flex items-start gap-2 text-left text-[12.5px] leading-relaxed text-navy/45" style="--tunda:0.3s">
                                <svg width="14" height="14" class="mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
                                <span>{{ __("Don't forget to add your location once you're approved — this helps members find in-person sessions.") }}</span>
                            </p>
                        @endif

                        <p class="mt-5 text-[13px] leading-relaxed text-navy/50">{{ __('We\'ll email you at') }} <strong class="font-semibold text-navy/70">{{ auth()->user()->email }}</strong> {{ __('once a decision is made.') }}</p>
                        <a href="mailto:support@mykaunsel.com" class="mt-4 inline-flex items-center gap-1.5 text-[13.5px] font-medium text-teal">{{ __('Questions? Contact support') }} <span aria-hidden="true">→</span></a>
                    </div>
                @elseif ($status->value === 'active')
                    <div id="waApproved" class="wa-lapis w-full">
                        <span class="wa-spring mx-auto grid h-16 w-16 place-items-center rounded-full" style="background:#0F6B7D;color:#FAF8F5">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 6 6L20 6"/></svg>
                        </span>
                        <h1 class="mt-6 text-[28px] font-semibold leading-[1.15] tracking-tightest">{{ __("You're approved!") }}</h1>
                        <p class="mx-auto mt-3 max-w-[38ch] text-[14.5px] leading-relaxed text-navy/60">{{ __('Welcome to MyKaunsel. Your organization is now active and ready to use.') }}</p>
                        <div class="mx-auto mt-6 h-[2px] w-full max-w-[220px] overflow-hidden rounded-full bg-navy/10">
                            <div id="waBar" class="h-full rounded-full" style="background:#0F6B7D;width:0%"></div>
                        </div>
                        <a href="{{ route('dashboard') }}" class="btn-utama mt-7 grid h-[46px] w-full place-items-center rounded-full text-[14px] font-medium text-white" style="background:#4A7DFF">{{ __('Go to dashboard') }}</a>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var bar = document.getElementById('waBar');
                            requestAnimationFrame(function () { bar.style.width = '100%'; });
                            setTimeout(function () { window.location.href = @json(route('dashboard')); }, 2200);
                        });
                    </script>
                @else
                    <div id="waRejected" class="wa-lapis w-full">
                        <span class="mx-auto grid h-16 w-16 place-items-center rounded-full" style="background:rgba(196,87,74,.10);color:#C4574A">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9.5"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                        </span>
                        <h1 class="mt-6 text-[28px] font-semibold leading-[1.15] tracking-tightest">{{ __('Your application needs changes') }}</h1>
                        <p class="mx-auto mt-3 max-w-[38ch] text-[14.5px] leading-relaxed text-navy/60">{{ __("We couldn't approve your organization at this time.") }}</p>

                        <div class="mt-6 rounded-xl p-4 text-left" style="background:rgba(196,87,74,.06);border-left:3px solid #C4574A">
                            <p class="text-[10.5px] font-semibold uppercase tracking-[0.14em]" style="color:#C4574A">{{ __('Reason from our team') }}</p>
                            <p class="mt-2 text-[13.5px] leading-relaxed text-navy">{{ $organization->rejection_reason ?: __('No specific reason was provided. Please contact support for more details.') }}</p>
                        </div>

                        <div class="mt-6 flex gap-3">
                            <a href="mailto:support@mykaunsel.com" class="grid h-[44px] flex-1 place-items-center rounded-full border border-navy/20 text-[13.5px] font-medium text-navy transition-all duration-200 hover:-translate-y-0.5 hover:border-navy hover:bg-navy/5">{{ __('Contact support') }}</a>
                            <a href="{{ route('organizations.signup.create') }}" class="btn-utama grid h-[44px] flex-1 place-items-center rounded-full text-[13.5px] font-medium text-white transition-all duration-200 hover:-translate-y-0.5" style="background:#4A7DFF">{{ __('Edit and resubmit') }}</a>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="mt-5">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 text-[13px] text-navy/50"><span aria-hidden="true">←</span> {{ __('Back to log in') }}</button>
                        </form>
                    </div>
                @endif

            </div>
        </div>
    </div>
</body>
</html>
