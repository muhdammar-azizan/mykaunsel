@php
    $selectClass = 'w-full rounded-xl border border-brand-ink/10 bg-white py-2 pl-3 pr-8 text-[13.5px] focus:border-brand-blue focus:ring-brand-blue';
    $labelClass = 'mb-1 block text-[11.5px] font-semibold uppercase tracking-wide text-brand-ink/55';
    $submit = "this.form.requestSubmit()";
    $currentAction = $filters->get('action');
    $knownAction = $currentAction === null || collect($actionOptions)->contains('value', $currentAction);
    $currentTarget = $filters->get('target_type');
    $targetOptions = collect($targetTypes)->when($currentTarget && ! in_array($currentTarget, $targetTypes, true), fn ($c) => $c->push($currentTarget))->sort()->values();
    $exportUrl = route('platform.audit-log.export', $filters->toQuery());
    $from = $logs->firstItem();
@endphp

<x-platform-layout title="Audit Log">
    <div x-data="auditLog({ showUrl: @js(url('/platform/audit-log')) })" @keydown.escape.window="close()">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <x-platform.page-header title="Audit Log" subtitle="Record of all administrative actions" />

            <a href="{{ $exportUrl }}"
               class="inline-flex min-h-[40px] items-center gap-2 rounded-full border border-brand-ink/15 bg-white px-5 text-[13.5px] font-semibold shadow-sm transition hover:border-brand-ink hover:bg-brand-ink hover:text-white">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                Export log
            </a>
        </div>

        <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-brand-ink/[.05] px-3.5 py-1.5 text-[12.5px] text-brand-ink/65">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            This log is read-only. Entries cannot be edited or deleted.
        </p>

        @if (session('error'))
            <div class="mt-4 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
        @endif

        @if ($filters->errors()->any())
            <div class="mt-4 rounded-2xl border border-brand-yellow/50 bg-brand-yellow/15 px-5 py-3 text-[13px] text-[#6B4C0C]" role="alert">
                <p class="font-bold">Some filters in the link were not valid and were ignored:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($filters->errors()->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('platform.audit-log') }}" role="search" aria-label="Filter the audit log"
              class="mt-5 rounded-[20px] border border-brand-ink/10 bg-white p-5"
              x-data="{ category: @js($filters->get('category', '')), range: @js($filters->get('range', '')) }">
            @if ($filters->get('per_page'))
                <input type="hidden" name="per_page" value="{{ $filters->get('per_page') }}">
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="f-q" class="{{ $labelClass }}">Search</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-brand-ink/40" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input id="f-q" type="search" name="q" value="{{ $filters->get('q') }}" maxlength="100" placeholder="Admin, target or action key"
                               class="w-full rounded-xl border border-brand-ink/10 bg-white py-2 pl-9 pr-3 text-[13.5px] placeholder:text-brand-ink/40 focus:border-brand-blue focus:ring-brand-blue">
                    </div>
                </div>

                <div>
                    <label for="f-category" class="{{ $labelClass }}">Category</label>
                    <select id="f-category" name="category" x-model="category" onchange="{{ $submit }}" class="{{ $selectClass }}">
                        <option value="">All categories</option>
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f-action" class="{{ $labelClass }}">Action</label>
                    <select id="f-action" name="action" onchange="{{ $submit }}" class="{{ $selectClass }}">
                        <option value="">All actions</option>
                        @if (! $knownAction)
                            <option value="{{ $currentAction }}" selected>{{ \App\Support\AuditCatalogue::describe($currentAction)['label'] }}</option>
                        @endif
                        @foreach (collect($actionOptions)->groupBy('category') as $cat => $options)
                            <optgroup label="{{ $categories[$cat] ?? $cat }}"
                                      x-bind:hidden="category && category !== '{{ $cat }}'"
                                      x-bind:disabled="category && category !== '{{ $cat }}'">
                                @foreach ($options as $option)
                                    <option value="{{ $option['value'] }}" @selected($currentAction === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f-actor" class="{{ $labelClass }}">Admin</label>
                    <select id="f-actor" name="actor" onchange="{{ $submit }}" class="{{ $selectClass }}">
                        <option value="">Anyone</option>
                        <option value="system" @selected($filters->get('actor') === 'system')>System</option>
                        @foreach ($actors as $id => $name)
                            <option value="{{ $id }}" @selected((string) $filters->get('actor') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="f-target" class="{{ $labelClass }}">Target</label>
                    <select id="f-target" name="target_type" onchange="{{ $submit }}" class="{{ $selectClass }}">
                        <option value="">Any target</option>
                        @foreach ($targetOptions as $type)
                            <option value="{{ $type }}" @selected($currentTarget === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <label for="f-org" class="{{ $labelClass }}">Organization</label>
                    <select id="f-org" name="organization" onchange="{{ $submit }}" class="{{ $selectClass }}">
                        <option value="">Any organization</option>
                        @php $currentOrg = (int) $filters->get('organization'); @endphp
                        @foreach ($organizations as $id => $name)
                            <option value="{{ $id }}" @selected($currentOrg === (int) $id)>{{ $name }}</option>
                        @endforeach
                        @if ($currentOrg && ! isset($organizations[$currentOrg]))
                            <option value="{{ $currentOrg }}" selected>Organization #{{ $currentOrg }}</option>
                        @endif
                    </select>
                </div>

                <div class="sm:col-span-2 lg:col-span-4">
                    <span class="{{ $labelClass }}" id="f-range-label">Date <span class="font-normal normal-case tracking-normal text-brand-ink/45">· days in your timezone ({{ $timezone }})</span></span>
                    <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-labelledby="f-range-label">
                        <label class="cursor-pointer">
                            <input type="radio" name="range" value="" x-model="range" class="peer sr-only" onchange="{{ $submit }}">
                            <span class="inline-flex rounded-full border border-brand-ink/10 px-3.5 py-1.5 text-[13px] font-semibold text-brand-ink/70 transition peer-checked:border-brand-blue peer-checked:bg-brand-blue peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-blue peer-focus-visible:ring-offset-2">Any time</span>
                        </label>
                        @foreach ($ranges as $key => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="range" value="{{ $key }}" x-model="range" class="peer sr-only"
                                       @if ($key !== 'custom') onchange="{{ $submit }}" @endif>
                                <span class="inline-flex rounded-full border border-brand-ink/10 px-3.5 py-1.5 text-[13px] font-semibold text-brand-ink/70 transition peer-checked:border-brand-blue peer-checked:bg-brand-blue peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-blue peer-focus-visible:ring-offset-2">{{ $label }}</span>
                            </label>
                        @endforeach

                        <span x-show="range === 'custom'" x-cloak class="flex flex-wrap items-center gap-2">
                            <input type="date" name="from" value="{{ $filters->get('from') }}" aria-label="From date" class="rounded-xl border border-brand-ink/10 px-3 py-1.5 text-[13px] focus:border-brand-blue focus:ring-brand-blue" :disabled="range !== 'custom'">
                            <span class="text-brand-ink/40" aria-hidden="true">→</span>
                            <input type="date" name="to" value="{{ $filters->get('to') }}" aria-label="To date" class="rounded-xl border border-brand-ink/10 px-3 py-1.5 text-[13px] focus:border-brand-blue focus:ring-brand-blue" :disabled="range !== 'custom'">
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-brand-ink/[.07] pt-4">
                <label class="inline-flex cursor-pointer items-center gap-2.5 text-[13.5px]">
                    <input type="checkbox" name="sign_in" value="1" @checked($filters->includesSignIn()) onchange="{{ $submit }}"
                           class="h-[18px] w-[18px] rounded-[5px] border-brand-ink/30 text-brand-blue focus:ring-brand-blue">
                    <span>Include sign-in activity <span class="text-brand-ink/45">(hidden by default)</span></span>
                </label>

                <div class="flex items-center gap-3">
                    @if ($filters->isFiltering())
                        <a href="{{ route('platform.audit-log', array_filter(['per_page' => $filters->get('per_page')])) }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Clear all</a>
                    @endif
                    <button type="submit" class="inline-flex min-h-[38px] items-center rounded-full bg-brand-blue px-5 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep">Apply filters</button>
                </div>
            </div>
        </form>

        {{-- Active-filter chips --}}
        @if ($chips)
            <ul class="mt-3 flex flex-wrap items-center gap-2" aria-label="Active filters">
                @foreach ($chips as $chip)
                    <li>
                        <a href="{{ route('platform.audit-log', $chip['remove'] + array_filter(['per_page' => $filters->get('per_page')])) }}"
                           class="group inline-flex items-center gap-1.5 rounded-full bg-brand-blue/10 py-1 pl-3 pr-2 text-[12.5px] font-semibold text-brand-blue transition hover:bg-brand-blue hover:text-white"
                           aria-label="Remove filter {{ $chip['label'] }}: {{ $chip['value'] }}">
                            <span class="text-brand-blue/65 group-hover:text-white/75">{{ $chip['label'] }}:</span> {{ $chip['value'] }}
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </a>
                    </li>
                @endforeach
                <li><a href="{{ route('platform.audit-log', array_filter(['per_page' => $filters->get('per_page')])) }}" class="px-1 text-[12.5px] font-semibold text-brand-ink/60 hover:text-brand-ink hover:underline">Clear all</a></li>
            </ul>
        @endif

        {{-- Table --}}
        <section class="mt-4 overflow-hidden rounded-[20px] border border-brand-ink/10 bg-white" aria-label="Audit entries">
            @if ($rows->isEmpty())
                <div class="px-6 py-16 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-blue/10 text-brand-blue">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12h6M9 16h6M9 8h6M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/></svg>
                    </span>
                    @if (! $hasAnyEntries)
                        <p class="mt-4 text-[15px] font-bold">Nothing has been recorded yet</p>
                        <p class="mx-auto mt-1 max-w-[46ch] text-[13px] text-brand-ink/55">Administrative actions — approvals, suspensions, security changes and system jobs — will appear here as they happen.</p>
                    @else
                        <p class="mt-4 text-[15px] font-bold">No entries match these filters</p>
                        <p class="mx-auto mt-1 max-w-[46ch] text-[13px] text-brand-ink/55">Try a wider date range or fewer filters.
                            @unless ($filters->includesSignIn()) Sign-in activity is hidden unless you include it. @endunless</p>
                        <a href="{{ route('platform.audit-log', array_filter(['per_page' => $filters->get('per_page')])) }}" class="mt-4 inline-flex rounded-full border border-brand-ink/15 px-4 py-1.5 text-[13px] font-semibold transition hover:bg-brand-ink/5">Clear all filters</a>
                    @endif
                </div>
            @else
                <div class="max-h-[68vh] overflow-auto">
                    <table class="w-full min-w-[860px] text-left">
                        <caption class="sr-only">Audit log entries, newest first. Times are shown in {{ $timezone }}.</caption>
                        <thead>
                            <tr>
                                @foreach (['Timestamp', 'Admin', 'Action', 'Target', 'Details'] as $heading)
                                    <th scope="col" class="sticky top-0 z-10 border-b border-brand-ink/10 bg-cream px-4 py-3 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/70">{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr class="cursor-pointer border-t border-brand-ink/[.07] align-top transition hover:bg-brand-blue/[.04]"
                                    :class="openId === {{ $r['id'] }} ? 'bg-brand-blue/[.06]' : ''"
                                    @click="open({{ $r['id'] }}, $refs['r{{ $r['id'] }}'])">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <button type="button" x-ref="r{{ $r['id'] }}" @click.stop="open({{ $r['id'] }}, $el)"
                                                class="rounded text-left text-[13px] font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
                                                aria-label="Open details for entry {{ $r['id'] }}: {{ $r['action_label'] }}, {{ $r['time'] }}">
                                            <time datetime="{{ $r['time_iso'] }}" title="{{ $r['time_utc'] }}" class="underline decoration-brand-ink/25 decoration-dotted underline-offset-4">{{ $r['time'] }}</time>
                                        </button>
                                    </td>

                                    <td class="px-4 py-3">
                                        @php $a = $r['actor']; @endphp
                                        <div class="flex items-center gap-2.5">
                                            @if ($a['kind'] === 'system')
                                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-zinc-100 text-zinc-600" title="System" aria-hidden="true">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
                                                </span>
                                            @elseif ($a['avatar'])
                                                <img src="{{ $a['avatar'] }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-cover">
                                            @else
                                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-yellow/80 text-[11px] font-bold text-brand-ink" aria-hidden="true">{{ $a['initials'] }}</span>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="truncate text-[13.5px] font-semibold {{ $a['kind'] === 'guest' ? 'text-brand-ink/55' : '' }}">{{ $a['name'] }}</p>
                                                @if ($a['role'])
                                                    <p class="truncate text-[11.5px] text-brand-ink/50">{{ $a['role'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold {{ $r['badge']['classes'] }}">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $r['badge']['icon'] }}"/></svg>
                                            {{ $r['badge']['label'] }}
                                        </span>
                                        <p class="mt-1 text-[12.5px] text-brand-ink/65" title="{{ $r['action_key'] }}">{{ $r['action_label'] }}</p>
                                    </td>

                                    <td class="px-4 py-3 text-[13px]" @click.stop>
                                        @if ($r['target']['has_target'])
                                            <p class="text-[11.5px] text-brand-ink/50">{{ $r['target']['label'] }}</p>
                                            <p class="font-medium">
                                                @if ($r['target']['url'] && ! $r['target']['deleted'])
                                                    <a href="{{ $r['target']['url'] }}" class="text-brand-blue hover:underline">{{ $r['target']['name'] }}</a>
                                                @else
                                                    {{ $r['target']['name'] }}
                                                @endif
                                                @if ($r['target']['deleted'])
                                                    <span class="font-normal text-brand-ink/50">(deleted)</span>
                                                @endif
                                            </p>
                                        @else
                                            <span class="text-brand-ink/40">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-[13px] text-brand-ink/70">
                                        <span class="block max-w-[300px] truncate" title="{{ $r['summary'] }}">{{ $r['summary'] !== '' ? $r['summary'] : '—' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-ink/[.07] px-4 py-3">
                    <p class="text-[13px] text-brand-ink/65" aria-live="polite">
                        Showing <span class="font-semibold text-brand-ink">{{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }}</span>
                        of <span class="font-semibold text-brand-ink">{{ number_format($logs->total()) }}</span>
                    </p>

                    <form method="GET" action="{{ route('platform.audit-log') }}" class="flex items-center gap-2 text-[13px]">
                        @foreach (\Illuminate\Support\Arr::dot($filters->toQuery()) as $name => $value)
                            @php $field = str_contains($name, '.') ? \Illuminate\Support\Str::before($name, '.').'['.\Illuminate\Support\Str::after($name, '.').']' : $name; @endphp
                            @if ($name !== 'per_page')
                                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <label for="per-page" class="text-brand-ink/60">Rows per page</label>
                        <select id="per-page" name="per_page" onchange="{{ $submit }}" class="rounded-lg border border-brand-ink/10 bg-white py-1 pl-2.5 pr-7 text-[13px] focus:border-brand-blue focus:ring-brand-blue">
                            @foreach ($perPageOptions as $n)
                                <option value="{{ $n }}" @selected($filters->perPage() === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($logs->hasPages())
                    <div class="border-t border-brand-ink/[.07] px-4 py-3">{{ $logs->links() }}</div>
                @endif
            @endif
        </section>

        {{-- Detail drawer --}}
        <div x-show="openId !== null" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="audit-drawer-title" @keydown.tab="trap($event)">
            <div class="absolute inset-0 bg-brand-ink/40" @click="close()"
                 x-show="openId !== null" x-transition.opacity></div>

            <aside x-ref="drawer" tabindex="-1"
                   class="absolute right-0 top-0 flex h-full w-full max-w-[540px] flex-col bg-white shadow-[-24px_0_48px_-24px_rgba(14,42,51,.5)] focus:outline-none"
                   x-show="openId !== null"
                   x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-8 opacity-0" x-transition:enter-end="translate-x-0 opacity-100"
                   x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0 opacity-100" x-transition:leave-end="translate-x-8 opacity-0">

                <header class="flex items-center justify-between gap-3 border-b border-brand-ink/10 px-6 py-4">
                    <div class="min-w-0">
                        <h2 id="audit-drawer-title" class="text-[16px] font-extrabold tracking-tight">Entry <span x-text="'#' + openId"></span></h2>
                        <p class="text-[12px] text-brand-ink/50">Read-only record</p>
                    </div>
                    <button type="button" x-ref="close" @click="close()" class="grid h-9 w-9 shrink-0 place-items-center rounded-full transition hover:bg-brand-ink/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue" aria-label="Close details">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5" aria-live="polite">
                    {{-- Loading skeleton --}}
                    <div x-show="loading" class="animate-pulse space-y-5" aria-hidden="true">
                        <div class="h-6 w-2/3 rounded bg-brand-ink/10"></div>
                        <div class="h-4 w-1/2 rounded bg-brand-ink/10"></div>
                        <div class="space-y-2 pt-2"><div class="h-4 rounded bg-brand-ink/10"></div><div class="h-4 w-5/6 rounded bg-brand-ink/10"></div><div class="h-4 w-4/6 rounded bg-brand-ink/10"></div></div>
                        <div class="h-24 rounded-xl bg-brand-ink/10"></div>
                        <div class="space-y-2"><div class="h-4 rounded bg-brand-ink/10"></div><div class="h-4 w-3/4 rounded bg-brand-ink/10"></div></div>
                    </div>
                    <span class="sr-only" x-show="loading">Loading entry…</span>

                    {{-- Error --}}
                    <div x-show="error" x-cloak class="rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-4 text-[13.5px]" role="alert">
                        <p class="font-bold text-brand-red">Couldn't load this entry.</p>
                        <button type="button" class="mt-2 font-semibold text-brand-blue hover:underline" @click="open(openId, trigger)">Try again</button>
                    </div>

                    <template x-if="d">
                        <div class="space-y-6 text-[13.5px]">
                            <section>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-bold" :class="d.badge.classes">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="d.badge.icon"/></svg>
                                        <span x-text="d.badge.label"></span>
                                    </span>
                                    <span class="rounded-full bg-brand-ink/[.06] px-2.5 py-0.5 text-[11.5px] font-semibold text-brand-ink/65" x-text="d.category"></span>
                                    <span x-show="d.seeded" class="rounded-full bg-brand-yellow/25 px-2.5 py-0.5 text-[11.5px] font-semibold text-[#7A5410]">Sample data</span>
                                </div>
                                <p class="mt-2 text-[17px] font-extrabold leading-snug" x-text="d.action_label"></p>
                                <p class="font-mono text-[12px] text-brand-ink/50" x-text="d.action_key"></p>
                            </section>

                            <dl class="divide-y divide-brand-ink/[.07] rounded-2xl border border-brand-ink/10 px-4">
                                <div class="py-3">
                                    <dt class="text-[11.5px] font-semibold uppercase tracking-wide text-brand-ink/50">When</dt>
                                    <dd class="mt-0.5 font-semibold" x-text="d.time_full"></dd>
                                    <dd class="text-[12.5px] text-brand-ink/55" x-text="d.time_utc"></dd>
                                </div>
                                <div class="py-3">
                                    <dt class="text-[11.5px] font-semibold uppercase tracking-wide text-brand-ink/50">Admin</dt>
                                    <dd class="mt-0.5 font-semibold"><span x-text="d.actor.name"></span> <span class="font-normal text-brand-ink/55" x-show="d.actor.role" x-text="'· ' + d.actor.role"></span></dd>
                                </div>
                                <div class="py-3" x-show="d.target.has_target">
                                    <dt class="text-[11.5px] font-semibold uppercase tracking-wide text-brand-ink/50">Target</dt>
                                    <dd class="mt-0.5">
                                        <span class="text-brand-ink/55" x-text="d.target.label + ':'"></span>
                                        <template x-if="d.target.url && !d.target.deleted"><a :href="d.target.url" class="font-semibold text-brand-blue hover:underline" x-text="d.target.name"></a></template>
                                        <template x-if="!(d.target.url && !d.target.deleted)"><span class="font-semibold" x-text="d.target.name"></span></template>
                                        <span x-show="d.target.deleted" class="text-brand-ink/50">(deleted)</span>
                                    </dd>
                                </div>
                                <div class="py-3" x-show="d.organization">
                                    <dt class="text-[11.5px] font-semibold uppercase tracking-wide text-brand-ink/50">Organization</dt>
                                    <dd class="mt-0.5">
                                        <template x-if="d.organization && d.organization.url"><a :href="d.organization.url" class="font-semibold text-brand-blue hover:underline" x-text="d.organization.name"></a></template>
                                        <template x-if="d.organization && !d.organization.url"><span class="font-semibold" x-text="d.organization.name"></span></template>
                                        <a x-show="d.organization" :href="d.organization ? d.organization.history_url : '#'" class="ml-2 text-[12.5px] font-semibold text-brand-ink/55 hover:text-brand-blue hover:underline">All history →</a>
                                    </dd>
                                </div>
                            </dl>

                            <section x-show="d.changes.length">
                                <h3 class="text-[13px] font-bold">What changed</h3>
                                <div class="mt-2 overflow-hidden rounded-2xl border border-brand-ink/10">
                                    <table class="w-full text-left text-[13px]">
                                        <thead class="bg-cream"><tr>
                                            <th scope="col" class="px-3 py-2 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/60">Field</th>
                                            <th scope="col" class="px-3 py-2 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/60">Before</th>
                                            <th scope="col" class="px-3 py-2 text-[10.5px] font-bold uppercase tracking-wide text-brand-ink/60">After</th>
                                        </tr></thead>
                                        <tbody>
                                            <template x-for="c in d.changes" :key="c.field">
                                                <tr class="border-t border-brand-ink/[.07]">
                                                    <td class="px-3 py-2 font-semibold" x-text="c.field"></td>
                                                    <td class="break-words px-3 py-2 text-brand-red/90" x-text="c.old"></td>
                                                    <td class="break-words px-3 py-2 font-semibold text-emerald-700" x-text="c.new"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section x-show="d.reason">
                                <h3 class="text-[13px] font-bold">Reason / note</h3>
                                <p class="mt-2 whitespace-pre-line break-words rounded-2xl bg-cream px-4 py-3 leading-relaxed" x-text="d.reason"></p>
                            </section>

                            <section x-show="d.details.length">
                                <h3 class="text-[13px] font-bold">Other details</h3>
                                <dl class="mt-2 divide-y divide-brand-ink/[.07] rounded-2xl border border-brand-ink/10 px-4">
                                    <template x-for="item in d.details" :key="item.label">
                                        <div class="flex justify-between gap-4 py-2.5">
                                            <dt class="shrink-0 text-brand-ink/55" x-text="item.label"></dt>
                                            <dd class="min-w-0 break-words text-right font-medium" x-text="item.value"></dd>
                                        </div>
                                    </template>
                                </dl>
                            </section>

                            <section>
                                <h3 class="text-[13px] font-bold">Technical</h3>
                                <dl class="mt-2 divide-y divide-brand-ink/[.07] rounded-2xl border border-brand-ink/10 px-4 text-[12.5px]">
                                    <div class="flex justify-between gap-4 py-2.5"><dt class="shrink-0 text-brand-ink/55">IP address</dt><dd class="break-all text-right font-mono" x-text="d.ip || '—'"></dd></div>
                                    <div class="flex justify-between gap-4 py-2.5"><dt class="shrink-0 text-brand-ink/55">User agent</dt><dd class="break-words text-right" x-text="d.user_agent || '—'"></dd></div>
                                    <div class="flex justify-between gap-4 py-2.5"><dt class="shrink-0 text-brand-ink/55">Request ID</dt><dd class="break-all text-right font-mono" x-text="d.request_id || '—'"></dd></div>
                                </dl>
                            </section>

                            <section x-show="d.related.length">
                                <h3 class="text-[13px] font-bold">Other entries from the same request</h3>
                                <ul class="mt-2 divide-y divide-brand-ink/[.07] rounded-2xl border border-brand-ink/10">
                                    <template x-for="rel in d.related" :key="rel.id">
                                        <li>
                                            <button type="button" class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left transition hover:bg-brand-blue/[.05] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-blue"
                                                    @click="open(rel.id, trigger)">
                                                <span class="min-w-0">
                                                    <span class="block font-semibold" x-text="rel.action_label"></span>
                                                    <span class="block truncate text-[12px] text-brand-ink/55" x-text="rel.target || ''"></span>
                                                </span>
                                                <span class="shrink-0 text-[12px] text-brand-ink/50" x-text="rel.time"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </section>
                        </div>
                    </template>
                </div>

                <footer class="flex flex-wrap items-center gap-2 border-t border-brand-ink/10 px-6 py-4" x-show="d" x-cloak>
                    <template x-if="d && d.target.url && !d.target.deleted">
                        <a :href="d.target.url" class="inline-flex min-h-[36px] items-center rounded-full bg-brand-blue px-4 text-[13px] font-semibold text-white transition hover:bg-brand-deep">View target</a>
                    </template>
                    <template x-if="d && d.view_all_url">
                        <a :href="d.view_all_url" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/20 px-4 text-[13px] font-semibold transition hover:bg-brand-ink/5" x-text="d.actor.kind === 'system' ? 'View all by System' : 'View all by this admin'"></a>
                    </template>
                    <button type="button" @click="copy(openId)" class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/20 px-4 text-[13px] font-semibold transition hover:bg-brand-ink/5">
                        <span x-show="!copied">Copy entry ID</span><span x-show="copied" x-cloak class="text-emerald-700">Copied ✓</span>
                    </button>
                </footer>
            </aside>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('auditLog', (cfg) => ({
                openId: null, loading: false, error: false, d: null, trigger: null, copied: false,

                init() {
                    // A link like …/audit-log#entry-42 opens that entry straight away.
                    const m = window.location.hash.match(/^#entry-(\d+)$/);
                    if (m) this.open(parseInt(m[1], 10), null);
                },

                async open(id, el) {
                    if (el) this.trigger = el;
                    this.openId = id; this.loading = true; this.error = false; this.d = null;
                    history.replaceState(null, '', window.location.pathname + window.location.search + '#entry-' + id);
                    this.$nextTick(() => this.$refs.close && this.$refs.close.focus());

                    try {
                        const res = await fetch(cfg.showUrl + '/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!res.ok) throw new Error('load failed');
                        const data = await res.json();
                        if (this.openId === id) this.d = data;
                    } catch (e) {
                        if (this.openId === id) this.error = true;
                    } finally {
                        if (this.openId === id) this.loading = false;
                    }
                },

                close() {
                    if (this.openId === null) return;
                    this.openId = null; this.d = null; this.error = false;
                    history.replaceState(null, '', window.location.pathname + window.location.search);
                    this.$nextTick(() => this.trigger && this.trigger.focus());
                },

                async copy(text) {
                    try {
                        await navigator.clipboard.writeText(String(text));
                        this.copied = true;
                        setTimeout(() => this.copied = false, 1800);
                    } catch (e) { /* clipboard unavailable: nothing to do */ }
                },

                // Keep Tab inside the open drawer.
                trap(e) {
                    const nodes = [...this.$refs.drawer.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter(n => n.offsetParent !== null);
                    if (!nodes.length) return;
                    const first = nodes[0], last = nodes[nodes.length - 1];
                    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
                },
            }));
        });
    </script>

    <x-platform.toast />
</x-platform-layout>
