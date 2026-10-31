<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which Terms of Use and Privacy Policy a person agreed to when they signed up, and when (config/legal.php `terms_version`). Older accounts have none. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dateTime('terms_accepted_at')->nullable();
            $table->string('terms_version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version']);
        });
    }
};
