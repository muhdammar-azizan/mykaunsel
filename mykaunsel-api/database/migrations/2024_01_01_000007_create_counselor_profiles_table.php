<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('kb_number')->unique();
            $table->string('pa_number');
            $table->date('pa_valid_until');
            $table->string('verification_type');
            $table->string('verification_status')->default('pending');
            $table->string('meeting_provider')->nullable();
            $table->string('display_name_org')->nullable();
            $table->string('display_name_independent')->nullable();
            $table->boolean('accepts_requests')->default(false);
            $table->integer('buffer_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_profiles');
    }
};
