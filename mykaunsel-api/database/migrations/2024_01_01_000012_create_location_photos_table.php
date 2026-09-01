<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_location_id')->constrained('practice_locations');
            $table->string('photo_url');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_photos');
    }
};
