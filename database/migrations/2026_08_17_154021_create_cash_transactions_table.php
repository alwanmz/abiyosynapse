<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->date('transaction_date');
            // The other side of the double-entry (e.g. 3.1 Modal Disetor
            // for a capital injection "in", 5.5 Beban Operasional for an
            // expense "out"). Generic Kas Bank entries are not tied to a
            // specific AR/AP document — those get their own dedicated
            // collection/payment flow in Fase 7/8, which will post to
            // this same bank_account's GL account but through their own
            // service, not through CashTransaction.
            $table->foreignId('counter_account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
