<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->string('pending_email')->nullable();
            $table->string('timezone', 64)->default('Asia/Kuala_Lumpur');
            $table->json('notification_preferences')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path', 'pending_email', 'timezone', 'notification_preferences',
                'last_login_at', 'last_login_ip',
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            ]);
        });
    }
};
