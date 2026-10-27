<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations to become a Platform Admin. Nothing about the person changes until they accept.
 *
 * - token_hash: SHA-256 of a random 256-bit token. The token itself is only in the emailed link.
 * - active_email: the email while the invitation is pending, otherwise NULL; its unique index is what makes "one live
 *   invitation per email" hold even when two admins invite the same address at the same moment.
 * - The invitation is not a user and not a membership: the sender is a plain id (kept if they are removed later, so the
 *   page can still say who sent it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->string('active_email')->nullable()->unique();
            $table->string('status', 16)->default('pending');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->unsignedSmallInteger('sent_count')->default(1);
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
    }
};
