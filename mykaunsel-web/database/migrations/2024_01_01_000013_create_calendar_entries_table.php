<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_user_id')->constrained('users');
            $table->string('entry_type');
            $table->foreignId('context_org_id')->nullable()->constrained('organizations');
            $table->string('title')->nullable();
            $table->date('entry_date');
            $table->time('start_time');
            $table->integer('duration_minutes')->default(60);
            $table->string('session_mode')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('practice_locations');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['counselor_user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_entries');
    }
};
