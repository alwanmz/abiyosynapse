<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_transaction_id')->constrained()->restrictOnDelete();
            $table->boolean('is_cleared')->default(false);
            $table->timestamps();

            $table->unique(['bank_reconciliation_id', 'cash_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
    }
};
