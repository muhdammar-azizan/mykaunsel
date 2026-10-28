<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An old invitation (accepted, expired or revoked) keeps no name and no email after the retention period
 * (config platform-admins.invitation.retention_days, 30 days): the row stays as an empty record, so both columns can be NULL.
 * personal_data_erased_at says when. See the command admin-invitations:purge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->string('name', 100)->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->dateTime('personal_data_erased_at')->nullable()->after('superseded_at');
        });
    }

    public function down(): void
    {
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->dropColumn('personal_data_erased_at');
        });
        // name and email stay nullable: erased rows cannot get their old values back.
    }
};
