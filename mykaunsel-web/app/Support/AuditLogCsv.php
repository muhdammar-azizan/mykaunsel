<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/** Writes audit entries as CSV, safe to open in Excel / Sheets. */
class AuditLogCsv
{
    public function __construct(private string $timezone) {}

    /** @return list<string> */
    public function header(): array
    {
        return [
            'Entry ID', 'Time (UTC)', "Time ({$this->timezone})", 'Actor type', 'Actor user ID', 'Actor name', 'Actor role',
            'Action', 'Action label', 'Category', 'Target type', 'Target ID', 'Target name', 'Organization ID',
            'Details', 'Metadata (JSON)', 'IP', 'User agent', 'Request ID',
        ];
    }

    /** @return list<scalar|null> */
    public function row(AuditLog $log, AuditEntryPresenter $presenter): array
    {
        $def = AuditCatalogue::describe($log->action);

        return [
            $log->id,
            $log->created_at->copy()->utc()->format('Y-m-d H:i:s'),
            $log->created_at->copy()->timezone($this->timezone)->format('Y-m-d H:i:s'),
            $log->actor_type,
            $log->actor_user_id,
            $log->actor_name,
            $log->actor_role,
            $log->action,
            $def['label'],
            $log->category,
            $log->target_type,
            $log->target_id,
            $log->target_name,
            $log->organization_id,
            $presenter->summary($log),
            $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $log->ip,
            $log->user_agent,
            $log->request_id,
        ];
    }

    /**
     * Stream the query's rows to $out in chunks (never loading them all into memory).
     * Only rows up to $maxId are written, so the "export" entry the caller has just logged is not part of its own file.
     */
    public function write($out, Builder $query, int $maxId, int $chunk): void
    {
        // UTF-8 byte-order mark: Excel then reads accents and non-Latin names correctly.
        fwrite($out, "\xEF\xBB\xBF");
        $this->put($out, $this->header());

        $presenter = new AuditEntryPresenter($this->timezone);

        $query->where('id', '<=', $maxId)->chunk($chunk, function ($logs) use ($out, $presenter) {
            foreach ($logs as $log) {
                $this->put($out, $this->row($log, $presenter));
            }
        });
    }

    private function put($out, array $cells): void
    {
        fputcsv($out, array_map([self::class, 'neutralise'], $cells), ',', '"', '');
    }

    /**
     * CSV-injection guard: a text cell that a spreadsheet could read as a formula (starts with = + - @, or a tab / carriage
     * return, even after spaces) gets a leading apostrophe so it is shown as text. Numbers are left alone.
     */
    public static function neutralise(mixed $cell): mixed
    {
        if (! is_string($cell) || $cell === '') {
            return $cell;
        }

        return preg_match('/^[ ]*[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }
}
