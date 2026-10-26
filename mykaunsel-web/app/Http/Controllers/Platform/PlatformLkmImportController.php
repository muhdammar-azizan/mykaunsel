<?php

namespace App\Http\Controllers\Platform;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\LkmDirectorySnapshot;
use App\Services\LkmCsvImporter;
use App\Support\LkmCsv;
use App\Support\LkmDirectoryFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Platform Admin → LKM Directory → Import / Export (CSV).
 *
 * Import is two steps: the file is checked and previewed (nothing is written), then the admin confirms. The file waits on a
 * private disk under a random name in between. Importing only creates or updates; it never deletes.
 */
class PlatformLkmImportController extends Controller
{
    private const SESSION_KEY = 'lkm_import';

    public function show(Request $request, LkmCsvImporter $importer): View
    {
        $plan = null;
        $staged = $this->staged($request);

        if ($staged !== null) {
            $plan = $importer->plan((string) $this->disk()->get($this->path($staged['token'])));
        }

        return view('platform.lkm.import', [
            'plan' => $plan,
            'fileName' => $staged['name'] ?? null,
            'columns' => LkmCsv::COLUMNS,
            'required' => LkmCsv::REQUIRED,
            'max' => config('lkm.import'),
            'shown' => ['errors' => (int) config('lkm.import.errors_shown'), 'updates' => (int) config('lkm.import.updates_shown')],
        ]);
    }

