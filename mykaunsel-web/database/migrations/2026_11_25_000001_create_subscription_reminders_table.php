<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which warning about the end of a subscription has been sent, so each one goes out once (config/subscription-reminders.php). One row per organization,
 * last day of the plan, audience (admin or counselor) and step (days before the end; 0 = it has ended). A renewal has a new last day, so its steps start
 * again. The unique key is also what stops two runs at the same moment from sending twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('ends_on');
            $table->string('audience', 12);               // admin | counselor
            $table->unsignedSmallInteger('step');         // days before the last day; 0 = it has ended
            $table->dateTime('sent_at');
            $table->unique(['org_id', 'ends_on', 'audience', 'step'], 'subscription_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
    }
};
