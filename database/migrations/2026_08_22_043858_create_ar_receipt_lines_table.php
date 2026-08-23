<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_applied', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_receipt_lines');
    }
};
