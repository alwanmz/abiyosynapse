<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            // One FIFO layer per receipt. received_at + id order defines
            // consumption order (oldest first) — deliberately not a
            // separate "sequence" column since receipt order IS FIFO order.
            $table->timestamp('received_at');
            $table->decimal('quantity_received', 18, 4);
            // Remaining quantity in this layer — decreases as it's
            // consumed by later issues; the layer is exhausted at 0 but
            // kept for traceability rather than deleted.
            $table->decimal('quantity_remaining', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->nullableMorphs('sourceable');
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
