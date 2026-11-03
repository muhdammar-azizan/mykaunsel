@php $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6'; @endphp

<x-portal-layout title="Reviews">
    <h1 class="text-[24px] font-bold leading-tight tracking-tight">Reviews</h1>
    <p class="mt-1 max-w-3xl text-[14px] text-brand-ink/65">The stars clients gave after sessions with the counselors of {{ $organization->name }}. You see stars only, never what a client wrote: the words are read only by the counselor and the MyKaunsel team, so a client's comment is never used to judge a counselor's work. No client is named.</p>

    <form method="GET" class="mt-5 flex items-center gap-2">
        <label for="range" class="text-[13px] font-semibold text-brand-ink/70">Period</label>
        <select id="range" name="range" onchange="this.form.submit()" class="min-h-[40px] rounded-md border border-brand-ink/20 pl-3 pr-9 text-[14px]">
            @foreach ($period['choices'] as $key => $label)<option value="{{ $key }}" @selected($period['key'] === $key)>{{ $label }}</option>@endforeach
        </select>
        <noscript><button class="min-h-[40px] rounded-md bg-brand-blue px-4 text-[14px] font-semibold text-white">Show</button></noscript>
    </form>
    <p class="mt-2 text-[13px] text-brand-ink/65">Sessions from {{ $period['from']->format('j M Y') }} to {{ $period['to']->format('j M Y') }} (Malaysia time). An average is shown from {{ $report['needed'] }} reviews; a number from 1 to {{ $minGroup - 1 }} is shown as "fewer than {{ $minGroup }}".</p>

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="avg-title">
            <h2 id="avg-title" class="text-[18px] font-bold tracking-tight">Average</h2>
            <p class="mt-1 text-[13px] text-brand-ink/60">The average stars of all reviews of your counselors in this period.</p>
            <p class="mt-4 text-[34px] font-bold leading-none tabular-nums">{{ $report['average'] ?? 'Not enough reviews yet' }}</p>
            <p class="mt-2 text-[13px] text-brand-ink/65">Reviews in this period: <span class="font-semibold">{{ $report['reviews'] }}</span></p>
        </section>

        @include('organizations.reports._set', ['title' => 'How the stars are spread', 'about' => 'How many reviews gave each number of stars.', 'set' => $report['distribution']])
    </div>

    <section class="{{ $card }} mt-5" aria-labelledby="each-title">
        <h2 id="each-title" class="text-[18px] font-bold tracking-tight">Each counselor</h2>
        <p class="mt-1 text-[13px] text-brand-ink/60">A counselor's average is shown from {{ $report['needed'] }} reviews of their own. Fewer would show what one or two people thought.</p>

        @if (count($report['counselors']) > 0)
            <table class="mt-3 w-full text-left text-[14px]">
                <caption class="sr-only">Average per counselor</caption>
                <thead class="text-[12.5px] text-brand-ink/55"><tr><th scope="col" class="py-2 pr-3 font-semibold">Counselor</th><th scope="col" class="py-2 pr-3 text-right font-semibold">Reviews</th><th scope="col" class="py-2 text-right font-semibold">Average</th></tr></thead>
                <tbody class="divide-y divide-[#EEEDEA]">
                    @foreach ($report['counselors'] as $row)
                        <tr>
                            <th scope="row" class="py-2.5 pr-3 font-semibold">{{ $row['name'] }}</th>
                            <td class="py-2.5 pr-3 text-right tabular-nums">{{ \App\Support\ReportFigures::one($row['count']) }}</td>
                            <td class="py-2.5 text-right font-semibold tabular-nums">{{ $row['average'] ?? 'Not enough reviews' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="mt-3 text-[14px] text-brand-ink/65">No reviews yet. Clients are asked for a review the day after a session.</p>
        @endif
    </section>
</x-portal-layout>
