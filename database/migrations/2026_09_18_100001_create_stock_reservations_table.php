<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_order_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('status', 20)->default('reserved');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'status'], 'stock_reservations_availability_lookup');
            $table->index(['delivery_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
