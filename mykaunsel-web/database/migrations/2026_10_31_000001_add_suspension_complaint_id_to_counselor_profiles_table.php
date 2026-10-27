<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * When a counselor is suspended FROM A COMPLAINT, the reason the admin wrote is kept only on the profile (suspension_reason)
 * and no longer copied into the audit log. This says which complaint it came from, so the audit entry can point to it and the
 * complaint retention rule can erase the reason together with the rest of that complaint's text. Cleared on reactivation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->foreignId('suspension_complaint_id')->nullable()->after('suspension_source')->constrained('complaints')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspension_complaint_id');
        });
    }
};
