@php
    // The main page of the public website (docs/LANDING_PAGE.md): the eleven sections in order. Words: lang/{ms,en}/landing.php. Pictures and videos: public/landing (README there).
    use App\Support\LandingLinks as Links;

    $appUrl = Links::url('get-the-app');
    $settings = app(\App\Services\PlatformSettings::class);
    $waitlist = app(\App\Services\WaitlistService::class);

    // Individuals use the app and never "sign up" on the web. An organization or a counselor signs up while that door is open; when it is closed, the waitlist (if there is one).
    $door = function (string $role) use ($settings, $waitlist) {
        $open = $settings->registrationOpen($role);

        return [
            'url' => $open ? route($role === 'organization' ? 'organizations.signup.create' : 'counselors.signup.create') : route('register.type'),
            'waitlist' => ! $open && $waitlist->offered($role),
        ];
    };
    $doors = ['organization' => $door('organization'), 'counselor' => $door('counselor')];
    $paths = __('landing.paths');

    $icons = [
        'shield' => '<path d="M12 3 5 6v5c0 4.500 3 8 7 10 4-2 7-5.500 7-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'tap' => '<path d="M9 11V6a2 2 0 1 1 4 0v5"/><path d="M13 10.500a2 2 0 0 1 4 0V14a6 6 0 0 1-6 6h-1a6 6 0 0 1-5-3l-1.500-3a1.500 1.500 0 0 1 2.600-1.500L9 14"/>',
        'mic-off' => '<path d="M3 3l18 18"/><path d="M9 9v2a3 3 0 0 0 5 2.200M15 9.300V6a3 3 0 0 0-5.800-1"/><path d="M5 11a7 7 0 0 0 11 5.700M19 11a7 7 0 0 1-.600 2.800M12 18v3"/>',
        'check' => '<circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><path d="m7.500 12.300 3 3 6-6.600" stroke="#fff"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    $icon = fn (string $name, int $size = 22) => '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$name].'</svg>';

    $section = 'border-b border-[#E6E9F0] px-5 py-16 lg:px-12 lg:py-24';
    $h2 = 'lp-h text-[30px] font-normal sm:text-[38px] lg:text-[44px]';
    $soft = 'text-[#6F7E9C]';
    $link = 'inline-flex items-center gap-1 text-[15px] font-medium text-brand-blue transition hover:text-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue rounded';
@endphp

