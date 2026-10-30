<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A counselor's weekly hours: "every Monday 09:00 to 12:00, in one-hour times, online, for this organization". They are a pattern only. Bookable
 * times (calendar_entries) are made from them on request ("fill the next weeks"); nothing here is bookable by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_weekly_hours', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('counselor_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('context_org_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = Monday ... 7 = Sunday (ISO)
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes');
            $table->string('session_mode', 16);
            $table->foreignId('location_id')->nullable()->constrained('practice_locations')->nullOnDelete();
            $table->timestamps();

            $table->index(['counselor_user_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_weekly_hours');
    }
};
