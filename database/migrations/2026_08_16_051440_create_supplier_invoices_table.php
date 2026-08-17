<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('supplier_reference')->nullable();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            // matched: 3-way match (PO qty/price vs Goods Receipt qty vs
            // Invoice qty/price) passed within tolerance and AP was posted.
            // disputed: mismatch found, AP posting withheld until resolved.
            $table->enum('status', ['pending_match', 'matched', 'disputed', 'paid'])->default('pending_match');
            $table->text('dispute_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
