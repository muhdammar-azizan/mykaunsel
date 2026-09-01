<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->nullable()->constrained('organizations');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('counselor_user_id')->constrained('users');
            $table->foreignId('calendar_entry_id')->nullable()->constrained('calendar_entries');
            $table->string('booking_mode');
            $table->string('status')->default('confirmed');
            $table->string('session_mode');
            $table->foreignId('location_id')->nullable()->constrained('practice_locations');
            $table->string('meeting_provider')->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('meeting_space_id')->nullable();
            $table->integer('reschedule_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
