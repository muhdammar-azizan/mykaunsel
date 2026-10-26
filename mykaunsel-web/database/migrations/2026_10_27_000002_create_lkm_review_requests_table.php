<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A counselor asking Platform Admin to look at their LKM record again ("my practising certificate was renewed"), with
 * evidence. See docs/LKM_DIRECTORY.md.
 *
 * - `current` and `claimed` hold the values at request time and what the counselor says they should be
 *   (pa_valid_until always; pa_number / full_name only when the counselor claims a change to them).
 * - `before` / `after` are filled when it is approved: the LKM record's values before and after the admin changed them.
 * - `pending_for` equals counselor_profile_id while the request is pending and is NULL afterwards. A unique index on it
 *   means a counselor can never have two pending requests, even if two requests arrive at the same moment.
 * - The evidence is a file on a private disk. `evidence_purged_at` says the file was removed under the retention rules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lkm_review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index(); // pending | approved | rejected
            $table->unsignedBigInteger('pending_for')->nullable()->unique();
            $table->json('current');
            $table->json('claimed');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->unsignedInteger('evidence_size')->nullable();
            $table->timestamp('evidence_purged_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reviewed_at']);
        });

        // The LKM Directory list filters and sorts on these.
        Schema::table('lkm_directory_snapshot', function (Blueprint $table) {
            $table->index('status');
            $table->index('pa_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('lkm_directory_snapshot', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['pa_valid_until']);
        });

        Schema::dropIfExists('lkm_review_requests');
    }
};
