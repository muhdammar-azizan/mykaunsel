<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // What the applicant picked as "organization size" at signup (was wrongly stored in subscription_tier).
            $table->string('estimated_size')->nullable()->after('ssm_number');
            $table->unsignedSmallInteger('submission_count')->default(1)->after('rejection_reason');
            $table->dateTime('submitted_at')->nullable()->after('submission_count');
            $table->dateTime('authority_confirmed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->after('authority_confirmed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('suspension_reason')->nullable()->after('reviewed_at');
            $table->dateTime('suspended_at')->nullable()->after('suspension_reason');
            $table->json('review_checklist')->nullable()->after('suspended_at');
        });

        // Move existing size values out of subscription_tier (Pricing will use that column later).
        DB::table('organizations')->whereNotNull('subscription_tier')->update([
            'estimated_size' => DB::raw('subscription_tier'),
            'subscription_tier' => null,
        ]);

        DB::table('organizations')->update(['submitted_at' => DB::raw('created_at')]);

        Schema::create('org_review_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['org_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_review_notes');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'estimated_size', 'submission_count', 'submitted_at', 'authority_confirmed_at',
                'reviewed_at', 'suspension_reason', 'suspended_at', 'review_checklist',
            ]);
        });
    }
};
