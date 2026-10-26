<?php

namespace App\Services;

use App\Facades\Audit;
use App\Models\LkmDirectorySnapshot;
use App\Models\User;
use App\Support\LkmCsv;
use App\Support\LkmImportPlan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Reads an LKM Directory CSV file and, once the admin has seen the preview, saves it.
 *
 * - plan() only reads: nothing is written until commit().
 * - The file only CREATES or UPDATES records, matched by KB number. Nothing is ever deleted.
 * - commit() saves every row or none (one transaction), together with its audit entry.
 */
class LkmCsvImporter
{
    private const LOOKUP_CHUNK = 500;

    public function plan(string $contents): LkmImportPlan
    {
        $contents = $this->withoutByteOrderMark($contents);

        if (trim($contents) === '') {
            return LkmImportPlan::unusable('The file is empty.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            return LkmImportPlan::unusable('The file is not UTF-8. In Excel choose “Save as → CSV UTF-8 (comma delimited)”.');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $columns = $this->readHeader($stream);

            if (is_string($columns)) {
                return LkmImportPlan::unusable($columns);
            }

            $problems = $this->headerProblems($columns);

            if ($problems !== []) {
                return LkmImportPlan::unusable(...$problems);
            }

            return $this->planRows($stream, $columns);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Saves the rows of a plan in one transaction, with one audit entry (counts only, never the rows).
     * Rows that are `unchanged` are left alone; rows with errors are not in the plan.
     *
     * @return array{new: int, updated: int, unchanged: int, skipped: int}
     */
    public function commit(LkmImportPlan $plan, User $actor, bool $validOnly): array
    {
        if (! $plan->usable() || ($plan->errorRows() > 0 && ! $validOnly)) {
            throw new RuntimeException('This file cannot be imported as it is.');
        }

        $counts = ['new' => 0, 'updated' => 0, 'unchanged' => $plan->count('unchanged'), 'skipped' => $plan->errorRows()];
        $rows = $plan->writable();

        DB::transaction(function () use ($rows, $plan, $actor, $validOnly, &$counts) {
            $existing = $this->lockExisting(array_column(array_column($rows, 'data'), 'kb_number'));

            foreach ($rows as $row) {
                if ($row['action'] === 'new') {
                    LkmDirectorySnapshot::create($row['data']);
                    $counts['new']++;

                    continue;
                }

                $record = $existing[$row['data']['kb_number']] ?? throw new RuntimeException('A record in the file is no longer in the directory.');
                $record->update(Arr::except($row['data'], 'kb_number'));
                $counts['updated']++;
            }

            Audit::log('lkm.records_imported', null, [
                'rows' => $plan->totalRows,
                'new' => $counts['new'],
                'updated' => $counts['updated'],
                'unchanged' => $counts['unchanged'],
                'skipped' => $counts['skipped'],
                'linked_updated' => $plan->linkedUpdates(),
                'valid_rows_only' => $validOnly,
                'summary' => "Imported {$counts['new']} new, {$counts['updated']} updated"
                    .($counts['unchanged'] ? ", {$counts['unchanged']} unchanged" : '')
                    .($counts['skipped'] ? ", {$counts['skipped']} skipped (errors)" : ''),
            ], $actor);
        });

        return $counts;
    }

    // ---- reading -----------------------------------------------------------------------------

    private function withoutByteOrderMark(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    /** The cleaned column names, or a sentence saying what is wrong. @return list<string>|string */
    private function readHeader($stream): array|string
    {
        $cells = $this->nextRecord($stream);

        if ($cells === null) {
            return 'The file is empty.';
        }

        // Excel in some regions saves with semicolons: the whole header then looks like a single cell.
        if (count($cells) === 1 && str_contains((string) $cells[0], ';')) {
            return 'The columns are separated by semicolons. Save the file as “CSV UTF-8 (comma delimited)”.';
        }

        return array_map(fn ($cell) => strtolower(preg_replace('/[\s\-]+/', '_', trim((string) $cell))), $cells);
    }

    /** @param  list<string>  $columns @return list<string> */
    private function headerProblems(array $columns): array
    {
        $problems = [];

        $missing = array_values(array_diff(LkmCsv::REQUIRED, $columns));
        if ($missing !== []) {
            $problems[] = 'Missing column'.(count($missing) > 1 ? 's' : '').': '.implode(', ', $missing).'.';
        }

        $unknown = array_values(array_diff(array_unique($columns), LkmCsv::COLUMNS));
        if ($unknown !== []) {
            $problems[] = 'Unknown column'.(count($unknown) > 1 ? 's' : '').': '.implode(', ', array_map(fn ($c) => $c === '' ? '(blank)' : $c, $unknown)).'. Use only: '.implode(', ', LkmCsv::COLUMNS).'.';
        }

        if (count($columns) !== count(array_unique($columns))) {
            $problems[] = 'A column appears twice.';
        }

        return $problems;
    }

    /** The next record that is not a blank line, or null at the end. @return list<?string>|null */
    private function nextRecord($stream): ?array
    {
        while (($cells = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if ($cells !== [null]) {
                return $cells;
            }
        }

        return null;
    }

    // ---- checking ----------------------------------------------------------------------------

    /** @param  list<string>  $columns */
    private function planRows($stream, array $columns): LkmImportPlan
    {
        $max = (int) config('lkm.import.max_rows');
        $valid = [];
        $errors = [];
        $total = 0;
        $firstSeen = [];
        $row = 1; // the header is row 1, like in a spreadsheet

        while (($cells = $this->nextRecord($stream)) !== null) {
            $row++;
            $total++;

            if ($total > $max) {
                return LkmImportPlan::unusable('The file has more than '.number_format($max).' rows. Split it into smaller files.');
            }

            if (count($cells) > count($columns)) {
                $errors[] = ['row' => $row, 'field' => '(row)', 'reason' => 'has more values than the header has columns', 'kb' => null];

                continue;
            }

            $data = $this->clean(array_combine($columns, array_pad($cells, count($columns), null)));
            $rowErrors = $this->rowErrors($data);

            // The same KB number twice in one file: the first one counts, the later ones are errors.
            $kb = $data['kb_number'] ?? null;
            if ($rowErrors === [] && isset($firstSeen[$kb])) {
                $rowErrors[] = ['field' => 'kb_number', 'reason' => "appears again in this file (first on row {$firstSeen[$kb]})"];
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $error) {
                    $errors[] = ['row' => $row, 'field' => $error['field'], 'reason' => $error['reason'], 'kb' => $kb];
                }

                continue;
            }

            $firstSeen[$kb] = $row;
            $valid[] = ['row' => $row, 'data' => $data];
        }

        if ($total === 0) {
            return LkmImportPlan::unusable('The file has a header but no records.');
        }

        return new LkmImportPlan([], $this->classify($valid), $errors, $total);
    }

    /**
     * Trim, empty → null, KB/PA upper-case, status written the way the directory writes it.
     *
     * @param  array<string, ?string>  $data
     * @return array<string, ?string>
     */
    private function clean(array $data): array
    {
        $data = array_map(function ($value) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }, $data);

        foreach (['kb_number', 'pa_number'] as $key) {
            if ($data[$key] !== null) {
                $data[$key] = strtoupper($data[$key]);
            }
        }

        if ($data['status'] !== null) {
            $data['status'] = collect(LkmDirectorySnapshot::STATUSES)->first(fn ($label) => strcasecmp($label, $data['status']) === 0) ?? $data['status'];
        }

        // An optional column that is NOT in the file is left out here, so an update keeps what the record has.
        // (A column that is in the file with an empty cell does clear the value.)
        return $data;
    }

    /** @param  array<string, ?string>  $data @return list<array{field: string, reason: string}> */
    private function rowErrors(array $data): array
    {
        $validator = Validator::make($data, ['kb_number' => ['required', 'regex:/^KB\d{5}$/'], ...LkmDirectorySnapshot::fieldRules()], [
            'required' => 'is required',
            'min' => 'is too short',
            'max' => 'is too long',
            'email' => 'is not a valid email address',
            'kb_number.regex' => 'must be KB followed by 5 digits (KB12345)',
            'pa_number.regex' => 'must be PA followed by 5 digits (PA12345)',
            'status.in' => 'must be one of: '.implode(', ', LkmDirectorySnapshot::STATUSES),
            'date_format' => 'must be a real date written as YYYY-MM-DD (for example 2027-03-31)',
            'after_or_equal' => 'cannot be before pa_valid_from',
        ]);

        $failed = $validator->failed();
        $messages = $validator->errors()->messages();

        // "Valid until" cannot be compared with a "valid from" that is not a date: say only what is actually wrong.
        if (isset($failed['pa_valid_from']) && array_keys($failed['pa_valid_until'] ?? []) === ['AfterOrEqual']) {
            unset($messages['pa_valid_until']);
        }

        return collect($messages)
            ->map(fn (array $texts, string $field) => ['field' => $field, 'reason' => $texts[0]])
            ->values()->all();
    }

    /**
     * Says for every valid row whether it is new, changes a record or changes nothing.
     *
     * @param  list<array{row: int, data: array<string, ?string>}>  $valid
     * @return list<array{row: int, data: array<string, ?string>, action: string, linked: bool, changes: list<string>}>
     */
    private function classify(array $valid): array
    {
        $existing = collect();

        foreach (array_chunk(array_column(array_column($valid, 'data'), 'kb_number'), self::LOOKUP_CHUNK) as $kbs) {
            $existing = $existing->merge(LkmDirectorySnapshot::query()->withCount('counselorProfiles')->whereIn('kb_number', $kbs)->get()->keyBy('kb_number'));
        }

        return array_map(function (array $item) use ($existing) {
            /** @var LkmDirectorySnapshot|null $record */
            $record = $existing->get($item['data']['kb_number']);

            if ($record === null) {
                return $item + ['action' => 'new', 'linked' => false, 'changes' => []];
            }

            $changes = [];
            foreach (Arr::except($item['data'], 'kb_number') as $field => $value) {
                $old = $record->getAttribute($field);
                $old = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : $old;

                if ((string) $old !== (string) $value) {
                    $changes[] = $field;
                }
            }

            return $item + ['action' => $changes === [] ? 'unchanged' : 'update', 'linked' => $record->counselor_profiles_count > 0, 'changes' => $changes];
        }, $valid);
    }

    /**
     * The records a commit is about to change, locked so nobody else changes them meanwhile.
     *
     * @param  list<string>  $kbs
     * @return array<string, LkmDirectorySnapshot>
     */
    private function lockExisting(array $kbs): array
    {
        $found = [];

        foreach (array_chunk($kbs, self::LOOKUP_CHUNK) as $chunk) {
            foreach (LkmDirectorySnapshot::query()->whereIn('kb_number', $chunk)->lockForUpdate()->get() as $record) {
                $found[$record->kb_number] = $record;
            }
        }

        return $found;
    }
}
