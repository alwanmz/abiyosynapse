<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('order_date');
            $table->date('requested_delivery_date')->nullable();
            // Blueprint §17 SO status lifecycle (mirrors PO): Draft →
            // Approval → Approved → Partial/Fulfilled → Closed.
            $table->enum('status', ['draft', 'approval', 'approved', 'partial', 'fulfilled', 'closed'])->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
