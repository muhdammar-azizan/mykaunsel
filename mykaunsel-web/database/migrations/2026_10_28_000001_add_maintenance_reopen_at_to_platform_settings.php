<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The time maintenance mode switches itself off (optional). Stored as an ISO-8601 instant in UTC; an empty string means
 * "no reopen time". Like every other setting it has a row from the start so it can be locked while it is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('platform_settings')->insertOrIgnore([
            'key' => 'maintenance.reopen_at',
            'value' => json_encode(''),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('platform_settings')->where('key', 'maintenance.reopen_at')->delete();
    }
};
