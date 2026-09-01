<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->string('attendance');
            $table->string('follow_up_status')->default('none');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_notes');
    }
};
