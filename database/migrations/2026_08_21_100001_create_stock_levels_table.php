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
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 18, 4)->default(0);
            // Weighted-average unit cost — kept up to date for BOTH
            // valuation methods so reporting can always show "current avg
            // cost" at a glance; only 'average'-method products actually
            // consume stock using this value. FIFO products consume via
            // stock_lots instead and this column becomes a derived display
            // figure for them (total lot value / total lot qty).
            $table->decimal('average_unit_cost', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
