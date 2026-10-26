<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Complaints (docs/COMPLAINTS.md):
 *
 *  - What an admin WRITES about a complaint no longer goes into the audit log. The closing reason lives on the complaint,
 *    each warning's message in complaint_warnings, and a suspension's reason on the counselor profile (as before). The audit
 *    log keeps only the kind of action, the old and new status and ids.
 *  - Retention: the text (what was reported, the closing reason, the warning messages) is erased some years after the
 *    complaint was closed, while the record itself stays: that needs the day it was closed and a mark that the text is gone.
 *    The text columns therefore may be empty.
 *  - A reporter who deletes their account no longer blocks the complaint: the link to them becomes empty ("Deleted user").
 *    The counselor link keeps its restriction on purpose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->text('closing_reason')->nullable()->after('status');
            $table->timestamp('closed_at')->nullable()->after('closing_reason');
            $table->timestamp('text_purged_at')->nullable()->after('closed_at');
            $table->text('description')->nullable()->change();
            $table->index('closed_at');
        });

        // The reporter may disappear (they deleted their account); the complaint stays.
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['reporter_user_id']);
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('reporter_user_id')->nullable()->change();
            $table->foreign('reporter_user_id')->references('id')->on('users')->nullOnDelete();
        });

        // Complaints that were closed before this existed: the day they were last touched is the best we know.
        DB::table('complaints')->whereIn('status', ['resolved', 'dismissed'])->whereNull('closed_at')->update(['closed_at' => DB::raw('updated_at')]);

        Schema::create('complaint_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // the admin who sent it
            $table->text('message')->nullable(); // erased by the retention rule
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_warnings');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['reporter_user_id']);
        });
        Schema::table('complaints', function (Blueprint $table) {
            $table->unsignedBigInteger('reporter_user_id')->nullable(false)->change();
            $table->foreign('reporter_user_id')->references('id')->on('users');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['closed_at']);
            $table->dropColumn(['closing_reason', 'closed_at', 'text_purged_at']);
        });
    }
};
