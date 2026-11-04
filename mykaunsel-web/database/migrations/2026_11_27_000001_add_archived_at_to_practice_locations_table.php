<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A place a counselor removed after it was used (a time, a weekly line, a booking or a request points at it) is archived, not deleted: the
 * history keeps pointing at it. An archived place is never offered for new times. Safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('practice_locations', 'archived_at')) {
            Schema::table('practice_locations', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable()->after('is_primary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('practice_locations', 'archived_at')) {
            Schema::table('practice_locations', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
