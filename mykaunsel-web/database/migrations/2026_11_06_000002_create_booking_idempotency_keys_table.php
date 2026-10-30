<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Remembers a booking request by its Idempotency-Key, so a retry of the SAME request (a bad network) never books twice. Holds the answer of
 * a successful booking for a day. Never holds a token or request headers; the request itself is kept only as a hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('key', 36);
            $table->char('fingerprint', 64);
            $table->string('status', 16); // processing | completed
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_idempotency_keys');
    }
};
