@php
    $writable = $plan?->usable() ? count($plan->writable()) : 0;
    $errorRows = $plan?->usable() ? $plan->errorRows() : 0;
    $updates = $plan?->usable() ? collect($plan->rows)->where('action', 'update') : collect();
    $checkbox = 'mt-0.5 h-4 w-4 rounded border-brand-ink/30 text-brand-blue focus:ring-brand-blue';
@endphp

<x-platform-layout title="Import LKM records">
    <a href="{{ route('platform.lkm') }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-brand-ink/60 transition hover:text-brand-blue">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        LKM Directory
    </a>

    <x-platform.page-header class="mt-3" title="Import LKM records" subtitle="Add or update many records from a CSV file. You see what it would do before anything is saved." />

    <x-platform.lkm-simulated-note class="mt-4" />

    @if (session('error'))
        <div class="mt-4 rounded-2xl border border-brand-red/30 bg-brand-red/10 px-5 py-3 text-[13px] font-semibold text-brand-red" role="alert">{{ session('error') }}</div>
    @endif

    @if (session('import_problems') || $errors->any())
        <div class="mt-4 rounded-2xl border border-brand-red/30 bg-brand-red/10 px-5 py-3 text-[13px] text-brand-red" role="alert">
            <p class="font-bold">The file was not accepted:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ((array) session('import_problems') as $problem)<li>{{ $problem }}</li>@endforeach
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-platform.panel title="1. Prepare the file">
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-[13.5px] text-brand-ink/75">
                <li>A CSV file in UTF-8, with commas. In Excel: Save as → CSV UTF-8 (comma delimited).</li>
                <li>Up to {{ rtrim(rtrim(number_format($max['max_bytes'] / 1048576, 1), '0'), '.') }} MB and {{ number_format($max['max_rows']) }} rows.</li>
                <li>
                    The first row names the columns:
                    @foreach ($columns as $column)<code class="rounded bg-brand-ink/[.06] px-1.5 py-0.5 text-[12px] {{ in_array($column, $required, true) ? 'font-bold' : '' }}">{{ $column }}</code>@endforeach.
                    The bold ones are required.
                </li>
                <li>Dates are written <code class="rounded bg-brand-ink/[.06] px-1.5 py-0.5 text-[12px]">YYYY-MM-DD</code>, for example 2027-03-31. Status is Aktif, Tidak Aktif or Digantung.</li>
                <li>A KB number that is already in the directory <strong>updates</strong> that record. A new KB number <strong>creates</strong> one. Nothing is ever deleted, and a KB number is never changed.</li>
                <li>An empty cell clears that value. Leave an optional column (email, city, state) out of the file to keep what the record has.</li>
            </ul>
            <a href="{{ route('platform.lkm.import.template') }}" class="mt-4 inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Download the template</a>
            <p class="mt-2 text-[12px] text-brand-ink/50">The template has one made-up example row. Delete it before importing.</p>
        </x-platform.panel>

        <x-platform.panel title="2. Upload and preview">
            <form method="POST" action="{{ route('platform.lkm.import.stage') }}" enctype="multipart/form-data" class="mt-3"
                  x-data="{ ok: false, name: '', over: false, busy: false }" @submit="busy = true">
                @csrf
                <label @dragover.prevent="over = true" @dragleave.prevent="over = false"
                       @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; name = $refs.file.files[0]?.name ?? ''"
                       :class="over ? 'border-brand-blue bg-brand-blue/5' : 'border-brand-ink/20'"
                       class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed px-4 py-8 text-center transition">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-brand-blue" aria-hidden="true"><path d="M12 16V4m0 0 4 4m-4-4L8 8M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                    <span class="text-[13.5px] font-semibold" x-text="name || 'Drop a CSV file here, or click to choose'">Drop a CSV file here, or click to choose</span>
                    <input x-ref="file" type="file" name="file" accept=".csv,text/csv" class="sr-only" required @change="name = $refs.file.files[0]?.name ?? ''">
                </label>

                <label class="mt-4 flex items-start gap-2.5 text-[13px]">
                    <input type="checkbox" name="confirm_simulated" value="1" x-model="ok" class="{{ $checkbox }}">
                    <span>This file contains simulated or test records only, not copied from the real LKM directory.</span>
                </label>

                <button type="submit" :disabled="! ok || ! name || busy" class="mt-4 inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">Preview import</button>
                <p class="mt-2 text-[12px] text-brand-ink/50">Previewing saves nothing.</p>
            </form>
        </x-platform.panel>
    </div>

    @if ($plan?->usable())
        <x-platform.panel class="mt-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-[14px] font-bold">3. Preview: <span class="font-mono text-[13px]">{{ $fileName }}</span></h2>
                <p class="text-[12.5px] text-brand-ink/55">Nothing is saved yet.</p>
            </div>

            <p class="mt-3 text-[20px] font-extrabold tracking-tightest" role="status">{{ $plan->summary() }}</p>
            @if ($plan->count('unchanged') > 0)
                <p class="mt-1 text-[12.5px] text-brand-ink/55">Unchanged rows are already in the directory exactly like this and are left alone.</p>
            @endif

            @if ($plan->linkedUpdates() > 0)
                <p class="mt-3 rounded-xl bg-brand-yellow/20 px-4 py-2.5 text-[13px] text-[#6B4C0C]">
                    {{ $plan->linkedUpdates() }} of the updates change a record that a counselor account refers to. The account is not changed automatically; it shows in the next LKM check.
                </p>
            @endif

            @if ($updates->isNotEmpty())
                <h3 class="mt-5 text-[13px] font-bold">Records that will change</h3>
                <x-platform.table :headings="['Row', 'KB number', 'Name', 'What changes', 'On MyKaunsel']">
                    @foreach ($updates->take($shown['updates']) as $row)
                        <tr class="border-t border-brand-ink/[.07]">
                            <td class="px-4 py-2.5 text-[13px] text-brand-ink/60">{{ $row['row'] }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12.5px]">{{ $row['data']['kb_number'] }}</td>
                            <td class="px-4 py-2.5 text-[13.5px]">{{ $row['data']['full_name'] }}</td>
                            <td class="px-4 py-2.5 text-[12.5px] text-brand-ink/70">{{ implode(', ', $row['changes']) }}</td>
                            <td class="px-4 py-2.5 text-[13px]">{{ $row['linked'] ? 'Yes' : 'No' }}</td>
                        </tr>
                    @endforeach
                </x-platform.table>
                @if ($updates->count() > $shown['updates'])
                    <p class="mt-2 text-[12.5px] text-brand-ink/55">…and {{ $updates->count() - $shown['updates'] }} more.</p>
                @endif
            @endif

            @if ($errorRows > 0)
                <div class="mt-5 flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="text-[13px] font-bold text-brand-red">{{ $errorRows }} {{ $errorRows === 1 ? 'row has' : 'rows have' }} errors</h3>
                    <a href="{{ route('platform.lkm.import.errors') }}" class="text-[13px] font-semibold text-brand-blue hover:underline">Download the error report (CSV)</a>
                </div>
                <x-platform.table :headings="['Row', 'KB number', 'Column', 'Problem']">
                    @foreach (array_slice($plan->errors, 0, $shown['errors']) as $error)
                        <tr class="border-t border-brand-ink/[.07]">
                            <td class="px-4 py-2.5 text-[13px] text-brand-ink/60">{{ $error['row'] }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12.5px]">{{ $error['kb'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 font-mono text-[12.5px]">{{ $error['field'] }}</td>
                            <td class="px-4 py-2.5 text-[13px]">{{ $error['reason'] }}</td>
                        </tr>
                    @endforeach
                </x-platform.table>
                @if (count($plan->errors) > $shown['errors'])
                    <p class="mt-2 text-[12.5px] text-brand-ink/55">Showing the first {{ $shown['errors'] }} of {{ count($plan->errors) }} problems. The report has all of them.</p>
                @endif
            @endif

            <form id="cancel-import" method="POST" action="{{ route('platform.lkm.import.cancel') }}" class="hidden">@csrf @method('DELETE')</form>

            <div class="mt-6 border-t border-brand-ink/10 pt-5">
                @if ($writable === 0)
                    <p class="text-[13.5px] text-brand-ink/70">There is nothing to import: no row is new or different from the directory.</p>
                    <button type="submit" form="cancel-import" class="mt-3 inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Clear this file</button>
                @else
                    <form method="POST" action="{{ route('platform.lkm.import.commit') }}" x-data="{ ok: false, busy: false }" @submit="busy = true">
                        @csrf
                        @if ($errorRows > 0)
                            <input type="hidden" name="valid_only" value="1">
                            <p class="mb-3 text-[13px] text-brand-ink/70">Rows with errors are skipped. The other rows are saved together, or not at all.</p>
                        @else
                            <p class="mb-3 text-[13px] text-brand-ink/70">All rows are saved together, or not at all.</p>
                        @endif

                        <label class="flex items-start gap-2.5 text-[13px]">
                            <input type="checkbox" name="confirm_simulated" value="1" x-model="ok" class="{{ $checkbox }}">
                            <span>This file contains simulated or test records only, not copied from the real LKM directory.</span>
                        </label>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="submit" :disabled="! ok || busy" class="inline-flex min-h-[42px] items-center rounded-full bg-brand-blue px-6 text-[13.5px] font-semibold text-white transition hover:bg-brand-deep disabled:cursor-not-allowed disabled:opacity-50">
                                {{ $errorRows > 0 ? "Import valid rows only ({$writable})" : "Import {$writable} ".($writable === 1 ? 'row' : 'rows') }}
                            </button>
                            <button type="submit" form="cancel-import" class="inline-flex min-h-[42px] items-center rounded-full border border-brand-ink/20 px-5 text-[13.5px] font-semibold transition hover:bg-brand-ink/5">Cancel</button>
                        </div>
                    </form>
                @endif
            </div>
        </x-platform.panel>
    @endif

    <x-platform.toast />
</x-platform-layout>
