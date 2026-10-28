@php
    $cards = $report['cards'];
    $notes = $report['notes'];
    $growth = $report['growth'];
    $byType = $report['sessions_by_type'];
    $spec = $report['specializations'];
    $outcomes = $report['outcomes'];
    $days = $range->days();
    $bucketName = ['day' => 'daily', 'week' => 'weekly', 'month' => 'monthly'][$range->bucket()];
    $anySessions = $notes['any_session_outcomes'];
    $threshold = $notes['small_number_threshold'];

    // "▲ +12.5% vs previous 30 days" — direction is written in words as well as with the arrow, never colour alone.
    $trend = function (array $trend) use ($days) {
        if ($trend['percent'] === null) {
            return ['text' => '—', 'hint' => 'No comparison: the previous '.$days.' days had no figure to compare with.', 'sr' => 'No comparison available'];
        }
        $arrow = ['up' => '▲', 'down' => '▼', 'flat' => '■'][$trend['direction']];
        $sign = $trend['percent'] > 0 ? '+' : '';
        $word = ['up' => 'up', 'down' => 'down', 'flat' => 'unchanged'][$trend['direction']];

        return ['text' => $arrow.' '.$sign.rtrim(rtrim(number_format($trend['percent'], 1), '0'), '.').'%', 'hint' => 'vs previous '.$days.' days', 'sr' => $word.' '.abs($trend['percent']).' percent compared with the previous '.$days.' days'];
    };

    $duration = function (?int $seconds) {
        if ($seconds === null) {
            return '—';
        }
        if ($seconds >= 86400) {
            return rtrim(rtrim(number_format($seconds / 86400, 1), '0'), '.').' days';
        }
        if ($seconds >= 3600) {
            return rtrim(rtrim(number_format($seconds / 3600, 1), '0'), '.').' hours';
        }

        return max(1, (int) round($seconds / 60)).' min';
    };

    $percent = fn (?float $p) => $p === null ? '—' : rtrim(rtrim(number_format($p, 1), '0'), '.').'%';

    $lastPoint = collect($growth['points'])->last();
    $pill = 'inline-flex min-h-[36px] items-center rounded-full border px-4 text-[13px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue';
    $field = 'rounded-xl border border-brand-ink/20 px-3 py-1.5 text-[13px] focus:border-brand-blue focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue/30';
@endphp

