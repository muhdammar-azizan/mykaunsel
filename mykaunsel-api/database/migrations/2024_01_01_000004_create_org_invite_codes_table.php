<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_invite_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations');
            $table->string('code')->unique();
            $table->dateTime('expires_at')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_invite_codes');
    }
};
