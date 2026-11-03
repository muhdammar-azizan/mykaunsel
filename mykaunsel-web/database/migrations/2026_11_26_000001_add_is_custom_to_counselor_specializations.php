<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A specialization the counselor typed themselves ("Add your own") rather than picked from the list. It is shown on their profile, but it is NOT offered as a
 * search filter and does not count in the Platform Admin analytics until MyKaunsel makes it part of the list. Rows that already exist stay as they are (false).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_specializations', function (Blueprint $table) {
            $table->boolean('is_custom')->default(false)->after('specialization');
        });
    }

    public function down(): void
    {
        Schema::table('counselor_specializations', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};
