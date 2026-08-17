<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            // Blueprint §17 PO status lifecycle: Draft → Approval → Approved
            // → Sent → Partial/Received → Closed.
            $table->enum('status', ['draft', 'approval', 'approved', 'sent', 'partial', 'received', 'closed'])->default('draft');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
