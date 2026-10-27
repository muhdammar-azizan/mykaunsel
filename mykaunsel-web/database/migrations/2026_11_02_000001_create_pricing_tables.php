<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing & Promotions. There is NO payment: these tables only record plans, promo codes and who is on which plan.
 *
 * - Money is always an integer in sen (price_cents and friends), so a billing period or a currency can be added later.
 * - subscription_plans / promo_codes: `version` goes up with every save; an edit says which version it saw and is refused
 *   when someone else saved first.
 * - subscription_assignments: one row per assignment, with the price, discount and final price AS THEY WERE (snapshots), so
 *   editing a plan later never changes what an organization was given. Old rows stay as history; the organization points to
 *   its current one.
 * - plans are never deleted (restrict), and a promo code that was used cannot be deleted (restrict).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('org_type', 16);
            $table->string('name', 100);
            $table->unsignedInteger('min_members');
            $table->unsignedInteger('max_members')->nullable(); // null = no upper limit
            $table->unsignedBigInteger('price_cents');          // yearly price, in sen
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['org_type', 'active']);
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();                // stored in capitals
            $table->string('type', 10);                          // percent | fixed
            $table->unsignedBigInteger('value');                 // 1-100 for percent, sen for fixed
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->unsignedInteger('usage_limit')->nullable();  // null = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->string('applies_to', 16)->default('all');    // all | university | corporate
            $table->boolean('disabled')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['disabled', 'valid_from', 'valid_until']);
        });

        Schema::create('subscription_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->restrictOnDelete();
            $table->unsignedBigInteger('plan_price_cents');      // the plan's price when it was assigned
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('final_price_cents');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'id']);
            $table->index('plan_id');
            $table->index('ends_on');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('current_assignment_id')->nullable()->after('subscription_ends_at')
                ->constrained('subscription_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_assignment_id');
        });

        Schema::dropIfExists('subscription_assignments');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('subscription_plans');
    }
};
