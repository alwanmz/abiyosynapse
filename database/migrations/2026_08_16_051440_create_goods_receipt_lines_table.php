<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('unit_cost', 18, 2);
            // Populated once Incoming QC (Fase 3.5's QualityInspectionService,
            // type=incoming) runs against this line; good enters stock, the
            // rest is excluded from put-away per the same good/reject split
            // used by Final Inspection on Production Orders.
            $table->decimal('quantity_accepted', 18, 4)->nullable();
            $table->decimal('quantity_rejected', 18, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
