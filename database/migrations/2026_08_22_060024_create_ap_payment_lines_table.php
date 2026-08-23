<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ap_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_applied', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_lines');
    }
};
