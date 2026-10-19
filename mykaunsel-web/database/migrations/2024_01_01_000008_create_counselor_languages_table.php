<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_profile_id')->constrained('counselor_profiles');
            $table->string('language');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_languages');
    }
};
