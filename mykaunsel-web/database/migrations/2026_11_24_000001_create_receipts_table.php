<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts (docs/PAYMENTS_PLAN.md, "Invoices and receipts"): the proof that an invoice was paid, a document of its own with its own number
 * (RCT-2026-0001) made at the moment the invoice is paid. Like an invoice it keeps, as they were that day, who was paid, who paid, the amounts and how
 * it was paid: nothing edited later changes it, and it is never deleted. One receipt per invoice (a unique key), and none for an invoice of RM 0.00.
 *
 * Invoices paid before receipts existed get theirs here, dated the day they were paid, so every paid invoice has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('number', 32)->unique();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('org_id')->constrained('organizations')->restrictOnDelete();
            $table->dateTime('paid_at');
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('description', 200);
            $table->date('period_starts_on');
            $table->date('period_ends_on');
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->string('tax_name', 20)->nullable();
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('MYR');
            $table->json('seller');
            $table->json('buyer');
            $table->timestamps();

            $table->index(['org_id', 'id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_counters');
    }

    /** A receipt for every invoice already paid (and not RM 0.00), in the order they were paid, numbered by the year they were paid. */
    private function backfill(): void
    {
        $paid = DB::table('invoices')->where('status', 'paid')->where('total_cents', '>', 0)->whereNotNull('paid_at')->orderBy('paid_at')->orderBy('id')->get();
        $counters = [];

        foreach ($paid as $invoice) {
            $year = (int) substr((string) $invoice->paid_at, 0, 4);
            $counters[$year] = ($counters[$year] ?? 0) + 1;

            DB::table('receipts')->insert([
                'public_id' => strtolower((string) \Illuminate\Support\Str::ulid()),
                'number' => 'RCT-'.$year.'-'.str_pad((string) $counters[$year], 4, '0', STR_PAD_LEFT),
                'invoice_id' => $invoice->id,
                'org_id' => $invoice->org_id,
                'paid_at' => $invoice->paid_at,
                'payment_method' => $invoice->payment_method,
                'payment_reference' => $invoice->payment_reference,
                'description' => $invoice->description,
                'period_starts_on' => $invoice->period_starts_on,
                'period_ends_on' => $invoice->period_ends_on,
                'subtotal_cents' => $invoice->subtotal_cents,
                'discount_cents' => $invoice->discount_cents,
                'tax_name' => $invoice->tax_name,
                'tax_rate_bp' => $invoice->tax_rate_bp,
                'tax_cents' => $invoice->tax_cents,
                'total_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'seller' => $invoice->seller,
                'buyer' => $invoice->buyer,
                'created_at' => $invoice->paid_at,
                'updated_at' => $invoice->paid_at,
            ]);
        }

        foreach ($counters as $year => $last) {
            DB::table('receipt_counters')->insert(['year' => $year, 'last_number' => $last]);
        }
    }
};
