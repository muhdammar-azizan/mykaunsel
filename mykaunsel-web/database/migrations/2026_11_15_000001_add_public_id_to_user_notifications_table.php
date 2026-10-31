<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Notifications are now also shown in the mobile app, which never sees a counter: every row gets a random 26 character public id (set the moment it
 * is inserted, see UserNotification). Rows that exist already (Platform Admin notifications) get one here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
        });

        DB::table('user_notifications')->whereNull('public_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('user_notifications')->where('id', $row->id)->update(['public_id' => strtolower((string) Str::ulid())]);
            }
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
