<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            // No 3-way match on the sales side (that's a purchasing
            // control) — a sales invoice posts AR/Revenue/COGS as soon as
            // it's created, capped at each line's delivered-but-not-yet-
            // invoiced quantity (enforced in SalesInvoiceService).
            $table->enum('status', ['posted', 'paid'])->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
