<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('received_date');
            // pending_inspection: goods physically arrived, awaiting Incoming
            // QC (Fase 3.5) before they're put away into usable stock.
            $table->enum('status', ['pending_inspection', 'put_away'])->default('pending_inspection');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
