<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A counselor can cancel a session on the web portal (docs/PORTALS_PLAN.md). The booking keeps who cancelled it and, for a counselor, the short
 * reason written for the client. Bookings cancelled before this existed have nothing in these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancelled_by', 12)->nullable();        // client | counselor | system (only counselor is written so far)
            $table->string('cancel_reason', 300)->nullable();
            $table->dateTime('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_by', 'cancel_reason', 'cancelled_at']);
        });
    }
};
