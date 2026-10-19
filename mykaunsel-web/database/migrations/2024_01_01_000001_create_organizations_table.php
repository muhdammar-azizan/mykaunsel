<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('org_type');
            $table->string('access_model');
            $table->string('subscription_status')->default('pending');
            $table->string('subscription_tier')->nullable();
            $table->dateTime('subscription_ends_at')->nullable();
            $table->boolean('allow_counselor_freelance')->default(false);
            $table->string('join_method')->nullable();
            $table->integer('cancellation_deadline_hours')->default(24);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
