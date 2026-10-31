<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E-mail invitations to an organization (docs/APP_SCREENS.md, "Decisions of the owner", 1): matched on the person's VERIFIED e-mail address. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_member_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('org_id')->constrained('organizations');
            $table->string('email');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('declined_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'org_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_member_invitations');
    }
};
