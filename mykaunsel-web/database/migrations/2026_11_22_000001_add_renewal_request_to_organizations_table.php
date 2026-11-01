<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An organization admin can ask MyKaunsel to renew the subscription (Subscription page). The request is a flag on the organization: set when asked,
 * cleared when MyKaunsel gives a new plan. Nothing is charged by asking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dateTime('renewal_requested_at')->nullable();
            $table->foreignId('renewal_requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewal_requested_by');
            $table->dropColumn('renewal_requested_at');
        });
    }
};
