<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client reviews of counselors (docs/REVIEWS.md): one per booking. The counselor and the organization never see who wrote one (no screen reads
 * `client_user_id`). The comment and the counselor's reply are stored encrypted. `status`: published | held (the comment waits for the MyKaunsel
 * team to read it; the stars already count) | removed (by the MyKaunsel team, for breaking the guidelines; never shown or counted).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('booking_id')->unique()->constrained('bookings');
            $table->foreignId('counselor_user_id')->constrained('users');
            $table->foreignId('client_user_id')->constrained('users');
            $table->unsignedTinyInteger('rating');
            $table->json('tags')->nullable();
            $table->text('comment')->nullable();                       // encrypted
            $table->boolean('attendance_confirmed')->default(true);
            $table->string('status', 12)->default('published');
            $table->text('reply')->nullable();                         // encrypted
            $table->dateTime('replied_at')->nullable();
            $table->dateTime('flagged_at')->nullable();
            $table->string('flag_reason', 20)->nullable();
            $table->string('flag_status', 12)->nullable();             // open | upheld | dismissed
            $table->timestamps();

            $table->index(['counselor_user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
