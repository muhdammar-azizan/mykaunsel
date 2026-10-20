<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_domains', function (Blueprint $table) {
            // Set when the automatic 7-day DNS check window ran out without the domain being verified.
            $table->dateTime('dns_check_expired_at')->nullable()->after('check_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('org_domains', function (Blueprint $table) {
            $table->dropColumn('dns_check_expired_at');
        });
    }
};
