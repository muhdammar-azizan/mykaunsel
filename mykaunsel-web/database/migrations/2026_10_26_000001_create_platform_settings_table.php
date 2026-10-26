<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Every setting has a row from the start (so it can be locked while it is changed): everything open, no maintenance.
        $now = now();
        $rows = [['key' => 'maintenance', 'value' => json_encode(false)]];
        foreach (array_keys(config('platform-settings.registration_roles')) as $role) {
            $rows[] = ['key' => "registration.{$role}", 'value' => json_encode(true)];
        }
        foreach (config('platform-settings.default_messages') as $key => $text) {
            $rows[] = ['key' => "message.{$key}", 'value' => json_encode($text)];
        }

        DB::table('platform_settings')->insert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $rows));
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
