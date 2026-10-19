<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lkm_directory_snapshot', function (Blueprint $table) {
            $table->id();
            $table->string('kb_number')->unique();
            $table->string('pa_number');
            $table->string('full_name');
            $table->string('status')->default('Aktif');
            $table->date('pa_valid_from');
            $table->date('pa_valid_until');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lkm_directory_snapshot');
    }
};
