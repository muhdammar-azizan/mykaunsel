<?php

namespace App\Support;

use App\Models\LkmDirectorySnapshot;
use Illuminate\Database\Eloquent\Builder;

/** The CSV files of the LKM Directory: the template, the export and the error report. Every cell goes through the injection guard. */
class LkmCsv
{
    /** The columns of an import file, in this order. The first six are required. */
    public const COLUMNS = ['kb_number', 'pa_number', 'full_name', 'status', 'pa_valid_from', 'pa_valid_until', 'email', 'city', 'state'];

    public const REQUIRED = ['kb_number', 'pa_number', 'full_name', 'status', 'pa_valid_from', 'pa_valid_until'];

    /** A header and one made-up example row (it is not a real person: delete it before importing). */
    public static function writeTemplate($out): void
    {
        self::start($out);
        self::put($out, self::COLUMNS);
        self::put($out, ['KB99999', 'PA99999', 'Example Counselor', 'Aktif', '2026-01-01', '2028-12-31', 'example@example.com', 'Kuantan', 'Pahang']);
    }

    /** The records of $query, in the same columns as an import file (so an export can be edited and imported again). */
    public static function writeExport($out, Builder $query, int $chunk): void
    {
        self::start($out);
        self::put($out, self::COLUMNS);

        $query->reorder('kb_number')->chunk($chunk, function ($records) use ($out) {
            foreach ($records as $record) {
                /** @var LkmDirectorySnapshot $record */
                self::put($out, [
                    $record->kb_number, $record->pa_number, $record->full_name, $record->status,
                    $record->pa_valid_from?->format('Y-m-d'), $record->pa_valid_until?->format('Y-m-d'),
                    $record->email, $record->city, $record->state,
                ]);
            }
        });
    }

    /** One line per problem: where it is and what is wrong, never the rest of the row. */
    public static function writeErrors($out, LkmImportPlan $plan): void
    {
        self::start($out);
        self::put($out, ['Row', 'KB number', 'Column', 'Problem']);

        foreach ($plan->errors as $error) {
            self::put($out, [$error['row'], $error['kb'], $error['field'], $error['reason']]);
        }
    }

    private static function start($out): void
    {
        // UTF-8 byte-order mark: Excel then reads accents and non-Latin names correctly.
        fwrite($out, "\xEF\xBB\xBF");
    }

    private static function put($out, array $cells): void
    {
        fputcsv($out, array_map([AuditLogCsv::class, 'neutralise'], $cells), ',', '"', '');
    }
}
