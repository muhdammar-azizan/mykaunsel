@props(['title' => null])

{{-- The public website around a page: head, sticky header with the five menus, the page in its framed column, the urgent help strip and the footer.
     Words are in lang/{ms,en}/landing.php; where a link goes is in App\Support\LandingLinks. Structure: docs/LANDING_PAGE.md. --}}
@php
    use App\Support\LandingLinks as Links;

    $settings = app(\App\Services\PlatformSettings::class);
    $waitlist = app(\App\Services\WaitlistService::class);
    $locale = app()->getLocale();
    $nav = __('landing.nav');
    $menu = __('landing.menu');

    // Individuals never "sign up" on the web (they use the app). An organization or a counselor signs up when that door is open; when it is closed, a waitlist if there is one.
    $door = function (string $role) use ($settings, $waitlist, $nav) {
        $open = $settings->registrationOpen($role);

        return [
            'url' => $open ? route($role === 'organization' ? 'organizations.signup.create' : 'counselors.signup.create') : route('register.type'),
            'waitlist' => ! $open && $waitlist->offered($role),
        ];
    };
    $doors = ['organization' => $door('organization'), 'counselor' => $door('counselor')];

    $menuKeys = ['platform' => 'platform', 'solutions' => 'solutions', 'how' => 'how_it_works', 'pricing' => 'pricing', 'resources' => 'resources'];
    $chevron = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    $soon = fn (string $slug) => Links::isSoon($slug) ? '<span class="ml-1.5 rounded-full bg-brand-ink/[.07] px-1.5 py-px align-middle text-[10px] font-semibold uppercase tracking-wide text-brand-ink/55">'.e($nav['soon']).'</span>' : '';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('landing.meta.title') }}</title>
    <meta name="description" content="{{ __('landing.meta.description') }}">
    @unless (app()->isProduction())<meta name="robots" content="noindex, nofollow">@endunless
    <link rel="icon" href="{{ asset('images/mykaunsel-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; scroll-padding-top: 88px; }
        body { -webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility; }
        .lp-h { letter-spacing: -0.03em; line-height: 1.06; }
        .lp-tabular { font-variant-numeric: tabular-nums; }
        .lp-faq { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s ease; }
        .lp-faq.is-open { grid-template-rows: 1fr; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .lp-faq { transition: none; } }
    </style>
</head>
<body class="bg-white font-landing text-brand-ink antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[100] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold">{{ __('landing.a11y.skip') }}</a>

