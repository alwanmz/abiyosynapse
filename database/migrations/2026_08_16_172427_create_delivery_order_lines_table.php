<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_order_lines');
    }
};
