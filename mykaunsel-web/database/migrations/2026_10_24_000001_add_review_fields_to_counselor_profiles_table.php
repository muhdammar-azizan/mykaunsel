<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            // When the three documents were (re)submitted — drives "waiting for N days" and oldest-first ordering.
            $table->dateTime('submitted_at')->nullable()->after('verification_status');
            $table->unsignedSmallInteger('submission_count')->default(1)->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('suspension_reason')->nullable()->after('reviewed_at');
            $table->dateTime('suspended_at')->nullable()->after('suspension_reason');
            // Null when the system did it (PA expiry).
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->string('suspension_source', 20)->nullable()->after('suspended_by'); // platform | complaint | system
            $table->json('review_checklist')->nullable()->after('suspension_source');

            $table->index(['verification_status', 'verification_type']);
            $table->index('pa_valid_until');
            $table->index('submitted_at');
        });

        // Applicants who already uploaded all three documents were "submitted" when their profile last changed.
        DB::table('counselor_profiles')
            ->whereNotNull('cert_document_path')->whereNotNull('pa_document_path')->whereNotNull('ic_document_path')
            ->update(['submitted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropIndex(['verification_status', 'verification_type']);
            $table->dropIndex(['pa_valid_until']);
            $table->dropIndex(['submitted_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['submitted_at', 'submission_count', 'reviewed_at', 'suspension_reason', 'suspended_at', 'suspension_source', 'review_checklist']);
        });
    }
};
