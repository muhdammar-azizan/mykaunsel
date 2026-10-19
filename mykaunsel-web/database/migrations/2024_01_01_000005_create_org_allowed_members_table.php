<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_allowed_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations');
            $table->string('identifier');
            $table->foreignId('claimed_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_allowed_members');
    }
};
