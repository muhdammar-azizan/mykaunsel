<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations');
            $table->string('domain')->unique();
            $table->string('default_role');
            $table->string('match_type')->default('exact');
            $table->string('verification_token')->nullable();
            $table->boolean('dns_verified')->default(false);
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('last_checked_at')->nullable();
            $table->integer('check_attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_domains');
    }
};
