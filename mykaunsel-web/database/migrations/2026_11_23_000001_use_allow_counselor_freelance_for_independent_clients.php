<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The organization's setting "may our counselors also take independent clients" already existed as `organizations.allow_counselor_freelance` (never
 * used, off). One column is enough: the copy `counselors_may_practise_independently` made on 21 November is dropped, and the old column carries the
 * setting, on by default (so no counselor who works independently today is turned away). Every organization is switched on, because nobody ever chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('organizations', 'counselors_may_practise_independently')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('counselors_may_practise_independently');
            });
        }

        DB::table('organizations')->update(['allow_counselor_freelance' => true]);

        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('allow_counselor_freelance')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('allow_counselor_freelance')->default(false)->change();
        });
    }
};
