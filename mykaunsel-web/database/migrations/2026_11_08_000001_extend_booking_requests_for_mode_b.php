<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Mode B ("ask for a time"): what a request needs beyond the original table. A random public id (never a counter), the organization the
 * time would be held for, how long, online or in person and where (all taken from the counselor's weekly hours line the time falls in), the
 * answer (an optional reason and when), and the booking made when the counselor approves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable()->after('id');
            $table->foreignId('context_org_id')->nullable()->after('counselor_user_id')->constrained('organizations');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('requested_time');
            $table->string('session_mode', 16)->nullable()->after('duration_minutes');
            $table->foreignId('location_id')->nullable()->after('session_mode')->constrained('practice_locations')->nullOnDelete();
            $table->text('response_reason')->nullable()->after('message');
            $table->dateTime('responded_at')->nullable()->after('expires_at');
            $table->foreignId('booking_id')->nullable()->after('responded_at')->constrained('bookings')->nullOnDelete();
        });

        // Rows made before this migration.
        DB::table('booking_requests')->whereNull('public_id')->orderBy('id')->select('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('booking_requests')->where('id', $row->id)->whereNull('public_id')->update(['public_id' => strtolower((string) Str::ulid())]);
            }
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->char('public_id', 26)->nullable(false)->change();
            $table->unique('public_id');
            $table->index(['counselor_user_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropIndex(['counselor_user_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropUnique(['public_id']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('context_org_id');
            $table->dropColumn(['public_id', 'duration_minutes', 'session_mode', 'response_reason', 'responded_at']);
        });
    }
};
