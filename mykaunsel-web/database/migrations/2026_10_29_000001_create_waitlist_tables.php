<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The waitlist for closed sign-ups (docs/WAITLIST.md).
 *
 * waitlist_entries      one row per address per kind of sign-up. Unique in the database, so two requests at the same moment
 *                       can never make two rows. `email_hash` is the keyed hash used to find a row from an opt-out link.
 * waitlist_suppressions people who opted out: ONLY a keyed hash (HMAC with the dedicated waitlist key), never the address.
 *                       Kept on purpose, after the address itself is gone, so the choice is respected if they sign up again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('email');
            $table->char('email_hash', 64);
            $table->string('status', 20)->default('waiting'); // waiting | notified
            $table->timestamp('notified_at')->nullable();
            // Set the moment the one email is handed to the mail system (once per claim), so a repeated job can never send twice.
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'email']);
            $table->index(['type', 'email_hash']);
            $table->index(['type', 'status']);
            $table->index('notified_at');
        });

        Schema::create('waitlist_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->char('email_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['type', 'email_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_suppressions');
        Schema::dropIfExists('waitlist_entries');
    }
};
