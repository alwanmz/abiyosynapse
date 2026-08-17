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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            // Manufactured = built in-house via BOM/Routing (Fase 3).
            // Purchased = bought from a supplier, never produced.
            // Service = non-stock, no warehouse/inventory tracking.
            $table->enum('type', ['manufactured', 'purchased', 'service']);
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_uom_id')->constrained('unit_of_measures')->restrictOnDelete();
            $table->enum('make_or_buy', ['make', 'buy'])->default('buy');
            $table->boolean('lot_tracked')->default(false);
            $table->boolean('serial_tracked')->default(false);
            $table->decimal('standard_cost', 18, 2)->default(0);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->foreignId('default_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            // BOM/Routing don't exist until Fase 3 — these stay null until
            // then. Referencing the table names directly (not a model FK
            // constraint yet) since those tables aren't created here.
            $table->unsignedBigInteger('bom_id')->nullable();
            $table->unsignedBigInteger('routing_id')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'active', 'inactive'])->default('draft');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
