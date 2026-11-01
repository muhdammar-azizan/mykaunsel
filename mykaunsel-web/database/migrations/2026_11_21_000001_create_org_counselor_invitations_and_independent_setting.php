<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization assigns a counselor (docs/COUNSELORS.md, "Counselors of an organization"): it invites the counselor's e-mail address, and the
 * counselor accepts on the counselor portal. A separate table from the invitations of employees, so nothing about members changes.
 *
 * Also the organization's second setting (docs/PORTALS_PLAN.md): may its counselors also take independent clients on MyKaunsel. Yes by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_counselor_invitations', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
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

        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('counselors_may_practise_independently')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('counselors_may_practise_independently');
        });

        Schema::dropIfExists('org_counselor_invitations');
    }
};
