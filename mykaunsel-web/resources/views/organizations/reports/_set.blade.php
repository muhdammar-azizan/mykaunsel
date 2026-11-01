{{-- One report: a title, what it means, and its figures with a bar each (a hidden figure has no bar). $set comes from ReportFigures::set(). --}}
@php
    $max = collect($set['cells'])->max(fn ($cell) => $cell['value'] ?? 0);
@endphp

<section class="rounded-xl border border-[#E4E2DE] bg-white p-6" aria-labelledby="{{ \Illuminate\Support\Str::slug($title) }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="{{ \Illuminate\Support\Str::slug($title) }}" class="text-[18px] font-bold tracking-tight">{{ $title }}</h2>
        <p class="text-[13px] text-brand-ink/65">Total <span class="font-semibold text-brand-ink">{{ $set['total'] }}</span>@if (isset($before)) <span class="text-brand-ink/50">· before {{ $before['total'] }}</span>@endif</p>
    </div>
    <p class="mt-1 text-[13px] text-brand-ink/60">{{ $about }}</p>

    <table class="mt-3 w-full text-left text-[14px]">
        <caption class="sr-only">{{ $title }}</caption>
        <tbody class="divide-y divide-[#EEEDEA]">
            @foreach ($set['cells'] as $label => $cell)
                <tr>
                    <th scope="row" class="w-[46%] py-2 pr-3 font-normal text-brand-ink/80">{{ $label }}</th>
                    <td class="py-2 pr-3"><span class="block h-2 rounded-full bg-brand-ink/10"><span class="block h-full rounded-full bg-brand-blue" style="width: {{ \App\Support\ReportFigures::bar($cell, $max) }}%"></span></span></td>
                    <td class="w-[110px] py-2 text-right font-semibold tabular-nums {{ $cell['hidden'] ? 'text-brand-ink/50' : '' }}">{{ $cell['label'] }}</td>
                    @if (isset($before) && ! ($series ?? false))
                        <td class="w-[110px] py-2 text-right tabular-nums text-brand-ink/55"><span class="sr-only">Before: </span>{{ $before['cells'][$label]['label'] ?? '0' }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