<x-platform-layout title="Analytics">
    <div x-data="{ loading: false }" @pageshow.window="loading = false">

        <x-platform.page-header title="Analytics" subtitle="Trends across the platform. Totals only: no person or organization is ever named here." />

        {{-- Controls --}}
        <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-brand-ink/10 bg-white p-4 lg:flex-row lg:flex-wrap lg:items-center lg:justify-between">
            <nav class="flex flex-wrap items-center gap-2" aria-label="Date range">
                @foreach ($presets as $preset)
                    <a href="{{ route('platform.analytics', ['range' => $preset]) }}" @click="loading = true"
                       @if ($choice === (string) $preset) aria-current="page" @endif
                       class="{{ $pill }} {{ $choice === (string) $preset ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5' }}">Last {{ $preset }} days</a>
                @endforeach
                <form method="GET" action="{{ route('platform.analytics') }}" class="flex flex-wrap items-center gap-2" @submit="loading = true">
                    <input type="hidden" name="range" value="custom">
                    <label class="sr-only" for="analytics-from">From date</label>
                    <input id="analytics-from" type="date" name="from" value="{{ $choice === 'custom' ? $range->from->format('Y-m-d') : (is_string(request('from')) ? request('from') : '') }}" max="{{ $today }}" required class="{{ $field }}">
                    <span class="text-[13px] text-brand-ink/60" aria-hidden="true">to</span>
                    <label class="sr-only" for="analytics-to">To date</label>
                    <input id="analytics-to" type="date" name="to" value="{{ $choice === 'custom' ? $range->to->format('Y-m-d') : (is_string(request('to')) ? request('to') : '') }}" max="{{ $today }}" required class="{{ $field }}">
                    <button type="submit" class="{{ $pill }} {{ $choice === 'custom' ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5' }}">Apply</button>
                </form>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[12.5px] text-brand-ink/60" role="status" title="Figures are kept for {{ config('analytics.cache_minutes') }} minutes. Refresh works them out again.">Updated {{ $generatedAt->diffInSeconds(now(), true) < 60 ? 'just now' : $generatedAt->diffForHumans() }}</p>
                <form method="POST" action="{{ route('platform.analytics.refresh', $rangeQuery) }}" @submit="loading = true">
                    @csrf
                    <button type="submit" class="{{ $pill }} border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5">Refresh</button>
                </form>
                <a href="{{ route('platform.analytics.export', $rangeQuery) }}" class="{{ $pill }} border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5">Export CSV</a>
            </div>
        </div>

        @if ($rangeError)
            <div class="mt-3 rounded-2xl border border-brand-red/25 bg-brand-red/[.06] px-5 py-3 text-[13.5px] font-semibold text-brand-red" role="alert">{{ $rangeError }} Showing the last {{ config('analytics.default_days') }} days instead.</div>
        @endif

        <p class="mt-3 text-[13px] text-brand-ink/70">
            <strong>{{ $range->from->format('j M Y') }} – {{ $range->to->format('j M Y') }}</strong>
            ({{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }}, {{ $timezone }}). Charts are {{ $bucketName }}. Compared with the {{ $days }} days before.
        </p>

        {{-- Loading skeleton: shown while a new range or a refresh is loading. --}}
        <div x-show="loading" x-cloak class="mt-6" role="status" aria-live="polite">
            <span class="sr-only">Loading analytics…</span>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-[140px] animate-pulse rounded-[20px] bg-brand-ink/[.07]"></div>
                @endfor
            </div>
            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
                @for ($i = 0; $i < 4; $i++)
                    <div class="h-[320px] animate-pulse rounded-[20px] bg-brand-ink/[.07]"></div>
                @endfor
            </div>
        </div>

        <div x-show="! loading">

            {{-- Metric cards --}}
            <section class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key figures">
                @php
                    $org = $cards['organizations'];
                    $cou = $cards['counselors'];
                    $ses = $cards['sessions_completed'];
                    $nsr = $cards['no_show_rate'];
                    $tOrg = $trend($org['trend']);
                    $tCou = $trend($cou['trend']);
                    $tSes = $trend($ses['trend']);
                    $tNsr = $trend($nsr['trend']);
                @endphp

                <x-platform.panel class="flex flex-col !p-5">
                    <h2 class="text-[13px] font-semibold text-brand-ink/65">Approved organizations</h2>
                    <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">{{ number_format($org['value']) }}</p>
                    <p class="mt-2 text-[12.5px] font-semibold"><span aria-hidden="true">{{ $tOrg['text'] }}</span><span class="sr-only">{{ $tOrg['sr'] }}</span> <span class="font-normal text-brand-ink/55">{{ $tOrg['hint'] }}</span></p>
                    <p class="mt-2 text-[12.5px] text-brand-ink/65">{{ number_format($org['suspended']) }} suspended currently</p>
                    @if ($org['undated'] > 0)
                        <p class="mt-1 text-[12px] text-brand-ink/55">{{ number_format($org['undated']) }} without a recorded approval date</p>
                    @endif
                </x-platform.panel>

                <x-platform.panel class="flex flex-col !p-5">
                    <h2 class="text-[13px] font-semibold text-brand-ink/65">Approved counselors</h2>
                    <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">{{ number_format($cou['value']) }}</p>
                    <p class="mt-2 text-[12.5px] font-semibold"><span aria-hidden="true">{{ $tCou['text'] }}</span><span class="sr-only">{{ $tCou['sr'] }}</span> <span class="font-normal text-brand-ink/55">{{ $tCou['hint'] }}</span></p>
                    <p class="mt-2 text-[12.5px] text-brand-ink/65">{{ number_format($cou['independent']) }} independent · {{ number_format($cou['organization']) }} organization</p>
                    <p class="mt-1 text-[12.5px] text-brand-ink/65">{{ number_format($cou['suspended']) }} suspended currently</p>
                    @if ($cou['undated'] > 0)
                        <p class="mt-1 text-[12px] text-brand-ink/55">{{ number_format($cou['undated']) }} without a recorded approval date</p>
                    @endif
                </x-platform.panel>

                <x-platform.panel class="flex flex-col !p-5">
                    <h2 class="text-[13px] font-semibold text-brand-ink/65">Sessions completed</h2>
                    @if (! $anySessions)
                        <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">—</p>
                        <p class="mt-2 text-[13px] font-semibold">No session data yet</p>
                        <p class="mt-1 text-[12px] text-brand-ink/55">A session counts once it is marked completed, and that step is not available yet.</p>
                    @else
                        <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">{{ number_format($ses['value']) }}</p>
                        <p class="mt-2 text-[12.5px] font-semibold"><span aria-hidden="true">{{ $tSes['text'] }}</span><span class="sr-only">{{ $tSes['sr'] }}</span> <span class="font-normal text-brand-ink/55">{{ $tSes['hint'] }}</span></p>
                        <p class="mt-2 text-[12px] text-brand-ink/55">By session date, not booking date.</p>
                    @endif
                </x-platform.panel>

                <x-platform.panel class="flex flex-col !p-5">
                    <h2 class="text-[13px] font-semibold text-brand-ink/65">No-show rate</h2>
                    @if (! $anySessions)
                        <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">—</p>
                        <p class="mt-2 text-[13px] font-semibold">No session data yet</p>
                        <p class="mt-1 text-[12px] text-brand-ink/55">Shown once sessions can be marked completed or no-show.</p>
                    @elseif (! $nsr['has_data'])
                        <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">—</p>
                        <p class="mt-2 text-[13px] font-semibold">No completed or no-show sessions in this period</p>
                    @else
                        <p class="mt-2 text-[34px] font-extrabold leading-none tracking-tightest">{{ $percent($nsr['value']) }}</p>
                        <p class="mt-2 text-[12.5px] font-semibold"><span aria-hidden="true">{{ $tNsr['text'] }}</span><span class="sr-only">{{ $tNsr['sr'] }}</span> <span class="font-normal text-brand-ink/55">{{ $tNsr['hint'] }}</span></p>
                        <p class="mt-2 text-[12px] text-brand-ink/55">{{ number_format($nsr['no_show']) }} no-show of {{ number_format($nsr['completed'] + $nsr['no_show']) }}. Cancelled sessions are not counted ({{ number_format($nsr['cancelled_excluded']) }} in this period).</p>
                    @endif
                </x-platform.panel>
            </section>

            @if ($notes['sessions_without_date'] > 0)
                <p class="mt-3 rounded-2xl border border-brand-yellow/60 bg-brand-yellow/15 px-4 py-2.5 text-[13px] text-[#6B4C0C]" role="note">
                    {{ number_format($notes['sessions_without_date']) }} completed or no-show {{ \Illuminate\Support\Str::plural('session', $notes['sessions_without_date']) }} {{ $notes['sessions_without_date'] === 1 ? 'has' : 'have' }} no session date and {{ $notes['sessions_without_date'] === 1 ? 'is' : 'are' }} left out of the session figures.
                </p>
            @endif

            <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">

                {{-- 1. Growth --}}
                <x-platform.chart-panel id="growth" title="Platform growth" subtitle="Approved organizations and counselors, running total at the end of each period.">
                    <x-slot:summary>
                        @if ($growth['empty'])
                            No organization or counselor had been approved by the end of this period.
                        @else
                            At the end of the period there are {{ number_format($lastPoint['organizations']) }} approved organizations and {{ number_format($lastPoint['counselors']) }} approved counselors (running totals, {{ $bucketName }}).
                        @endif
                    </x-slot:summary>

                    @if ($growth['empty'])
                        <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                            <p class="text-[14px] font-semibold">No growth to show yet</p>
                            <p class="mt-1 text-[13px] text-brand-ink/55">Approved organizations and counselors appear here once there are some.</p>
                        </div>
                    @else
                        <x-platform.line-chart :points="$growth['points']" />
                    @endif

                    <x-slot:tableView>
                        <table class="w-full text-left text-[13px]">
                            <caption class="sr-only">Approved organizations and counselors, running total at the end of each period</caption>
                            <thead><tr class="border-b border-brand-ink/10 text-[12px] text-brand-ink/60"><th scope="col" class="py-2 pr-3 font-semibold">Period</th><th scope="col" class="px-3 py-2 text-right font-semibold">Organizations</th><th scope="col" class="py-2 pl-3 text-right font-semibold">Counselors</th></tr></thead>
                            <tbody>
                                @foreach ($growth['points'] as $point)
                                    <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">{{ $point['label'] }}</th><td class="px-3 py-2 text-right">{{ number_format($point['organizations']) }}</td><td class="py-2 pl-3 text-right">{{ number_format($point['counselors']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:tableView>
                </x-platform.chart-panel>

                {{-- 2. Sessions by organization type --}}
                <x-platform.chart-panel id="types" title="Completed sessions by organization type" subtitle="Sessions in the selected period.">
                    <x-slot:summary>
                        @if (! $anySessions)
                            No session data yet.
                        @elseif (! $byType['has_data'])
                            No completed sessions in this period.
                        @else
                            {{ collect($byType['items'])->map(fn ($i) => $i['label'].' '.$i['display'])->implode(', ') }}.
                        @endif
                    </x-slot:summary>

                    @if (! $anySessions)
                        <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                            <p class="text-[14px] font-semibold">No session data yet</p>
                            <p class="mt-1 text-[13px] text-brand-ink/55">Sessions are counted once a counselor marks them completed. That step is not available yet.</p>
                        </div>
                    @elseif (! $byType['has_data'])
                        <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                            <p class="text-[14px] font-semibold">No completed sessions in this period</p>
                            <p class="mt-1 text-[13px] text-brand-ink/55">Try a longer date range.</p>
                        </div>
                    @else
                        <x-platform.bar-chart name="types" :items="$byType['items']" />
                        <p class="mt-3 text-[12px] text-brand-ink/55">“&lt;{{ $threshold }}” means fewer than {{ $threshold }} sessions. The number is hidden to protect privacy.</p>
                    @endif

                    <x-slot:tableView>
                        <table class="w-full text-left text-[13px]">
                            <caption class="sr-only">Completed sessions by organization type</caption>
                            <thead><tr class="border-b border-brand-ink/10 text-[12px] text-brand-ink/60"><th scope="col" class="py-2 pr-3 font-semibold">Organization type</th><th scope="col" class="py-2 pl-3 text-right font-semibold">Sessions</th></tr></thead>
                            <tbody>
                                @foreach ($byType['items'] as $item)
                                    <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">{{ $item['label'] }}</th><td class="py-2 pl-3 text-right">{{ $item['display'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:tableView>
                </x-platform.chart-panel>

                {{-- 3. Specializations --}}
                @php
                    $specView = $anySessions && $spec['sessions']['has_data'] ? 'sessions' : 'offered';
                    $top = collect($spec[$specView]['items'])->reject(fn ($i) => $i['other'])->take(3);
                @endphp
                <x-platform.chart-panel id="specializations" title="Specializations" subtitle="Which topics counselors cover and which are used.">
                    <x-slot:summary>
                        @if (! $spec['offered']['has_data'] && ! $spec['sessions']['has_data'])
                            No specializations recorded yet.
                        @elseif ($top->isEmpty())
                            Every specialization has fewer than {{ $threshold }}, so they are grouped as “Other”.
                        @else
                            Most {{ $specView === 'sessions' ? 'used (sessions)' : 'offered (counselors)' }}: {{ $top->map(fn ($i) => $i['label'].' '.$i['display'])->implode(', ') }}.
                        @endif
                    </x-slot:summary>

                    <div x-data="{ v: '{{ $specView }}' }">
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Specialization view">
                            <button type="button" @click="v = 'sessions'" :aria-pressed="(v === 'sessions').toString()" :class="v === 'sessions' ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5'" class="{{ $pill }} !min-h-[32px] !px-3 !text-[12.5px]">Sessions</button>
                            <button type="button" @click="v = 'offered'" :aria-pressed="(v === 'offered').toString()" :class="v === 'offered' ? 'border-brand-blue bg-brand-blue text-white' : 'border-brand-ink/20 text-brand-ink hover:bg-brand-ink/5'" class="{{ $pill }} !min-h-[32px] !px-3 !text-[12.5px]">Offered</button>
                        </div>

                        <div class="mt-4" x-show="v === 'sessions'" @if ($specView !== 'sessions') x-cloak @endif>
                            @if (! $anySessions)
                                <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                                    <p class="text-[14px] font-semibold">No session data yet</p>
                                    <p class="mt-1 text-[13px] text-brand-ink/55">Sessions are counted once a counselor marks them completed. See “Offered” for what counselors cover today.</p>
                                </div>
                            @elseif (! $spec['sessions']['has_data'])
                                <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                                    <p class="text-[14px] font-semibold">No completed sessions in this period</p>
                                    <p class="mt-1 text-[13px] text-brand-ink/55">Try a longer date range.</p>
                                </div>
                            @else
                                <p class="mb-2 text-[12px] text-brand-ink/55">Completed sessions. A session counts once for each specialization its counselor lists.</p>
                                <x-platform.bar-chart name="spec-sessions" :items="$spec['sessions']['items']" />
                            @endif
                        </div>

                        <div class="mt-4" x-show="v === 'offered'" @if ($specView !== 'offered') x-cloak @endif>
                            @if (! $spec['offered']['has_data'])
                                <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                                    <p class="text-[14px] font-semibold">No specializations recorded yet</p>
                                    <p class="mt-1 text-[13px] text-brand-ink/55">They appear here once approved counselors list them.</p>
                                </div>
                            @else
                                <p class="mb-2 text-[12px] text-brand-ink/55">Approved counselors who list each specialization, as of now.</p>
                                <x-platform.bar-chart name="spec-offered" :items="$spec['offered']['items']" />
                            @endif
                        </div>

                        <p class="mt-3 text-[12px] text-brand-ink/55">Specializations are free text: spacing and capitals are ignored, nothing else is merged. Groups under {{ $threshold }} are combined as “Other”.</p>
                    </div>

                    <x-slot:tableView>
                        @foreach (['sessions' => 'Completed sessions', 'offered' => 'Approved counselors offering it'] as $key => $heading)
                            <table class="w-full text-left text-[13px] {{ $loop->first ? '' : 'mt-5' }}">
                                <caption class="mb-1 text-left text-[12.5px] font-semibold">{{ $heading }}</caption>
                                <thead><tr class="border-b border-brand-ink/10 text-[12px] text-brand-ink/60"><th scope="col" class="py-2 pr-3 font-semibold">Specialization</th><th scope="col" class="py-2 pl-3 text-right font-semibold">Count</th></tr></thead>
                                <tbody>
                                    @forelse ($spec[$key]['items'] as $item)
                                        <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">{{ $item['label'] }}</th><td class="py-2 pl-3 text-right">{{ $item['display'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="2" class="py-3 text-brand-ink/55">{{ $key === 'sessions' && ! $anySessions ? 'No session data yet.' : 'Nothing to show.' }}</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        @endforeach
                    </x-slot:tableView>
                </x-platform.chart-panel>

                {{-- 4. Application outcomes --}}
                @php $o = $outcomes['organizations']; $c = $outcomes['counselors']; @endphp
                <x-platform.chart-panel id="outcomes" title="Application outcomes" subtitle="Applications submitted in the selected period, and where they stand now.">
                    <x-slot:summary>
                        Organizations: {{ $o['submitted'] }} submitted, {{ $o['approved'] }} approved, {{ $o['rejected'] }} rejected, {{ $o['pending'] }} still pending.
                        Independent counselors: {{ $c['submitted'] }} submitted, {{ $c['approved'] }} approved, {{ $c['rejected'] }} rejected, {{ $c['pending'] }} still pending.
                    </x-slot:summary>

                    @if ($outcomes['organizations']['submitted'] === 0 && $outcomes['counselors']['submitted'] === 0)
                        <div class="rounded-2xl border border-dashed border-brand-ink/20 px-6 py-10 text-center">
                            <p class="text-[14px] font-semibold">No applications submitted in this period</p>
                            <p class="mt-1 text-[13px] text-brand-ink/55">Try a longer date range.</p>
                        </div>
                    @else
                        <ul class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-brand-ink/80" aria-label="Legend">
                            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded-sm bg-[#0A4F88]"></span>Approved</li>
                            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded-sm bg-[#B3202F]"></span>Rejected (now)</li>
                            <li class="flex items-center gap-2"><svg width="12" height="12" aria-hidden="true"><rect width="12" height="12" rx="2" fill="url(#pending-hatch)" stroke="#5B6B73"/></svg>Still pending</li>
                        </ul>
                        <svg width="0" height="0" class="absolute" aria-hidden="true"><defs><pattern id="pending-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="6" height="6" fill="#FFFFFF"/><rect width="3" height="6" fill="#5B6B73"/></pattern></defs></svg>

                        @foreach (['Organizations' => $o, 'Independent counselors' => $c] as $groupLabel => $g)
                            <div class="{{ $loop->first ? '' : 'mt-5' }}">
                                <div class="flex items-baseline justify-between gap-3 text-[13px]"><p class="font-semibold">{{ $groupLabel }}</p><p class="text-brand-ink/60">{{ number_format($g['submitted']) }} submitted</p></div>
                                @if ($g['submitted'] === 0)
                                    <p class="mt-1 text-[12.5px] text-brand-ink/55">None submitted in this period.</p>
                                @else
                                    <svg viewBox="0 0 640 22" class="mt-1.5 block h-auto w-full" role="img" aria-label="{{ $groupLabel }}: {{ $g['approved'] }} approved, {{ $g['rejected'] }} rejected, {{ $g['pending'] }} pending">
                                        @php $px = 0; @endphp
                                        @foreach ([['approved', '#0A4F88', false], ['rejected', '#B3202F', false], ['pending', '#5B6B73', true]] as [$k, $col, $hatch])
                                            @php $segW = $g[$k] / $g['submitted'] * 640; @endphp
                                            @if ($g[$k] > 0)
                                                <rect x="{{ round($px, 1) }}" y="0" width="{{ max(2, round($segW - 2, 1)) }}" height="22" rx="4" fill="{{ $hatch ? 'url(#pending-hatch)' : $col }}" @if ($hatch) stroke="{{ $col }}" @endif><title>{{ ucfirst($k) }}: {{ $g[$k] }}</title></rect>
                                            @endif
                                            @php $px += $segW; @endphp
                                        @endforeach
                                    </svg>
                                    <p class="mt-1.5 text-[12.5px] text-brand-ink/75">
                                        Approved {{ $g['approved'] }} ({{ $percent($g['approved_percent']) }}) · Rejected {{ $g['rejected'] }} ({{ $percent($g['rejected_percent']) }}) · Pending {{ $g['pending'] }} ({{ $percent($g['pending_percent']) }})
                                    </p>
                                @endif
                                <p class="mt-1 text-[12.5px] text-brand-ink/65">
                                    Median decision time: <strong>{{ $duration($g['median_decision_seconds']) }}</strong>
                                    @if ($g['median_decision_seconds'] === null) <span>(no decisions made in this period)</span> @endif
                                    · Resubmissions: <strong>{{ $g['resubmissions'] === null ? '—' : number_format($g['resubmissions']) }}</strong>
                                    @if ($g['resubmissions'] === null) <span>(not tracked yet)</span> @endif
                                </p>
                            </div>
                        @endforeach

                        <p class="mt-4 text-[12px] text-brand-ink/55">“Rejected” is the current status: an application rejected earlier and then resubmitted is counted by where it stands now.</p>
                    @endif

                    <x-slot:tableView>
                        <table class="w-full text-left text-[13px]">
                            <caption class="sr-only">Application outcomes for applications submitted in the selected period</caption>
                            <thead><tr class="border-b border-brand-ink/10 text-[12px] text-brand-ink/60"><th scope="col" class="py-2 pr-3 font-semibold">Figure</th><th scope="col" class="px-3 py-2 text-right font-semibold">Organizations</th><th scope="col" class="py-2 pl-3 text-right font-semibold">Independent counselors</th></tr></thead>
                            <tbody>
                                @foreach ([['Submitted', 'submitted'], ['Approved', 'approved'], ['Rejected (now)', 'rejected'], ['Still pending', 'pending']] as [$rowLabel, $k])
                                    <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">{{ $rowLabel }}</th><td class="px-3 py-2 text-right">{{ number_format($o[$k]) }}</td><td class="py-2 pl-3 text-right">{{ number_format($c[$k]) }}</td></tr>
                                @endforeach
                                <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">Median decision time</th><td class="px-3 py-2 text-right">{{ $duration($o['median_decision_seconds']) }}</td><td class="py-2 pl-3 text-right">{{ $duration($c['median_decision_seconds']) }}</td></tr>
                                <tr class="border-b border-brand-ink/[.07]"><th scope="row" class="py-2 pr-3 font-medium">Resubmissions</th><td class="px-3 py-2 text-right">— (not tracked yet)</td><td class="py-2 pl-3 text-right">{{ number_format($c['resubmissions']) }}</td></tr>
                            </tbody>
                        </table>
                    </x-slot:tableView>
                </x-platform.chart-panel>
            </div>

            <p class="mt-6 text-[12.5px] text-brand-ink/60" role="note">Figures are calculated from live platform data. Demo data may be included in non-production environments.</p>
        </div>
    </div>
</x-platform-layout>
