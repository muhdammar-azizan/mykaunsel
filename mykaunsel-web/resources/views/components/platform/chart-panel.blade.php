@props(['id', 'title', 'subtitle' => null])

{{-- One Analytics chart: a heading, a plain-text summary (for screen readers and for anyone who cannot see the chart), the chart, and a
     "View as table" toggle that shows the same numbers as a table. The table is the accessible twin of the chart. Slots: summary, table. --}}
<section {{ $attributes->class('rounded-[20px] border border-brand-ink/10 bg-white p-6') }} aria-labelledby="{{ $id }}-title" x-data="{ table: false }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-[15px] font-bold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-[12.5px] text-brand-ink/60">{{ $subtitle }}</p>
            @endif
        </div>
        <button type="button" @click="table = ! table" :aria-expanded="table.toString()" aria-controls="{{ $id }}-table"
                class="inline-flex min-h-[36px] items-center rounded-full border border-brand-ink/20 px-4 text-[12.5px] font-semibold text-brand-ink transition hover:bg-brand-ink/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue">
            <span x-text="table ? 'View as chart' : 'View as table'">View as table</span>
        </button>
    </div>

    <p class="mt-3 text-[13px] leading-snug text-brand-ink/80">{{ $summary }}</p>

    <div class="mt-4" x-show="! table">
        {{ $slot }}
    </div>

    <div id="{{ $id }}-table" class="mt-4 overflow-x-auto" x-show="table" x-cloak>
        {{ $tableView }}
    </div>
</section>