<x-landing-layout>

    {{-- 2 ============================ HERO ============================ --}}
    <section class="relative overflow-hidden border-b border-[#E6E9F0]" aria-labelledby="hero-title">
        <div class="pointer-events-none absolute -right-32 -top-32 h-[620px] w-[620px] rounded-full opacity-70 blur-3xl" aria-hidden="true"
             style="background: radial-gradient(circle at 30% 30%, #FFD88F, transparent 55%), radial-gradient(circle at 72% 58%, #9DBBFF, transparent 56%), radial-gradient(circle at 48% 92%, #FFA3AF, transparent 52%)"></div>

        <div class="relative grid items-center gap-12 px-5 pb-14 pt-14 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,.95fr)] lg:px-12 lg:pb-20 lg:pt-24">
            <div>
                <h1 id="hero-title" class="lp-h text-[40px] font-normal sm:text-[52px] lg:text-[64px]">
                    <span class="font-medium">{{ __('landing.hero.strong') }}</span>
                    <span class="{{ $soft }}">{{ __('landing.hero.soft') }}</span>
                </h1>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a href="{{ $appUrl }}" class="inline-flex min-h-[46px] items-center gap-2 rounded-md bg-brand-blue px-6 text-[15px] font-medium text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">{{ __('landing.nav.get_app') }} <span aria-hidden="true">›</span></a>
                    @foreach (['app_store', 'google_play'] as $store)
                        <span class="inline-flex min-h-[46px] items-center gap-2.5 rounded-md border border-[#D9DEE9] bg-white/80 px-4 text-[14px] font-medium text-brand-ink/70" aria-disabled="true">
                            @if ($store === 'app_store')
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.400 12.600c0-2.400 2-3.500 2.100-3.600-1.100-1.700-2.900-1.900-3.500-1.900-1.500-.2-2.900.9-3.700.9s-1.900-.9-3.200-.8c-1.600 0-3.100 1-4 2.400-1.700 3-.4 7.400 1.200 9.800.8 1.200 1.800 2.500 3 2.400 1.200 0 1.700-.8 3.100-.8s1.800.8 3.100.8 2.100-1.200 2.900-2.400c.9-1.300 1.300-2.600 1.300-2.700 0 0-2.300-.9-2.300-3.600zM14.100 5.600c.6-.8 1.100-1.900.9-3-.9 0-2.100.6-2.700 1.400-.6.700-1.100 1.800-1 2.900 1.100.1 2.200-.5 2.800-1.300z"/></svg>
                            @else
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3.500v17c0 .5.600.8 1 .5l14-8.500c.4-.3.400-.8 0-1l-14-8.500c-.4-.2-1 0-1 .5z"/></svg>
                            @endif
                            <span class="leading-tight">{{ __('landing.hero.'.$store) }}<span class="block text-[11px] font-normal text-brand-ink/50">{{ __('landing.hero.badge_soon') }}</span></span>
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- Picture or video: the app, or people using it --}}
            <x-landing.media slot_name="hero" ratio="1 / 1" kind="video" label="Video atau gambar utama" size="1400 × 1400 (1:1)" :alt="__('landing.hero.media_alt')" class="shadow-[0_40px_80px_-40px_rgba(27,42,78,.45)] ring-1 ring-black/5 lg:ml-auto lg:w-full lg:max-w-[520px]" />
        </div>

        {{-- The three paths --}}
        <div class="relative grid border-t border-[#E6E9F0] bg-white/70 backdrop-blur-sm md:grid-cols-3 md:divide-x md:divide-[#E6E9F0]">
            @foreach (['individual', 'organization', 'counselor'] as $key)
                @php($p = $paths[$key])
                @php($closed = $key !== 'individual' && $doors[$key]['waitlist'])
                <div class="border-b border-[#E6E9F0] px-5 py-8 last:border-b-0 md:border-b-0 lg:px-8">
                    <p class="text-[12.5px] font-semibold uppercase tracking-[0.12em] text-brand-ink/50">{{ $p['label'] }}</p>
                    <p class="mt-2 text-[20px] font-medium tracking-tight">{{ $p['title'] }}</p>
                    <p class="mt-1.5 max-w-[34ch] text-[14.5px] leading-relaxed text-brand-ink/65">{{ $p['text'] }}</p>
                    <a href="{{ $key === 'individual' ? $appUrl : $doors[$key]['url'] }}" class="{{ $link }} mt-4">{{ $closed ? $p['cta_closed'] : $p['cta'] }} <span aria-hidden="true">›</span></a>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 3 ============================ SOCIAL PROOF ============================ --}}
    <section class="{{ $section }}" aria-labelledby="proof-title">
        <h2 id="proof-title" class="{{ $h2 }} max-w-[22ch]"><span class="font-medium">{{ __('landing.proof.strong') }}</span> <span class="{{ $soft }}">{{ __('landing.proof.soft') }}</span></h2>

        {{-- Block A: always shown, true --}}
        <div class="mt-12 grid gap-px overflow-hidden rounded-xl border border-[#E6E9F0] bg-[#E6E9F0] md:grid-cols-3">
            @foreach (__('landing.proof.assurances') as $i => $a)
                <div class="bg-white p-7">
                    <span class="grid h-11 w-11 place-items-center rounded-lg bg-brand-blue/[.08] text-brand-blue">{!! $icon(['shield', 'tap', 'mic-off'][$i]) !!}</span>
                    <p class="mt-5 text-[17px] font-medium tracking-tight">{{ $a['title'] }}</p>
                    <p class="mt-1.5 text-[14.5px] leading-relaxed text-brand-ink/65">{{ $a['text'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Block B: invented placeholders (config/landing.php), gone when the switch is off --}}
        @if (Links::shows('proof_logos'))
            <div class="mt-12" data-placeholder="proof_logos">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[13.5px] font-medium text-brand-ink/60">{{ __('landing.proof.logos_label') }}</p>
                    <span class="rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11px] font-semibold text-[#6B4C0C]">{{ __('landing.proof.placeholder_note') }}</span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="grid h-16 place-items-center rounded-lg border border-dashed border-brand-ink/20 text-[12px] font-semibold uppercase tracking-[0.14em] text-brand-ink/35">{{ __('landing.proof.logo') }}</div>
                    @endfor
                </div>
            </div>
        @endif

        @if (Links::shows('proof_quotes'))
            <div class="mt-12 grid gap-5 md:grid-cols-2" data-placeholder="proof_quotes">
                @foreach (__('landing.proof.quotes') as $quote)
                    <figure class="rounded-xl border border-[#E6E9F0] bg-[#F7F9FD] p-7">
                        <blockquote class="text-[19px] leading-snug tracking-tight">“{{ $quote['text'] }}”</blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full border border-dashed border-brand-ink/25 text-[10px] font-semibold uppercase tracking-wide text-brand-ink/40">Foto</span>
                            <span class="text-[14px]"><span class="block font-medium">{{ $quote['name'] }}</span><span class="text-brand-ink/60">{{ $quote['role'] }}</span></span>
                            <span class="ml-auto rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11px] font-semibold text-[#6B4C0C]">{{ __('landing.proof.placeholder_note') }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        @if (Links::shows('proof_figures'))
            <div class="mt-12 grid divide-y divide-[#E6E9F0] border-y border-[#E6E9F0] text-center sm:grid-cols-3 sm:divide-x sm:divide-y-0" data-placeholder="proof_figures">
                @foreach (__('landing.proof.figures') as [$value, $label])
                    <div class="px-4 py-9">
                        <p class="lp-tabular text-[44px] font-light leading-none tracking-tight text-brand-ink/80">{{ $value }}</p>
                        <p class="mt-2 text-[14px] text-brand-ink/55">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- 4 ============================ PROBLEM (placeholder copy) ============================ --}}
    @if (Links::shows('problem'))
        <section class="{{ $section }} bg-[#F7F9FD]" aria-labelledby="problem-title" data-placeholder="problem">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h2 id="problem-title" class="{{ $h2 }} max-w-[22ch]"><span class="font-medium">{{ __('landing.problem.strong') }}</span> <span class="{{ $soft }}">{{ __('landing.problem.soft') }}</span></h2>
                <span class="rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-[11px] font-semibold text-[#6B4C0C]">{{ __('landing.proof.placeholder_note') }}</span>
            </div>
            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @foreach (__('landing.problem.cards') as $i => $card)
                    <div class="rounded-xl border border-[#E6E9F0] bg-white p-7">
                        <p class="lp-tabular text-[13px] font-semibold text-brand-blue">0{{ $i + 1 }}</p>
                        <p class="mt-4 text-[19px] font-medium leading-snug tracking-tight">{{ $card['title'] }}</p>
                        <p class="mt-2 text-[14.5px] leading-relaxed text-brand-ink/65">{{ $card['text'] }}</p>
                    </div>
                @endforeach
            </div>
            <a href="{{ Links::url('about') }}" class="{{ $link }} mt-8">{{ __('landing.problem.learn_more') }} <span aria-hidden="true">›</span></a>
        </section>
    @endif

    {{-- 5 ============================ SOLUTION ============================ --}}
    <section class="{{ $section }}" aria-labelledby="solution-title">
        <h2 id="solution-title" class="{{ $h2 }} max-w-[26ch]"><span class="font-medium">{{ __('landing.solution.strong') }}</span> <span class="{{ $soft }}">{{ __('landing.solution.soft') }}</span></h2>
        <div class="mt-12 grid gap-8 md:grid-cols-3">
            @foreach (__('landing.solution.pillars') as $pillar)
                <article>
                    <x-landing.media :slot_name="$pillar['media']" ratio="4 / 3" kind="image" label="Gambar" size="1200 × 900 (4:3)" />
                    <p class="mt-5 text-[16px] leading-snug"><span class="font-semibold">{{ $pillar['title'] }}</span> <span class="text-brand-ink/65">{{ $pillar['text'] }}</span></p>
                    <a href="{{ Links::url($pillar['slug']) }}" class="{{ $link }} mt-4">{{ $pillar['cta'] }} <span aria-hidden="true">›</span></a>
                </article>
            @endforeach
        </div>
    </section>

    {{-- 6 ============================ HOW IT WORKS ============================ --}}
    <section class="{{ $section }} bg-[#F7F9FD]" aria-labelledby="how-title">
        <div class="grid items-center gap-12 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)]">
            <div>
                <h2 id="how-title" class="{{ $h2 }} max-w-[20ch]"><span class="font-medium">{{ __('landing.how.strong') }}</span> <span class="{{ $soft }}">{{ __('landing.how.soft') }}</span></h2>
                <ol class="mt-10 space-y-6">
                    @foreach (__('landing.how.steps') as $i => $step)
                        <li class="flex gap-5">
                            <span class="lp-tabular grid h-9 w-9 shrink-0 place-items-center rounded-full border border-brand-blue/30 bg-white text-[14px] font-semibold text-brand-blue">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-[18px] font-medium tracking-tight">{{ $step['title'] }}</p>
                                <p class="mt-1 max-w-[38ch] text-[14.5px] leading-relaxed text-brand-ink/65">{{ $step['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <a href="{{ Links::url('how-it-works') }}" class="{{ $link }} mt-9">{{ __('landing.how.cta') }} <span aria-hidden="true">›</span></a>
            </div>
            <x-landing.media slot_name="how-it-works" ratio="16 / 10" kind="video" label="Video demonstrasi" size="1600 × 1000 (16:10)" class="shadow-[0_30px_60px_-36px_rgba(27,42,78,.4)] ring-1 ring-black/5" />
        </div>
    </section>

    {{-- 7 ============================ FOR ORGANIZATIONS / FOR COUNSELORS ============================ --}}
    <section class="grid border-b border-[#E6E9F0] md:grid-cols-2 md:divide-x md:divide-[#E6E9F0]" aria-label="{{ __('landing.audiences.org.label') }}, {{ __('landing.audiences.counselor.label') }}">
        @foreach (['org' => 'organization', 'counselor' => 'counselor'] as $key => $role)
            @php($a = __('landing.audiences.'.$key))
            <div class="border-b border-[#E6E9F0] px-5 py-14 last:border-b-0 md:border-b-0 lg:px-12 lg:py-20">
                <p class="text-[12.5px] font-semibold uppercase tracking-[0.12em] text-brand-ink/50">{{ $a['label'] }}</p>
                <h2 class="lp-h mt-3 text-[28px] font-normal sm:text-[34px]"><span class="font-medium">{{ $a['strong'] }}</span> <span class="{{ $soft }}">{{ $a['soft'] }}</span></h2>
                <x-landing.media :slot_name="'audience-'.$key" ratio="16 / 10" kind="image" label="Gambar" size="1600 × 1000 (16:10)" class="mt-8" />
                <ul class="mt-8 space-y-3">
                    @foreach ($a['benefits'] as $benefit)
                        <li class="flex items-start gap-2.5 text-[15px]"><span class="mt-0.5 shrink-0 text-emerald-500">{!! $icon('check', 18) !!}</span><span>{{ $benefit }}</span></li>
                    @endforeach
                </ul>
                <div class="mt-9 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <a href="{{ $doors[$role]['url'] }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-brand-blue px-5 text-[14.5px] font-medium text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2">{{ $doors[$role]['waitlist'] ? $a['cta_closed'] : $a['cta'] }} <span aria-hidden="true">›</span></a>
                    <a href="{{ Links::url($key === 'org' ? 'for-organizations' : 'for-counselors') }}" class="{{ $link }}">{{ $a['learn'] }} <span aria-hidden="true">›</span></a>
                </div>
            </div>
        @endforeach
    </section>

    {{-- 8 ============================ GET THE APP (the dark section) ============================ --}}
    <section id="get-the-app" class="border-b border-[#E6E9F0] bg-[#0F1A38] px-5 py-16 text-white lg:px-12 lg:py-24" aria-labelledby="app-title">
        <div class="grid items-center gap-14 lg:grid-cols-[minmax(0,1fr)_minmax(0,.8fr)]">
            <div>
                <h2 id="app-title" class="lp-h max-w-[18ch] text-[32px] font-normal sm:text-[42px] lg:text-[50px]"><span class="font-medium">{{ __('landing.app.strong') }}</span> <span class="text-[#8EA2D1]">{{ __('landing.app.soft') }}</span></h2>
                <div class="mt-9 flex flex-wrap gap-3">
                    @foreach (['app_store', 'google_play'] as $store)
                        <span class="inline-flex min-h-[48px] items-center gap-2.5 rounded-md border border-white/20 bg-white/[.06] px-4 text-[14px] font-medium text-white/85" aria-disabled="true">
                            @if ($store === 'app_store')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16.400 12.600c0-2.400 2-3.500 2.100-3.600-1.100-1.700-2.900-1.900-3.500-1.900-1.500-.2-2.900.9-3.700.9s-1.900-.9-3.200-.8c-1.600 0-3.100 1-4 2.400-1.700 3-.4 7.400 1.200 9.800.8 1.200 1.800 2.500 3 2.400 1.200 0 1.700-.8 3.100-.8s1.800.8 3.100.8 2.100-1.200 2.900-2.400c.9-1.300 1.300-2.600 1.300-2.700 0 0-2.300-.9-2.300-3.600zM14.100 5.600c.6-.8 1.100-1.900.9-3-.9 0-2.100.6-2.700 1.400-.6.700-1.100 1.800-1 2.900 1.100.1 2.200-.5 2.800-1.300z"/></svg>
                            @else
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 3.500v17c0 .5.600.8 1 .5l14-8.500c.4-.3.400-.8 0-1l-14-8.500c-.4-.2-1 0-1 .5z"/></svg>
                            @endif
                            <span class="leading-tight">{{ __('landing.hero.'.$store) }}<span class="block text-[11px] font-normal text-white/55">{{ __('landing.hero.badge_soon') }}</span></span>
                        </span>
                    @endforeach
                </div>
                <div class="mt-10 flex items-center gap-5">
                    <x-landing.media slot_name="app-qr" ratio="1 / 1" kind="image" label="{{ __('landing.app.qr_label') }}" size="400 × 400" rounded="rounded-lg" class="w-[116px] shrink-0 bg-white" />
                    <p class="max-w-[22ch] text-[14.5px] leading-snug text-white/65">{{ __('landing.app.scan') }}</p>
                </div>
            </div>

            {{-- The phone: the app's screen goes inside --}}
            <div class="mx-auto w-full max-w-[290px]">
                <div class="rounded-[44px] bg-[#1E2C54] p-3 shadow-[0_40px_80px_-30px_rgba(0,0,0,.7)] ring-1 ring-white/15">
                    <x-landing.media slot_name="app-screen" ratio="9 / 19" kind="image" label="Skrin aplikasi" size="1080 × 2280 (9:19)" :alt="__('landing.app.phone_alt')" rounded="rounded-[34px]" class="bg-[#EEF2FA]" />
                </div>
            </div>
        </div>
    </section>

    {{-- 9 ============================ FAQ ============================ --}}
    <section class="{{ $section }}" aria-labelledby="faq-title">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,.7fr)_minmax(0,1.3fr)]">
            <div>
                <h2 id="faq-title" class="{{ $h2 }}"><span class="font-medium">{{ __('landing.faq.title') }}</span></h2>
                <a href="{{ Links::url('faq-help') }}" class="{{ $link }} mt-6">{{ __('landing.faq.cta') }} <span aria-hidden="true">›</span></a>
            </div>
            <div class="divide-y divide-[#E6E9F0] border-y border-[#E6E9F0]">
                @foreach (__('landing.faq.items') as $i => $item)
                    <div x-data="{ open: false }">
                        <h3>
                            <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="faq-{{ $i }}" class="flex w-full items-center justify-between gap-6 py-5 text-left text-[17px] font-medium tracking-tight focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
                                <span>{{ $item['q'] }}</span>
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-[#D9DEE9] text-brand-blue transition-transform" :class="open ? 'rotate-45' : ''" aria-hidden="true">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </button>
                        </h3>
                        <div id="faq-{{ $i }}" class="lp-faq" :class="open ? 'is-open' : ''" role="region">
                            <div class="overflow-hidden"><p class="pb-5 pr-10 text-[15px] leading-relaxed text-brand-ink/65">{{ $item['a'] }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 10 ============================ FINAL CALL TO ACTION ============================ --}}
    <section class="relative overflow-hidden bg-[#F7F9FD]" aria-labelledby="cta-title">
        <div class="px-5 pb-10 pt-16 lg:px-12 lg:pt-24">
            <h2 id="cta-title" class="{{ $h2 }} max-w-[20ch]"><span class="font-medium">{{ __('landing.cta.strong') }}</span> <span class="{{ $soft }}">{{ __('landing.cta.soft') }}</span></h2>
        </div>
        <div class="grid border-t border-[#E6E9F0] bg-white md:grid-cols-3 md:divide-x md:divide-[#E6E9F0]">
            @foreach (['individual', 'organization', 'counselor'] as $key)
                @php($p = $paths[$key])
                @php($closed = $key !== 'individual' && $doors[$key]['waitlist'])
                <a href="{{ $key === 'individual' ? $appUrl : $doors[$key]['url'] }}" class="group flex items-center justify-between gap-4 border-b border-[#E6E9F0] px-5 py-7 transition hover:bg-[#F7F9FD] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue last:border-b-0 md:border-b-0 lg:px-8">
                    <span>
                        <span class="block text-[12.5px] font-semibold uppercase tracking-[0.12em] text-brand-ink/50">{{ $p['label'] }}</span>
                        <span class="mt-1 block text-[17px] font-medium tracking-tight">{{ $closed ? $p['cta_closed'] : $p['cta'] }}</span>
                    </span>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-blue text-white transition group-hover:translate-x-0.5">{!! $icon('arrow', 18) !!}</span>
                </a>
            @endforeach
        </div>
    </section>
</x-landing-layout>