{{-- ============================== HEADER ============================== --}}
<header class="sticky top-0 z-50 border-b border-[#E6E9F0] bg-white/90 backdrop-blur-md" x-data="{
            open: null, mobile: false, accordion: null, timer: null,
            show(k) { clearTimeout(this.timer); this.open = k; },
            hide() { this.timer = setTimeout(() => this.open = null, 140); },
            toggle(k) { this.open = this.open === k ? null : k; },
        }" @keydown.escape.window="open = null; mobile = false" @click.outside="open = null" @mouseleave="hide()">
    <div class="mx-auto flex h-[64px] max-w-[1200px] items-center gap-6 px-5 lg:px-8">
        <a href="{{ route('home') }}" aria-label="MyKaunsel" class="shrink-0 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
            <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="h-8 w-auto">
        </a>

        {{-- The five menus (wide screens) --}}
        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('landing.a11y.main_nav') }}">
            @foreach ($menuKeys as $key => $label)
                <button type="button" @mouseenter="show('{{ $key }}')" @click="toggle('{{ $key }}')" :aria-expanded="open === '{{ $key }}'" aria-haspopup="true"
                        class="inline-flex items-center gap-1 rounded-md px-3 py-2 text-[14.5px] font-medium text-brand-ink/80 transition hover:text-brand-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
                        :class="open === '{{ $key }}' ? 'text-brand-ink' : ''">
                    {{ $nav[$label] }}<span class="transition-transform" :class="open === '{{ $key }}' ? 'rotate-180' : ''">{!! $chevron !!}</span>
                </button>
            @endforeach
        </nav>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('login') }}" class="hidden rounded-md border border-[#D9DEE9] bg-white px-4 py-2 text-[14px] font-medium text-brand-blue transition hover:border-brand-blue/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue sm:inline-flex">{{ $nav['login'] }}</a>

            {{-- Sign up: organization or counselor only --}}
            <div class="relative hidden sm:block" x-data="{ s: false }" @click.outside="s = false" @keydown.escape="s = false">
                <button type="button" @click="s = !s" :aria-expanded="s" aria-haspopup="true" class="inline-flex items-center gap-1.5 rounded-md border border-[#D9DEE9] bg-white px-4 py-2 text-[14px] font-medium text-brand-blue transition hover:border-brand-blue/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">{{ $nav['signup'] }}{!! $chevron !!}</button>
                <div x-show="s" x-cloak x-transition.opacity class="absolute right-0 top-full mt-2 w-[230px] overflow-hidden rounded-xl border border-[#E6E9F0] bg-white p-1.5 shadow-[0_20px_40px_-18px_rgba(27,42,78,.35)]">
                    @foreach (['organization' => 'signup_org', 'counselor' => 'signup_counselor'] as $role => $labelKey)
                        <a href="{{ $doors[$role]['url'] }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-[14px] font-medium text-brand-ink hover:bg-[#F4F7FC]">
                            <span>{{ $doors[$role]['waitlist'] ? $nav['waitlist'] : $nav[$labelKey] }}</span>
                            @if ($doors[$role]['waitlist'])<span class="text-[11.5px] font-normal text-brand-ink/50">{{ $nav[$labelKey] }}</span>@endif
                        </a>
                    @endforeach
                </div>
            </div>

            <a href="{{ Links::url('get-the-app') }}" class="hidden items-center gap-1.5 rounded-md bg-brand-blue px-4 py-2 text-[14px] font-medium text-white transition hover:bg-brand-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2 sm:inline-flex">{{ $nav['get_app'] }} <span aria-hidden="true">›</span></a>

            <button type="button" class="grid h-10 w-10 place-items-center rounded-md text-brand-ink hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue lg:hidden" @click="mobile = !mobile" :aria-expanded="mobile" aria-label="{{ __('landing.a11y.menu') }}">
                <svg x-show="!mobile" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg x-show="mobile" x-cloak width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
    </div>

    {{-- The big dropdowns --}}
    @foreach ($menuKeys as $key => $label)
        @php($m = $menu[$key])
        <div x-show="open === '{{ $key }}'" x-cloak x-transition.opacity.duration.120ms @mouseenter="show('{{ $key }}')" @mouseleave="hide()"
             class="absolute inset-x-0 top-full hidden border-b border-[#E6E9F0] bg-white shadow-[0_30px_50px_-30px_rgba(27,42,78,.3)] lg:block">
            <div class="mx-auto grid max-w-[1200px] {{ $m['more'] ? 'grid-cols-[minmax(0,1fr)_300px]' : 'grid-cols-1' }}">
                <div class="px-8 pb-6 pt-7">
                    <div class="grid gap-x-8 gap-y-6" style="grid-template-columns: repeat({{ count($m['columns']) }}, minmax(0, 1fr))">
                        @foreach ($m['columns'] as $column)
                            <div>
                                <p class="border-b border-[#E6E9F0] pb-2 text-[13.5px] font-medium text-brand-ink/70">{{ $column['title'] }}</p>
                                <ul class="mt-3 space-y-3.5">
                                    @foreach ($column['items'] as [$slug, $itemTitle, $desc])
                                        <li>
                                            <a href="{{ Links::url($slug) }}" class="group block rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue" @click="open = null">
                                                <span class="text-[14.5px] font-medium text-brand-blue group-hover:text-brand-deep">{{ $itemTitle }}</span>{!! $soon($slug) !!}
                                                <span class="mt-0.5 block text-[13.5px] leading-snug text-brand-ink/60">{{ $desc }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 border-t border-[#E6E9F0] pt-4">
                        <a href="{{ Links::url($m['see_all'][0]) }}" class="inline-flex items-center gap-1 text-[14px] font-medium text-brand-blue hover:text-brand-deep" @click="open = null">{{ $m['see_all'][1] }} <span aria-hidden="true">›</span></a>
                    </div>
                </div>

                @if ($m['more'])
                    <aside class="border-l border-[#E6E9F0] bg-[#F7F9FD] px-6 pb-6 pt-7">
                        <p class="text-[13.5px] font-medium text-brand-ink/70">{{ $nav['more'] }}</p>
                        <ul class="mt-4 space-y-3.5">
                            @foreach ($m['more']['items'] as [$slug, $itemTitle, $desc])
                                <li>
                                    <a href="{{ Links::url($slug) }}" class="group block rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue" @click="open = null">
                                        <span class="text-[14.5px] font-medium text-brand-blue group-hover:text-brand-deep">{{ $itemTitle }}</span>{!! $soon($slug) !!}
                                        <span class="mt-0.5 block text-[13.5px] leading-snug text-brand-ink/60">{{ $desc }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @php($card = $m['more']['card'])
                        <div class="mt-5 overflow-hidden rounded-lg border border-[#E6E9F0] bg-white">
                            <x-landing.media slot_name="menu-{{ $key }}" ratio="16 / 9" kind="image" label="Gambar kad menu" size="640 × 360" rounded="rounded-none" />
                            <div class="p-3.5">
                                <p class="text-[13.5px] font-semibold">{{ $card['title'] }}</p>
                                <p class="mt-0.5 text-[13px] leading-snug text-brand-ink/60">{{ $card['text'] }}</p>
                                <a href="{{ Links::url($card['slug']) }}" class="mt-2 inline-flex items-center gap-1 text-[13.5px] font-medium text-brand-blue" @click="open = null">{{ $card['cta'] }} <span aria-hidden="true">›</span></a>
                            </div>
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    @endforeach

    {{-- Small screens: the same menus as a list that opens and closes --}}
    <div x-show="mobile" x-cloak class="max-h-[calc(100vh-64px)] overflow-y-auto border-t border-[#E6E9F0] bg-white px-5 pb-8 pt-2 lg:hidden">
        @foreach ($menuKeys as $key => $label)
            @php($m = $menu[$key])
            <div class="border-b border-[#EEF0F5]">
                <button type="button" @click="accordion = accordion === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="accordion === '{{ $key }}'" class="flex w-full items-center justify-between py-4 text-left text-[16px] font-medium">
                    {{ $nav[$label] }}<span class="transition-transform" :class="accordion === '{{ $key }}' ? 'rotate-180' : ''">{!! $chevron !!}</span>
                </button>
                <div x-show="accordion === '{{ $key }}'" x-cloak class="pb-4">
                    @foreach ($m['columns'] as $column)
                        <p class="mb-1 mt-3 text-[12.5px] font-semibold uppercase tracking-wide text-brand-ink/45">{{ $column['title'] }}</p>
                        @foreach ($column['items'] as [$slug, $itemTitle, $desc])
                            <a href="{{ Links::url($slug) }}" class="block py-1.5 text-[15px] text-brand-blue">{{ $itemTitle }}{!! $soon($slug) !!}</a>
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="mt-5 grid gap-2.5">
            <a href="{{ Links::url('get-the-app') }}" class="rounded-md bg-brand-blue px-4 py-3 text-center text-[15px] font-medium text-white">{{ $nav['get_app'] }}</a>
            <a href="{{ route('login') }}" class="rounded-md border border-[#D9DEE9] px-4 py-3 text-center text-[15px] font-medium text-brand-blue">{{ $nav['login'] }}</a>
            @foreach (['organization' => 'signup_org', 'counselor' => 'signup_counselor'] as $role => $labelKey)
                <a href="{{ $doors[$role]['url'] }}" class="rounded-md border border-[#D9DEE9] px-4 py-3 text-center text-[15px] font-medium text-brand-blue">{{ $nav['signup'] }}: {{ $doors[$role]['waitlist'] ? $nav['waitlist'] : $nav[$labelKey] }}</a>
            @endforeach
        </div>
    </div>
</header>

{{-- ============================== THE PAGE ============================== --}}
<main id="main" class="mx-auto max-w-[1200px] border-x border-[#E6E9F0]">
    {{ $slot }}
</main>

{{-- ============================== URGENT HELP + FOOTER ============================== --}}
<footer class="border-t border-[#E6E9F0] bg-[#F7F9FD]">
    <div class="mx-auto max-w-[1200px] border-x border-[#E6E9F0] bg-white">
        {{-- The urgent help strip: always shown --}}
        <section class="border-b border-[#E6E9F0] bg-[#FFF8E6] px-5 py-6 lg:px-8" aria-labelledby="urgent-title">
            <div class="flex flex-wrap items-center justify-between gap-x-10 gap-y-3">
                <div class="max-w-[560px]">
                    <p id="urgent-title" class="text-[15px] font-semibold">{{ __('landing.urgent.label') }}</p>
                    <p class="mt-1 text-[13.5px] leading-relaxed text-brand-ink/70">{{ __('landing.urgent.text') }}</p>
                </div>
                <ul class="flex flex-wrap gap-x-8 gap-y-2 text-[14px]">
                    @foreach (config('landing.urgent_help') as $line)
                        <li>
                            <span class="font-semibold">{{ $line['name'] }}</span>
                            @if ($line['number'])<a href="tel:{{ $line['number'] }}" class="ml-1.5 text-[17px] font-semibold text-brand-red lp-tabular">{{ $line['number'] }}</a>@elseif ($line['url'])<a href="{{ $line['url'] }}" target="_blank" rel="noopener" class="ml-1.5 font-medium text-brand-blue underline underline-offset-2">{{ parse_url($line['url'], PHP_URL_HOST) }}</a>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <div class="px-5 pb-8 pt-12 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-[260px_minmax(0,1fr)]">
                <div>
                    <img src="{{ asset('images/mykaunsel-logo.png') }}" alt="MyKaunsel" class="h-8 w-auto">
                    <p class="mt-4 max-w-[26ch] text-[14.5px] leading-relaxed text-brand-ink/65">{{ __('landing.footer.description') }}</p>
                    <a href="{{ Links::url('get-the-app') }}" class="mt-5 inline-flex items-center gap-1.5 rounded-md bg-brand-blue px-4 py-2.5 text-[14px] font-medium text-white transition hover:bg-brand-deep">{{ $nav['get_app'] }} <span aria-hidden="true">›</span></a>
                </div>

                <div class="grid grid-cols-2 gap-x-6 gap-y-9 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach (__('landing.footer.columns') as $column)
                        <div>
                            <p class="text-[13.5px] font-semibold">{{ $column['title'] }}</p>
                            <ul class="mt-3 space-y-2.5">
                                @foreach ($column['items'] as [$slug, $label])
                                    <li><a href="{{ Links::url($slug) }}" class="text-[13.5px] leading-snug text-brand-ink/65 transition hover:text-brand-blue">{{ $label }}</a>{!! $soon($slug) !!}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-14 flex flex-wrap items-center justify-between gap-x-8 gap-y-4 border-t border-[#E6E9F0] pt-6 text-[13px] text-brand-ink/60">
                <p>{{ __('landing.footer.copyright', ['year' => now()->year]) }}</p>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <a href="mailto:{{ config('landing.contact_email') }}" class="hover:text-brand-blue">{{ __('landing.footer.contact') }}: {{ config('landing.contact_email') }}</a>
                    <nav class="flex items-center gap-1" aria-label="{{ __('landing.footer.language') }}">
                        @foreach (['ms', 'en'] as $code)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" hreflang="{{ $code }}" @if ($locale === $code) aria-current="true" @endif
                               class="rounded-md px-2.5 py-1 {{ $locale === $code ? 'bg-brand-ink/[.07] font-semibold text-brand-ink' : 'hover:text-brand-blue' }}">{{ __('landing.footer.'.$code) }}</a>
                        @endforeach
                    </nav>
                    <a href="{{ route('privacy') }}" class="hover:text-brand-blue">{{ __('landing.footer.columns.5.items.1.1') }}</a>
                    <a href="{{ route('terms') }}" class="hover:text-brand-blue">{{ __('landing.footer.columns.5.items.0.1') }}</a>
                    <a href="{{ Links::url('sitemap') }}" class="hover:text-brand-blue">{{ __('landing.footer.sitemap') }}</a>
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
