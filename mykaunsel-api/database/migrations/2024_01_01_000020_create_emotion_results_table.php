<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emotion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages');
            $table->string('emotion');
            $table->decimal('confidence_score', 5, 4);
            $table->dateTime('analysed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emotion_results');
    }
};
