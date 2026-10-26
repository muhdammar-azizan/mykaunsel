<?php

namespace App\Support;

/**
 * What an LKM CSV file would do, worked out without writing anything (see App\Services\LkmCsvImporter).
 *
 * A row is `new` (KB number not in the directory), `update` (it is, and something differs) or `unchanged`. Rows with a
 * problem are not in $rows at all: they are in $errors, one entry per problem.
 */
final class LkmImportPlan
{
    /**
     * @param  list<string>  $fileProblems  Problems with the file as a whole (not UTF-8, wrong columns, too big…). If any, nothing else is filled in.
     * @param  list<array{row: int, data: array<string, ?string>, action: 'new'|'update'|'unchanged', linked: bool, changes: list<string>}>  $rows
     * @param  list<array{row: int, field: string, reason: string, kb: ?string}>  $errors
     * @param  int  $totalRows  Records in the file (the header and blank lines do not count).
     */
    public function __construct(
        public readonly array $fileProblems,
        public readonly array $rows,
        public readonly array $errors,
        public readonly int $totalRows,
    ) {}

    public static function unusable(string ...$problems): self
    {
        return new self(array_values($problems), [], [], 0);
    }

    public function usable(): bool
    {
        return $this->fileProblems === [];
    }

    public function count(string $action): int
    {
        return count(array_filter($this->rows, fn (array $row) => $row['action'] === $action));
    }

    /** Rows with at least one problem. */
    public function errorRows(): int
    {
        return count(array_unique(array_column($this->errors, 'row')));
    }

    /** @return list<array{row: int, data: array<string, ?string>, action: string, linked: bool, changes: list<string>}> */
    public function writable(): array
    {
        return array_values(array_filter($this->rows, fn (array $row) => $row['action'] !== 'unchanged'));
    }

    /** Changed records that a MyKaunsel counselor account refers to. */
    public function linkedUpdates(): int
    {
        return count(array_filter($this->rows, fn (array $row) => $row['action'] === 'update' && $row['linked']));
    }

    /** "42 rows: 39 new, 3 updates, 0 errors" */
    public function summary(): string
    {
        $parts = [
            $this->count('new').' new',
            $this->count('update').' '.($this->count('update') === 1 ? 'update' : 'updates'),
        ];

        if ($this->count('unchanged') > 0) {
            $parts[] = $this->count('unchanged').' unchanged';
        }

        $errors = $this->errorRows();
        $parts[] = $errors.' '.($errors === 1 ? 'error' : 'errors');

        return $this->totalRows.' '.($this->totalRows === 1 ? 'row' : 'rows').': '.implode(', ', $parts);
    }

    /**
     * What the preview promised. If the directory changes before the admin confirms, this no longer matches and the
     * import asks for a new look instead of doing something different from what was shown.
     *
     * @return list<int>
     */
    public function signature(): array
    {
        return [$this->count('new'), $this->count('update'), $this->count('unchanged'), $this->errorRows()];
    }
}
