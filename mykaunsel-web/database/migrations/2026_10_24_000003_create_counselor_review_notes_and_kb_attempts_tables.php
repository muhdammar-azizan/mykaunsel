<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal notes between Platform Admins. Never shown to the applicant.
        Schema::create('counselor_review_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_profile_id')->constrained('counselor_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['counselor_profile_id', 'created_at']);
        });

        // Someone tried to register a KB number that already belongs to this account (the unique constraint stopped them).
        Schema::create('counselor_kb_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_profile_id')->constrained('counselor_profiles')->cascadeOnDelete();
            $table->string('kb_number', 20);
            $table->string('submitted_name');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            // "one record per KB + IP per hour" looks up exactly this.
            $table->index(['kb_number', 'ip', 'created_at']);
            $table->index('counselor_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_kb_attempts');
        Schema::dropIfExists('counselor_review_notes');
    }
};
