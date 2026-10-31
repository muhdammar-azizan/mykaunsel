<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deleted account is not removed: what identifies the person is erased and the row stays as an anonymous stub, because bookings, session
 * notes and complaints still point at it (docs/ACCOUNT_DELETION.md). `deleted_at` marks the stub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('deleted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropColumn('deleted_at');
        });
    }
};
