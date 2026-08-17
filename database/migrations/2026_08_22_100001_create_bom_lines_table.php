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
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products')->restrictOnDelete();
            // Quantity of the component needed per batch_quantity of the
            // parent BOM's output (blueprint: "Qty / 100 PCS" column).
            $table->decimal('quantity_per_batch', 18, 4);
            $table->foreignId('uom_id')->constrained('unit_of_measures')->restrictOnDelete();
            // Expected loss % for this component during production —
            // MRP/costing will inflate the required quantity by this
            // factor once those calculations are wired up.
            $table->decimal('scrap_percentage', 5, 2)->default(0);
            $table->unsignedInteger('sequence')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
    }
};
