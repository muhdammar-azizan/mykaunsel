<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal notes between Platform Admins. Never shown to the reporter or the counselor.
        Schema::create('complaint_review_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        // The list filters by status and orders by age.
        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index('counselor_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['counselor_user_id']);
        });

        Schema::dropIfExists('complaint_review_notes');
    }
};
