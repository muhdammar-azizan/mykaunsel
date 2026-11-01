@php $card = 'rounded-xl border border-[#E4E2DE] bg-white p-6'; @endphp

<x-portal-layout title="Reports: quality">
    @include('organizations.reports._header')

    <div class="mt-5 grid gap-5 xl:grid-cols-2">
        <section class="{{ $card }}" aria-labelledby="rating-title">
            <h2 id="rating-title" class="text-[18px] font-bold tracking-tight">What clients say</h2>
            <p class="mt-1 text-[13px] text-brand-ink/60">The average of the stars clients gave after sessions with your counselors. You see stars only, never the comments: those are read only by the counselor and the MyKaunsel team. An average is shown from {{ $report['needed'] }} reviews.</p>

            <p class="mt-4 text-[34px] font-bold leading-none tabular-nums">{{ $report['average'] ?? 'Not enough reviews yet' }}</p>
            <p class="mt-2 text-[13px] text-brand-ink/65">Reviews in this period: <span class="font-semibold">{{ $report['reviews'] }}</span></p>

            @if (count($report['counselors']) > 0)
                <table class="mt-4 w-full text-left text-[14px]">
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
            @endif
        </section>

        <section class="{{ $card }} self-start" aria-labelledby="cancel-title">
            <h2 id="cancel-title" class="text-[18px] font-bold tracking-tight">Cancelling</h2>
            <p class="mt-1 text-[13px] text-brand-ink/60">The share of sessions in this period that were cancelled. It is shown from {{ $minGroup }} sessions.</p>
            <p class="mt-4 text-[34px] font-bold leading-none tabular-nums">{{ $report['cancel_rate'] ?? 'Not shown' }}</p>
            <p class="mt-2 text-[13px] text-brand-ink/65">Sessions in this period: <span class="font-semibold">{{ $report['sessions'] }}</span></p>
        </section>

        @include('organizations.reports._set', ['title' => 'Who cancelled', 'about' => 'Cancelled sessions by who cancelled them.', 'set' => $report['cancelled_by']])
    </div>
</x-portal-layout>
