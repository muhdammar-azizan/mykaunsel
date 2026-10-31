<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reference codes (docs/APP_SCREENS.md, "Decisions of the owner"): who made a code, and a way to switch it off without deleting it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_invite_codes', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('org_id')->constrained('users')->nullOnDelete();
            $table->dateTime('revoked_at')->nullable()->after('used_count');
        });
    }

    public function down(): void
    {
        Schema::table('org_invite_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('revoked_at');
        });
    }
};
