<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The in-app inbox: one row per recipient per event. Plain columns (no JSON), so it filters, counts, de-duplicates and purges the same on
 * SQLite and MySQL/MariaDB. The recipient is a plain user id, so the same table can serve other roles later; only Platform Admins use it now.
 *
 * It holds a type key, a target (kind + id), a SHORT label (an organization name, a counselor name or a complaint reference such as "#12"),
 * a count and timestamps. Never a URL (the route is worked out on the server from the type key and the target id), never complaint text,
 * notes, reasons, document names, emails or KB numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60);                                  // dotted key from the registry, e.g. organization.application_submitted
            $table->string('target_type', 40)->nullable();               // organization | counselor_profile | lkm_review_request | complaint | user
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('label', 150)->nullable();
            $table->string('occurrence_key', 120);                       // one row per recipient per event occurrence
            $table->unsignedInteger('count')->default(1);                // how many times it happened (coalesced types)
            $table->dateTime('occurred_at');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            // A retried job cannot create the same notification twice.
            $table->unique(['recipient_user_id', 'type', 'occurrence_key'], 'un_notifications_occurrence');
            // The bell's unread count and the lists (indexed count query).
            $table->index(['recipient_user_id', 'read_at', 'occurred_at'], 'ix_notifications_inbox');
            // Coalescing: the latest row of one type about one target for one recipient.
            $table->index(['recipient_user_id', 'type', 'target_id', 'created_at'], 'ix_notifications_coalesce');
            // Retention: read and unread rows by age.
            $table->index(['read_at', 'occurred_at'], 'ix_notifications_retention');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
