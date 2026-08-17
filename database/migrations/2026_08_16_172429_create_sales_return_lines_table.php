<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('unit_cost', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_lines');
    }
};