    /** Step 1: read the file and show what it would do. Nothing is saved. */
    public function stage(Request $request, LkmCsvImporter $importer): RedirectResponse
    {
        $request->validate([
            'confirm_simulated' => ['accepted'],
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:'.(int) ceil(config('lkm.import.max_bytes') / 1024)],
        ], [
            'confirm_simulated.accepted' => 'Tick the box to confirm the file has simulated or test records only.',
            'file.required' => 'Choose a CSV file.',
            'file.uploaded' => 'The file could not be uploaded. Is it larger than '.$this->megabytes().' MB?',
            'file.extensions' => 'The file must be a .csv file.',
            'file.max' => 'The file is larger than '.$this->megabytes().' MB.',
        ]);

        $upload = $request->file('file');
        $plan = $importer->plan((string) file_get_contents($upload->getRealPath()));

        $this->forget($request);

        if (! $plan->usable()) {
            return redirect()->route('platform.lkm.import')->with('import_problems', $plan->fileProblems);
        }

        $this->sweepOldFiles();

        $token = (string) Str::ulid();
        $this->disk()->put($this->path($token), file_get_contents($upload->getRealPath()));
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'name' => Str::limit(basename((string) $upload->getClientOriginalName()), 100, '…'),
            'signature' => $plan->signature(),
        ]);

        return redirect()->route('platform.lkm.import');
    }

    /** Step 2: save what the preview showed, all of it or nothing. */
    public function commit(Request $request, LkmCsvImporter $importer): RedirectResponse
    {
        $request->validate(['confirm_simulated' => ['accepted'], 'valid_only' => ['nullable', 'boolean']], [
            'confirm_simulated.accepted' => 'Tick the box to confirm the file has simulated or test records only.',
        ]);

        $staged = $this->staged($request);

        if ($staged === null) {
            return redirect()->route('platform.lkm.import')->with('error', 'There is no file waiting. Upload it again.');
        }

        $plan = $importer->plan((string) $this->disk()->get($this->path($staged['token'])));
        $validOnly = $request->boolean('valid_only');

        if (! $plan->usable()) {
            $this->forget($request);

            return redirect()->route('platform.lkm.import')->with('import_problems', $plan->fileProblems);
        }

        if ($plan->errorRows() > 0 && ! $validOnly) {
            return back()->with('error', 'The file has rows with errors. Fix the file, or choose “Import valid rows only”.');
        }

        // Someone changed the directory since the preview: show the new numbers instead of doing something else than was shown.
        if ($plan->signature() !== $staged['signature']) {
            $request->session()->put(self::SESSION_KEY, [...$staged, 'signature' => $plan->signature()]);

            return back()->with('error', 'The directory changed since the preview. Check the numbers below and confirm again.');
        }

        if ($plan->writable() === []) {
            return back()->with('error', 'Nothing to import: no row is new or different from the directory.');
        }

        try {
            $counts = $importer->commit($plan, $request->user(), $validOnly);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Nothing was saved. Something changed while importing (or the database refused a row). Check the file and try again.');
        }

        $this->forget($request);

        return redirect()->route('platform.lkm')->with('status', "Import done: {$counts['new']} new, {$counts['updated']} updated"
            .($counts['unchanged'] ? ", {$counts['unchanged']} unchanged" : '').($counts['skipped'] ? ", {$counts['skipped']} skipped because of errors" : '').'.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->forget($request);

        return redirect()->route('platform.lkm.import')->with('status', 'Import cancelled. Nothing was saved.');
    }

    /** The problems of the file waiting for confirmation, as a CSV. */
    public function errors(Request $request, LkmCsvImporter $importer): StreamedResponse|RedirectResponse
    {
        $staged = $this->staged($request);

        if ($staged === null) {
            return redirect()->route('platform.lkm.import');
        }

        $plan = $importer->plan((string) $this->disk()->get($this->path($staged['token'])));

        return $this->download('lkm-import-errors_'.now()->format('Ymd_Hi').'.csv', fn ($out) => LkmCsv::writeErrors($out, $plan));
    }

    public function template(): StreamedResponse
    {
        return $this->download('lkm-directory-template.csv', fn ($out) => LkmCsv::writeTemplate($out));
    }

    /** The records the list currently shows (same search and filters), as a CSV. The export is recorded in the audit log. */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filters = LkmDirectoryFilters::fromRequest($request);
        $query = $filters->apply(LkmDirectorySnapshot::query());

        // Count BEFORE streaming: once a download has started there is no way to say "too many".
        $total = (clone $query)->count();
        $max = (int) config('lkm.export_max_rows');

        if ($total > $max) {
            return redirect()->route('platform.lkm', $filters->toQuery())
                ->with('error', 'That is '.number_format($total).' records — more than the '.number_format($max).' an export can hold. Narrow the filters and try again.');
        }

        DB::transaction(fn () => Audit::log('lkm.records_exported', null, [
            'format' => 'csv',
            'rows' => $total,
            'filters' => $filters->isFiltering() ? array_diff_key($filters->toQuery(), ['sort' => 1]) : 'none',
            'summary' => "Exported {$total} ".($total === 1 ? 'record' : 'records'),
        ], $request->user()));

        return $this->download('lkm-directory_'.now()->format('Ymd_Hi').'.csv', fn ($out) => LkmCsv::writeExport($out, $query, (int) config('lkm.export_chunk')));
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function download(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            $write($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('lkm.import.disk'));
    }

    private function path(string $token): string
    {
        return $token.'.csv';
    }

    private function megabytes(): string
    {
        return rtrim(rtrim(number_format(config('lkm.import.max_bytes') / 1048576, 1), '0'), '.');
    }

    /**
     * The file waiting for this admin, or null (and the stale session entry is dropped) if there is none or it is gone.
     *
     * @return array{token: string, name: string, signature: list<int>}|null
     */
    private function staged(Request $request): ?array
    {
        $staged = $request->session()->get(self::SESSION_KEY);

        if (is_array($staged) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', (string) ($staged['token'] ?? '')) === 1 && $this->disk()->exists($this->path($staged['token']))) {
            return $staged;
        }

        $request->session()->forget(self::SESSION_KEY);

        return null;
    }

    private function forget(Request $request): void
    {
        $staged = $request->session()->pull(self::SESSION_KEY);

        if (is_array($staged) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', (string) ($staged['token'] ?? '')) === 1) {
            $this->disk()->delete($this->path($staged['token']));
        }
    }

    /** Files nobody confirmed or cancelled (the admin closed the tab) are removed after a day. */
    private function sweepOldFiles(): void
    {
        $limit = now()->subHours((int) config('lkm.import.keep_hours'))->getTimestamp();

        foreach ($this->disk()->files() as $file) {
            if ($this->disk()->lastModified($file) < $limit) {
                $this->disk()->delete($file);
            }
        }
    }
}
