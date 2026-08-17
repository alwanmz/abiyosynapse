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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // in: receipt (purchase, production output, positive adjustment)
            // out: issue (sale, material consumption, negative adjustment)
            // transfer_out / transfer_in: warehouse-to-warehouse moves
            $table->enum('type', ['in', 'out', 'transfer_out', 'transfer_in']);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('total_cost', 18, 4);
            // Running balance after this movement — denormalized for cheap
            // "stock card" reporting without recomputing from history.
            $table->decimal('balance_quantity', 18, 4);
            $table->text('notes')->nullable();
            // Whatever business event caused this (StockOpname adjustment,
            // future PurchaseReceipt/ProductionOrder/SalesInvoice in later
            // phases). Nullable for manual adjustments.
            $table->nullableMorphs('sourceable');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
