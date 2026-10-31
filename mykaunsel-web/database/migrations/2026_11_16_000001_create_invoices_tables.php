<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices for the yearly subscription (docs/PAYMENTS_PLAN.md). Money is whole sen. An invoice keeps, as they were the day it was issued, who
 * sold (`seller`), who was billed (`buyer`), the tax name and rate, and every amount: editing a plan, a setting or an organization later never
 * changes an invoice. An invoice is never deleted: it is paid, or voided with a reason.
 *
 * `invoice_counters` gives the next number of a year (INV-2026-0001); the row is locked while a number is taken, so two invoices can not share one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('number', 32)->unique();
            $table->foreignId('org_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('assignment_id')->nullable()->unique()->constrained('subscription_assignments')->nullOnDelete();
            $table->string('status', 12)->default('unpaid');           // unpaid | paid | void
            $table->date('issued_on');
            $table->date('due_on');
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->string('description', 200);
            $table->unsignedBigInteger('subtotal_cents');              // the plan price
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->string('tax_name', 20)->nullable();
            $table->unsignedInteger('tax_rate_bp')->default(0);        // 800 = 8%
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('MYR');
            $table->json('seller');
            $table->json('buyer');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();        // manual | no_charge | billplz (later)
            $table->string('payment_reference', 100)->nullable();
            $table->foreignId('paid_marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['org_id', 'id']);
            $table->index(['status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_counters');
    }
};
