<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per try to pay an invoice online (docs/PAYMENTS_PLAN.md). The invoice stays unpaid until a try succeeds; failed and abandoned tries are
 * kept so an admin can see what happened. `amount_cents` is the invoice total on the day the try began; a result is only accepted for that amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('status', 12)->default('pending');     // pending | succeeded | failed
            $table->unsignedBigInteger('amount_cents');
            $table->string('provider_reference', 100)->nullable();
            $table->dateTime('settled_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['invoice_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
