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
        Schema::create('production_order_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('products')->restrictOnDelete();
            // Snapshot of the BOM explosion at release time: required =
            // bom_line.quantity_per_batch / bom.batch_quantity *
            // planned_quantity, inflated by scrap_percentage.
            $table->decimal('required_quantity', 18, 4);
            $table->decimal('issued_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['production_order_id', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_components');
    }
};
