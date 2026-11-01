<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session notes get their text (docs/SESSION_NOTES.md). The table existed before with two unused columns; it is empty everywhere, so a note is now one
 * row per booking, written by one counselor. `body` holds the ENCRYPTED text (see the model cast), never readable in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->unique();
            $table->foreignId('counselor_user_id')->nullable()->constrained('users');
            $table->text('body')->nullable();
            $table->string('attendance')->nullable()->change();
            $table->unique('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('session_notes', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
            $table->dropConstrainedForeignId('counselor_user_id');
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'body']);
        });
    }
};
