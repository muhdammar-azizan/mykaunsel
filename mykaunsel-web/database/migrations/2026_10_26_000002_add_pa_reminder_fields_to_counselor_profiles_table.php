<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            // The automatic "your PA is running out" emails: which PA date the last one was about and which step it was
            // (30, 7 or 1 days before), so each step is sent once per PA date even if the daily check runs late or twice.
            $table->date('pa_reminder_for')->nullable()->after('pa_valid_until');
            $table->unsignedSmallInteger('pa_reminder_days')->nullable()->after('pa_reminder_for');
        });
    }

    public function down(): void
    {
        Schema::table('counselor_profiles', function (Blueprint $table) {
            $table->dropColumn(['pa_reminder_for', 'pa_reminder_days']);
        });
    }
};
