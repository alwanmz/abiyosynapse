<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_code_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('invoiced_quantity', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
