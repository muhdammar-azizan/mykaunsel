<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * A random public id for every calendar entry (slot or block). The mobile app names a slot with it, so the number of slots cannot be read
 * from the id and nobody can walk through slots by counting (1, 2, 3…). The row id never leaves the server.
 *
 * Safe to run again: the column is only added when missing, only rows without an id get one, in batches, with no model events and no audit
 * entries; then the column becomes required and unique. Existing rows keep working throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_entries', 'public_id')) {
            Schema::table('calendar_entries', function (Blueprint $table) {
                $table->char('public_id', 26)->nullable()->after('id');
            });
        }

        // Rows made before this migration (and rows a previous, interrupted run did not reach).
        DB::table('calendar_entries')->whereNull('public_id')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('calendar_entries')->where('id', $row->id)->whereNull('public_id')->update(['public_id' => strtolower((string) Str::ulid())]);
            }
        });

        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable(false)->change();
        });

        if (! $this->hasUniqueIndex()) {
            Schema::table('calendar_entries', function (Blueprint $table) {
                $table->unique('public_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
        });

        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });
    }

    private function hasUniqueIndex(): bool
    {
        return collect(Schema::getIndexes('calendar_entries'))->contains(fn (array $index) => $index['unique'] && $index['columns'] === ['public_id']);
    }
};
