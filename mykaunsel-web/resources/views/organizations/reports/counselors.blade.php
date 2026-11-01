@php $card = 'rounded-xl border border-[#E4E2DE] bg-white p-5'; @endphp

<x-portal-layout title="Reports: counselors">
    @include('organizations.reports._header')

    <section class="mt-5 grid gap-4 sm:grid-cols-3" aria-label="Capacity">
        <div class="{{ $card }}"><p class="text-[13px] text-brand-ink/65">Hours offered</p><p class="mt-1 text-[28px] font-bold leading-none tabular-nums">{{ $report['offered_hours'] }}</p><p class="mt-2 text-[12.5px] text-brand-ink/55">Hours your counselors opened for booking in this period.</p></div>
        <div class="{{ $card }}"><p class="text-[13px] text-brand-ink/65">Hours booked</p><p class="mt-1 text-[28px] font-bold leading-none tabular-nums">{{ $report['booked_hours'] }}</p><p class="mt-2 text-[12.5px] text-brand-ink/55">Of those, hours with a session that was not cancelled.</p></div>
        <div class="{{ $card }}"><p class="text-[13px] text-brand-ink/65">Share of hours booked</p><p class="mt-1 text-[28px] font-bold leading-none tabular-nums">{{ $report['booked_share'] ?? 'Not shown' }}</p><p class="mt-2 text-[12.5px] text-brand-ink/55">{{ $report['booked_share'] ? 'Hours booked as a share of hours offered.' : 'Shown from '.$minGroup.' sessions in the period.' }}</p></div>
    </section>

    <section class="{{ $card }} mt-5 !p-6" aria-labelledby="each-title">
        <h2 id="each-title" class="text-[18px] font-bold tracking-tight">Each counselor</h2>
        <p class="mt-1 text-[13px] text-brand-ink/60">The sessions of one counselor are a small group, so a number under {{ $minGroup }} is hidden, and the share booked is shown only when it is not. The first free time is the next time this counselor has open for your organization.</p>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-[14px]">
                <caption class="sr-only">Counselors of your organization</caption>
                <thead class="text-[12.5px] text-brand-ink/55">
                    <tr>
                        <th scope="col" class="py-2 pr-3 font-semibold">Counselor</th>
                        <th scope="col" class="py-2 pr-3 text-right font-semibold">Hours offered</th>
                        <th scope="col" class="py-2 pr-3 text-right font-semibold">Sessions</th>
                        <th scope="col" class="py-2 pr-3 text-right font-semibold">Share booked</th>
                        <th scope="col" class="py-2 font-semibold">First free time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EEEDEA]">
                    @forelse ($report['list'] as $row)
                        <tr>
                            <th scope="row" class="py-2.5 pr-3 font-semibold">{{ $row['name'] }}</th>
                            <td class="py-2.5 pr-3 text-right tabular-nums">{{ $row['offered_hours'] }}</td>
                            <td class="py-2.5 pr-3 text-right tabular-nums">{{ $row['sessions'] }}</td>
                            <td class="py-2.5 pr-3 text-right tabular-nums">{{ $row['booked_share'] ?? '—' }}</td>
                            <td class="py-2.5">{{ $row['first_free'] ?? 'Nothing open' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-brand-ink/65">No counselors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-[13px] text-brand-ink/65">Sessions in total: <span class="font-semibold">{{ $report['sessions_total'] }}</span></p>
    </section>
</x-portal-layout>
