<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            // What the mobile app uses to name a counselor: random, so the number of counselors cannot be read from it
            // and nobody can walk through profiles by counting (1, 2, 3…). The row id never leaves the server.
            $table->char('public_id', 26)->nullable()->unique()->after('id');
        });

        DB::table('counselor_profiles')->whereNull('public_id')->orderBy('id')->each(function ($row) {
            DB::table('counselor_profiles')->where('id', $row->id)->update(['public_id' => strtolower((string) Str::ulid())]);
        });
    }

    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
