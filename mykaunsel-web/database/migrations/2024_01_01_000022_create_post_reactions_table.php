<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained('feed_posts');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->unique(['feed_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};
