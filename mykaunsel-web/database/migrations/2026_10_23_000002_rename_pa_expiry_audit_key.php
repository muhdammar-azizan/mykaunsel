<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The scheduled PA-expiry suspension is logged as counselor.pa_expired_suspended (it was briefly catalogued as
 * system.pa_expiry_suspension). Converts any rows written under the old key, once, with DB::table() — the same
 * way the earlier audit-key conversion did. Rows with a key this code no longer knows still display (fallback label).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convert();
    }

    public function convert(): int
    {
        return DB::table('audit_logs')
            ->where('action', 'system.pa_expiry_suspension')
            ->update(['action' => 'counselor.pa_expired_suspended', 'category' => 'counselors']);
    }

    public function down(): void
    {
        // Not reversed: the old key no longer exists in the catalogue.
    }
};
