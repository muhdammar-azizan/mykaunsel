<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('org_id')->constrained('organizations');
            $table->foreignId('unit_id')->nullable()->constrained('org_units');
            $table->string('role');
            $table->string('status');
            $table->string('join_source')->nullable();
            $table->date('expected_graduation_date')->nullable();
            $table->dateTime('last_verified_at')->nullable();
            $table->dateTime('notice_started_at')->nullable();
            $table->dateTime('offboarded_at')->nullable();
            $table->text('offboard_reason')->nullable();
            $table->dateTime('joined_at');
            $table->timestamps();

            $table->unique(['user_id', 'org_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
