<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('delivery_date');
            // draft: lines recorded but stock not yet issued. shipped:
            // FG stock issued (InventoryValuationService::issue()) and
            // COGS/Finished Goods posted — mirrors Goods Receipt's
            // pending_inspection -> put_away split, but delivery has no
            // inspection step so it's a simple two-state flow.
            $table->enum('status', ['draft', 'shipped'])->default('draft');
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
